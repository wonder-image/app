<?php

namespace Wonder\AI;

use RuntimeException;
use Wonder\App\LegacyGlobals;
use Wonder\App\Module\Registry as ModuleRegistry;

/**
 * Orchestratore della discovery degli agenti AI nei 3 layer:
 *
 *   priority 10 → `<framework>/ai/agents/`              (sempre)
 *   priority 20 → `vendor/wonder-image/<modulo>/ai/agents/` (moduli enabled)
 *   priority 30 → `<consumer>/ai/agents/`               (sito)
 *
 * Mirror della logica di `Wonder\App\ResourceRegistry`. La discovery è
 * idempotente e cached staticamente. `reset()` per test.
 *
 * **Cosa fa**:
 *   - Lista tutti gli slug agente disponibili (unione dei 3 layer)
 *   - Per ciascuno, costruisce un `Agent` via `AgentResolver` con la
 *     cascade file-level
 *   - Cache statica: la registry materializza gli `Agent` una sola
 *     volta per request
 *
 * **Cosa NON fa**:
 *   - Esecuzione (sta in `Agent::run()`, oggi stub)
 *   - Validazione approfondita (sta in `AgentValidator`, chiamato da
 *     `status:agents` / `validate:agent`)
 *   - Caching cross-request (la prossima request rifa la discovery)
 */
final class AgentRegistry
{
    /** @var array<string,Agent>|null */
    private static ?array $agents = null;

    public static function reset(): void
    {
        self::$agents = null;
        ConfigLoader::reset();
        YamlReader::reset();
    }

    /**
     * Mappa slug → Agent risolto. Cached.
     *
     * @return array<string,Agent>
     */
    public static function all(): array
    {
        if (self::$agents !== null) {
            return self::$agents;
        }

        $layers = self::layers();
        $slugs = AgentResolver::discoverSlugs($layers);

        $agents = [];
        foreach ($slugs as $slug) {
            try {
                $agents[$slug] = AgentResolver::resolve($slug, $layers);
            } catch (RuntimeException $e) {
                // Errori di risoluzione (es. agent.yml malformato) NON
                // bloccano la registry intera: la registry serve anche
                // a `status:agents` che vuole elencare anche gli agenti
                // rotti. La validazione approfondita si fa altrove.
                //
                // Tracciamo l'errore via error_log così il dev vede il
                // problema senza far crashare la request.
                error_log('[AgentRegistry] Skip "'.$slug.'": '.$e->getMessage());
            }
        }

        ksort($agents);
        self::$agents = $agents;

        return $agents;
    }

    /**
     * @return array<int,string>
     */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $slug): bool
    {
        return isset(self::all()[trim($slug)]);
    }

    /**
     * Recupera un agente risolto.
     *
     * @throws RuntimeException Se lo slug non è registrato.
     */
    public static function get(string $slug): Agent
    {
        $slug = trim($slug);
        $all = self::all();

        if (!isset($all[$slug])) {
            throw new RuntimeException(
                'Agente non registrato: "'.$slug.'". Slug disponibili: '
                .(empty($all) ? '(nessuno)' : implode(', ', array_keys($all)))
            );
        }

        return $all[$slug];
    }

    /**
     * Layer di discovery in ordine di priority desc (consumer → moduli →
     * framework). L'ordine effettivo nella cascade dei file viene
     * ri-normalizzato da `AgentResolver::resolve()`, ma lo lasciamo
     * già corretto qui per leggibilità.
     *
     * @return array<int,array{path:string,priority:int,source:string}>
     */
    private static function layers(): array
    {
        $layers = [];

        // Priority 30: consumer (vince su tutto)
        $consumerRoot = self::consumerRoot();
        if ($consumerRoot !== null) {
            $consumerPath = $consumerRoot.'/ai/agents';
            if (is_dir($consumerPath)) {
                $layers[] = [
                    'path' => $consumerPath,
                    'priority' => 30,
                    'source' => 'consumer',
                ];
            }
        }

        // Priority 20: moduli abilitati
        foreach (ModuleRegistry::aiAgentDirectories() as $entry) {
            $layers[] = $entry;
        }

        // Priority 10: framework (base layer)
        $frameworkPath = self::frameworkRoot().'/ai/agents';
        if (is_dir($frameworkPath)) {
            $layers[] = [
                'path' => $frameworkPath,
                'priority' => 10,
                'source' => 'app',
            ];
        }

        return $layers;
    }

    private static function frameworkRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function consumerRoot(): ?string
    {
        $root = LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');

        if (!is_string($root) || trim($root) === '') {
            return null;
        }

        return rtrim($root, '/');
    }
}
