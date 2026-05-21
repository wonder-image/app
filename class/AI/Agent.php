<?php

namespace Wonder\AI;

use RuntimeException;

/**
 * Value object che rappresenta un agente AI risolto (config + prompt +
 * provenienza dei file). Immutable.
 *
 * Costruito da `AgentResolver::resolve()`, mai direttamente. La sua API
 * pubblica è pensata per essere stabile: i consumer scriveranno codice
 * tipo `AgentRegistry::get('slug')->prompt()` e questo deve continuare
 * a funzionare quando un PR successivo implementerà davvero `run()`.
 *
 * Nota su `run()`: è uno **stub** in questa iterazione. La firma è
 * definitiva (`array $input` → `array`), ma il metodo throwa subito.
 * Permette di scrivere già nel codice consumer i call site senza
 * bisogno di refactor futuri.
 */
final class Agent
{
    /**
     * @param string             $slug        Identificatore kebab-case (nome cartella).
     * @param AgentConfig        $config      Config risolta (file-level cascade + overrides).
     * @param string             $promptPath  Path assoluto al file `prompt.md` risolto.
     * @param array<string,string> $sources   Mappa `<filename>` → source layer
     *                                        (`'app'`, `'module:<slug>'`, `'consumer'`).
     *                                        Utile per `status:agents` e debug.
     */
    public function __construct(
        public readonly string $slug,
        public readonly AgentConfig $config,
        public readonly string $promptPath,
        public readonly array $sources,
    ) {}

    /**
     * Carica il contenuto del prompt dal file `prompt.md`. Lazy: il file
     * viene letto solo on demand, non al momento della discovery
     * (`AgentRegistry::all()` deve restare cheap).
     *
     * @throws RuntimeException Se il file non esiste o non è leggibile.
     */
    public function prompt(): string
    {
        if ($this->promptPath === '' || !is_file($this->promptPath)) {
            throw new RuntimeException(
                'Prompt file non trovato per agente "'.$this->slug.'": '.$this->promptPath
            );
        }

        $contents = @file_get_contents($this->promptPath);

        if ($contents === false) {
            throw new RuntimeException(
                'Impossibile leggere il prompt di "'.$this->slug.'" da '.$this->promptPath
            );
        }

        return $contents;
    }

    /**
     * Esegue l'agente con l'input fornito. STUB in questa iterazione.
     *
     * La firma e il contratto sono definitivi:
     *   - Input: array associativo (i parametri attesi dipendono
     *     dall'agente specifico; saranno validati via JSON schema in
     *     un PR successivo).
     *   - Output: array associativo con almeno `success` e `result`.
     *
     * @throws RuntimeException Sempre, finché non viene implementato.
     */
    public function run(array $input): array
    {
        throw new RuntimeException(
            'Agent::run() non ancora implementato. La discovery e la cascade '
            .'sono pronte (vedi AgentRegistry::get("'.$this->slug.'")), '
            .'ma l\'integrazione con il provider LLM ('.$this->config->provider
            .') sarà aggiunta in un PR successivo. Per ora puoi usare '
            .'l\'agente per leggere prompt/config: $agent->prompt(), $agent->config.'
        );
    }
}
