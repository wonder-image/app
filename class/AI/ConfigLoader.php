<?php

namespace Wonder\AI;

use Wonder\App\LegacyGlobals;

/**
 * Caricatore dei due file YAML di configurazione AI lato consumer:
 *
 * - `<framework>/ai/config.yml` + `<root>/ai/config.yml` → defaults globali
 *   (model di partenza, provider, timeout). Il framework può shippare un
 *   suo set di default e il consumer può overridarli con merge profondo.
 *
 * - `<root>/ai/overrides.yml` → tweak run-time per singolo agente, applicati
 *   DOPO la cascade file-level di `AgentResolver`. Solo settings di
 *   esecuzione (model, temperature, max_tokens, tools); il `prompt` NON
 *   è overridabile da qui (per quello c'è la cascade file-level che
 *   prende `<root>/ai/agents/<slug>/prompt.md`).
 *
 * Tutto static + cached: le config si leggono pochissime volte per
 * request, non vale la pena di passare istanze in giro. `reset()` per
 * i test e per il long-running CLI.
 */
final class ConfigLoader
{
    /** @var array<string,mixed>|null */
    private static ?array $defaultsCache = null;

    /** @var array<string,mixed>|null */
    private static ?array $overridesCache = null;

    public static function reset(): void
    {
        self::$defaultsCache = null;
        self::$overridesCache = null;
        YamlReader::reset();
    }

    /**
     * Defaults globali per gli agenti.
     *
     * Sorgenti in cascade:
     *   1. `<framework>/ai/config.yml` — base layer shippato col framework
     *   2. `<consumer>/ai/config.yml` — override del progetto
     *
     * Merge profondo (`array_replace_recursive`). Il consumer vince per
     * ogni chiave dichiarata. Niente discovery dai moduli: per ora la
     * config "globale" è solo framework + consumer (i moduli pubblicano
     * agenti, non default globali).
     *
     * Esempio shape di ritorno:
     * ```
     * [
     *   'defaults' => ['model' => 'claude-sonnet-4', 'temperature' => 0, ...],
     *   'providers' => ['anthropic' => ['api_key_env' => 'ANTHROPIC_API_KEY'], ...],
     * ]
     * ```
     */
    public static function defaults(): array
    {
        if (self::$defaultsCache !== null) {
            return self::$defaultsCache;
        }

        $merged = [];

        // Framework layer
        $frameworkPath = self::frameworkRoot().'/ai/config.yml';
        $framework = YamlReader::parseFile($frameworkPath);
        if (is_array($framework)) {
            $merged = $framework;
        }

        // Consumer layer
        $consumerRoot = self::consumerRoot();
        if ($consumerRoot !== null) {
            $consumer = YamlReader::parseFile($consumerRoot.'/ai/config.yml');
            if (is_array($consumer)) {
                $merged = array_replace_recursive($merged, $consumer);
            }
        }

        self::$defaultsCache = $merged;
        return $merged;
    }

    /**
     * Mappa di override per slug agente.
     *
     * Letta UNA SOLA volta da `<consumer>/ai/overrides.yml`. Forma:
     * ```
     * [
     *   'seo-meta-writer' => ['model' => 'claude-haiku-4', 'temperature' => 0.3],
     *   'rsvp-reminder-composer' => ['max_tokens' => 4000],
     * ]
     * ```
     *
     * Niente fallback su framework: gli override sono un concept del
     * consumer. Un modulo che vuole "self-customizzare" un agente del
     * framework dovrebbe forkare l'agente nel proprio `ai/agents/`, non
     * usare overrides.
     */
    public static function overrides(): array
    {
        if (self::$overridesCache !== null) {
            return self::$overridesCache;
        }

        $consumerRoot = self::consumerRoot();

        if ($consumerRoot === null) {
            self::$overridesCache = [];
            return [];
        }

        $parsed = YamlReader::parseFile($consumerRoot.'/ai/overrides.yml');
        self::$overridesCache = is_array($parsed) ? $parsed : [];

        return self::$overridesCache;
    }

    /**
     * Override puntuali per uno slug specifico. Comodo nei consumer:
     * `ConfigLoader::overridesFor('seo-meta-writer')` invece di indicizzare
     * manualmente nell'array completo.
     */
    public static function overridesFor(string $slug): array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return [];
        }

        $all = self::overrides();
        $entry = $all[$slug] ?? null;

        return is_array($entry) ? $entry : [];
    }

    /**
     * Root del framework (dove vive questo file). Risale di 3 directory:
     * `class/AI/ConfigLoader.php` → `class/AI/` → `class/` → root pkg.
     */
    private static function frameworkRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Root del consumer (sito che ha installato il framework). Letto da
     * `LegacyGlobals::ROOT`. Se non disponibile (es. test isolati o
     * comandi pre-bootstrap), ritorna `null` e il caller degrada al
     * solo framework layer.
     */
    private static function consumerRoot(): ?string
    {
        $root = LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');

        if (!is_string($root) || trim($root) === '') {
            return null;
        }

        return rtrim($root, '/');
    }
}
