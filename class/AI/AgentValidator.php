<?php

namespace Wonder\AI;

use RuntimeException;

/**
 * Validatore della shape di `agent.yml`. Stesso pattern di
 * `Wonder\App\Module\ManifestValidator` per il `module.json`: ritorna
 * un array di stringhe (vuoto = tutto ok) oppure throwa via `assertValid()`.
 *
 * Lo slug dell'agente non è dentro al file (è il nome della cartella),
 * quindi viene passato come parametro per metterlo nei messaggi di errore.
 */
final class AgentValidator
{
    /**
     * Lancia una `RuntimeException` se la config è invalida.
     */
    public static function assertValid(array $data, string $slug, string $agentDir): void
    {
        $errors = self::errors($data, $slug, $agentDir);

        if ($errors === []) {
            return;
        }

        throw new RuntimeException(
            'agent.yml invalido per "'.$slug.'": '.implode(' | ', $errors)
        );
    }

    /**
     * Ritorna la lista degli errori riscontrati. Permette al `status:agents`
     * di mostrare TUTTI gli agenti col loro stato di validità senza fail-fast.
     *
     * @return array<int,string>
     */
    public static function errors(array $data, string $slug, string $agentDir): array
    {
        $errors = [];

        // Slug: stesso regex usato dai moduli (consistenza terminologica)
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $errors[] = 'Slug non valido (atteso kebab-case lowercase): '.$slug;
        }

        // Campi obbligatori
        foreach (['name', 'description', 'model'] as $field) {
            $v = $data[$field] ?? null;
            if (!is_string($v) || trim($v) === '') {
                $errors[] = "Campo obbligatorio mancante o vuoto: {$field}";
            }
        }

        // Tipi numerici (se presenti)
        if (isset($data['temperature']) && !is_numeric($data['temperature'])) {
            $errors[] = 'temperature deve essere numerico';
        }
        if (isset($data['max_tokens']) && !is_numeric($data['max_tokens'])) {
            $errors[] = 'max_tokens deve essere numerico (int)';
        }
        if (isset($data['timeout_seconds']) && !is_numeric($data['timeout_seconds'])) {
            $errors[] = 'timeout_seconds deve essere numerico (int)';
        }
        if (isset($data['max_retries']) && !is_numeric($data['max_retries'])) {
            $errors[] = 'max_retries deve essere numerico (int)';
        }

        // Tools deve essere lista di stringhe se dichiarato
        if (isset($data['tools'])) {
            if (!is_array($data['tools'])) {
                $errors[] = 'tools deve essere una lista (array YAML)';
            } else {
                foreach ($data['tools'] as $i => $tool) {
                    if (!is_string($tool) || trim($tool) === '') {
                        $errors[] = "tools[{$i}] deve essere una stringa non vuota";
                    }
                }
            }
        }

        // Prompt file: opzionale nel file (default `prompt.md`), ma se
        // dichiarato deve essere un nome relativo (no path absolute, no
        // `..` traversal) e il file deve esistere nella cartella agente.
        $promptRel = is_string($data['prompt'] ?? null) ? trim($data['prompt']) : 'prompt.md';

        if ($promptRel === '') {
            $errors[] = 'Campo prompt non può essere stringa vuota (default: prompt.md)';
        } elseif (str_contains($promptRel, '..') || str_starts_with($promptRel, '/')) {
            $errors[] = 'Campo prompt deve essere un path relativo dentro la cartella agente (no `..`, no path assoluti): '.$promptRel;
        } elseif ($agentDir !== '' && !is_file(rtrim($agentDir, '/').'/'.$promptRel)) {
            $errors[] = 'File prompt mancante: '.$promptRel.' (cercato in '.$agentDir.')';
        }

        // Provider whitelist soft: log-only, accettiamo provider arbitrari
        // per non bloccare estensioni future; ma flag i palesemente sbagliati.
        if (isset($data['provider']) && !is_string($data['provider'])) {
            $errors[] = 'provider deve essere una stringa';
        }

        return $errors;
    }
}
