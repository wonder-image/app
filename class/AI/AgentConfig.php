<?php

namespace Wonder\AI;

/**
 * Value object immutable che rappresenta la config risolta di un agente,
 * cioè il contenuto canonico di `agent.yml` con i defaults globali applicati
 * (da `ConfigLoader::defaults()`) e gli eventuali override del consumer
 * (da `ConfigLoader::overridesFor($slug)`).
 *
 * Non rappresenta il prompt: quello vive nel file `prompt.md` separato,
 * gestito da `Agent::prompt()` con lazy loading.
 *
 * I campi ricalcano i tipici parametri di una chiamata LLM. Stub: in questa
 * iterazione il `run()` non li usa davvero (l'esecuzione SDK è rimandata),
 * ma la shape è già definitiva per evitare breaking change futuri.
 */
final class AgentConfig
{
    /**
     * @param array<int,string> $tools Slug dei tool registrati (vuoto per ora).
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $model,
        public readonly float $temperature,
        public readonly int $maxTokens,
        public readonly string $promptFile,
        public readonly array $tools,
        public readonly string $provider,
        public readonly int $timeoutSeconds,
        public readonly int $maxRetries,
    ) {}

    /**
     * Costruisce un `AgentConfig` da un array tipicamente proveniente dal
     * parsing di `agent.yml`, applicando i defaults globali per i campi
     * non specificati.
     *
     * Convenzione campi YAML (snake_case) → properties PHP (camelCase):
     *   - `max_tokens` → `maxTokens`
     *   - `prompt` → `promptFile`
     *   - `timeout_seconds` → `timeoutSeconds`
     *   - `max_retries` → `maxRetries`
     *
     * Campi obbligatori che non hanno un default sensato:
     *   - `name` (obbligatorio)
     *   - `description` (obbligatorio)
     *
     * Il `model` ha fallback su `defaults.model` dei config globali.
     * Il `prompt` di default è `prompt.md` (convenzione).
     */
    public static function fromArray(array $data, array $globalDefaults = []): self
    {
        $defaults = is_array($globalDefaults['defaults'] ?? null) ? $globalDefaults['defaults'] : [];

        return new self(
            name:           self::stringVal($data, 'name', ''),
            description:    self::stringVal($data, 'description', ''),
            model:          self::stringVal($data, 'model', self::stringVal($defaults, 'model', '')),
            temperature:    self::floatVal($data, 'temperature', self::floatVal($defaults, 'temperature', 0.0)),
            maxTokens:      self::intVal($data, 'max_tokens', self::intVal($defaults, 'max_tokens', 1024)),
            promptFile:     self::stringVal($data, 'prompt', 'prompt.md'),
            tools:          self::arrayOfStrings($data['tools'] ?? []),
            provider:       self::stringVal($data, 'provider', self::stringVal($defaults, 'provider', 'anthropic')),
            timeoutSeconds: self::intVal($data, 'timeout_seconds', self::intVal($defaults, 'timeout_seconds', 60)),
            maxRetries:     self::intVal($data, 'max_retries', self::intVal($defaults, 'max_retries', 2)),
        );
    }

    /**
     * Ritorna una NUOVA istanza con gli override applicati. Solo i campi
     * runtime-modificabili (model, temperature, max_tokens, tools,
     * provider, timeout, retries) possono essere cambiati: name,
     * description, promptFile restano dell'originale.
     *
     * Rationale per escludere `promptFile`: il prompt è la "personalità"
     * dell'agente. Cambiarlo via `overrides.yml` introdurrebbe due
     * meccanismi paralleli per la stessa cosa (overrides + file-level
     * cascade), confondendo chi legge il diff. Per cambiare il prompt
     * basta mettere un `prompt.md` nel consumer/ai/agents/<slug>/.
     */
    public function mergedWith(array $overrides): self
    {
        if (empty($overrides)) {
            return $this;
        }

        return new self(
            name:           $this->name,                                         // immutabile
            description:    $this->description,                                  // immutabile
            model:          self::stringVal($overrides, 'model', $this->model),
            temperature:    self::floatVal($overrides, 'temperature', $this->temperature),
            maxTokens:      self::intVal($overrides, 'max_tokens', $this->maxTokens),
            promptFile:     $this->promptFile,                                   // immutabile (vedi rationale sopra)
            tools:          isset($overrides['tools']) ? self::arrayOfStrings($overrides['tools']) : $this->tools,
            provider:       self::stringVal($overrides, 'provider', $this->provider),
            timeoutSeconds: self::intVal($overrides, 'timeout_seconds', $this->timeoutSeconds),
            maxRetries:     self::intVal($overrides, 'max_retries', $this->maxRetries),
        );
    }

    /**
     * Serializza in array (debug, JSON output di console commands).
     * Usa snake_case per coerenza con `agent.yml` (così il diff
     * config-su-disco vs config-risolta è leggibile).
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'prompt' => $this->promptFile,
            'tools' => $this->tools,
            'provider' => $this->provider,
            'timeout_seconds' => $this->timeoutSeconds,
            'max_retries' => $this->maxRetries,
        ];
    }

    private static function stringVal(array $data, string $key, string $default): string
    {
        $v = $data[$key] ?? null;
        return is_string($v) && trim($v) !== '' ? trim($v) : $default;
    }

    private static function intVal(array $data, string $key, int $default): int
    {
        $v = $data[$key] ?? null;
        if (is_int($v)) return $v;
        if (is_numeric($v)) return (int) $v;
        return $default;
    }

    private static function floatVal(array $data, string $key, float $default): float
    {
        $v = $data[$key] ?? null;
        if (is_float($v) || is_int($v)) return (float) $v;
        if (is_numeric($v)) return (float) $v;
        return $default;
    }

    /**
     * @return array<int,string>
     */
    private static function arrayOfStrings(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $out[] = trim($entry);
            }
        }
        return array_values(array_unique($out));
    }
}
