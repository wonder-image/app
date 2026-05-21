<?php

namespace Wonder\AI;

use RuntimeException;

/**
 * Risolve un singolo agente applicando la **cascade file-level** sui 3
 * layer (framework → moduli → consumer).
 *
 * Idea: ogni FILE che compone un agente (`agent.yml`, `prompt.md`,
 * eventualmente `tools.yml`, `schema.yml`) viene cercato separatamente
 * partendo dalla priority massima. Il primo trovato vince.
 *
 * Conseguenza pratica: il consumer può overridare SOLO il prompt
 * (mette `prompt.md` in `<root>/ai/agents/<slug>/`) lasciando ereditare
 * `agent.yml` dal framework. Stesso meccanismo per ogni altro file.
 *
 * Differenza rispetto al pattern Resources/Models: lì lo override è
 * a livello di CLASSE intera. Qui è a livello di FILE singolo dentro
 * la cartella agente. Più granulare, perché un agente è naturalmente
 * scomponibile (config + prompt + tools list).
 */
final class AgentResolver
{
    /**
     * Nomi canonici dei file che compongono un agente. L'ordine è
     * irrilevante per la cascade, ma è bene tenerlo stabile per i
     * messaggi di debug.
     */
    private const CANONICAL_FILES = [
        'agent.yml',
        'prompt.md',
        'tools.yml',
        'schema.yml',
    ];

    /**
     * Risolve l'agente `$slug` dato un set di layer ordinati per priority
     * decrescente.
     *
     * @param string $slug Identificatore kebab-case (nome cartella).
     * @param array<int,array{path:string,priority:int,source:string}> $layers
     *        Layer in cui cercare. Path = directory base che contiene le
     *        cartelle degli agenti (es. `<root>/ai/agents`). `source` è
     *        un'etichetta usata per il tracking della provenienza.
     *
     * @throws RuntimeException Se `agent.yml` non esiste in NESSUN layer
     *                          (l'agente non esiste affatto).
     */
    public static function resolve(string $slug, array $layers): Agent
    {
        $slug = trim($slug);

        if ($slug === '') {
            throw new RuntimeException('Slug agente vuoto.');
        }

        // Ordina per priority desc (sicurezza: il caller dovrebbe già
        // averlo fatto, ma normalizziamo). Priority più alta vince.
        usort($layers, static function ($a, $b) {
            return ((int) ($b['priority'] ?? 0)) <=> ((int) ($a['priority'] ?? 0));
        });

        $resolvedFiles = [];   // filename → path assoluto
        $sources = [];         // filename → source layer (es. 'consumer')

        // Per ogni file canonico cerca nel primo layer che lo possiede.
        foreach (self::CANONICAL_FILES as $file) {
            foreach ($layers as $layer) {
                $candidate = rtrim((string) ($layer['path'] ?? ''), '/').'/'.$slug.'/'.$file;
                if (is_file($candidate)) {
                    $resolvedFiles[$file] = $candidate;
                    $sources[$file] = (string) ($layer['source'] ?? 'unknown');
                    break;
                }
            }
        }

        // agent.yml è obbligatorio. prompt.md è obbligatorio per definizione
        // ma se manca lasciamo il validator dare un errore più specifico
        // (così `status:agents` può ancora elencare l'agente come "invalid"
        // senza crashare la discovery).
        if (!isset($resolvedFiles['agent.yml'])) {
            throw new RuntimeException(
                'Agente "'.$slug.'" non trovato: nessun agent.yml in '
                .'framework/moduli/consumer. Layer cercati: '
                .implode(', ', array_map(static fn($l) => (string) ($l['source'] ?? '?'), $layers))
            );
        }

        // Parsing config con merge defaults globali + overrides per-slug.
        $rawConfig = YamlReader::parseFile($resolvedFiles['agent.yml']);
        if (!is_array($rawConfig)) {
            $rawConfig = [];
        }

        $globalDefaults = ConfigLoader::defaults();
        $config = AgentConfig::fromArray($rawConfig, $globalDefaults);

        // Override per-slug applicati dopo (consumer/ai/overrides.yml).
        $overrides = ConfigLoader::overridesFor($slug);
        if (!empty($overrides)) {
            $config = $config->mergedWith($overrides);
            $sources['__overrides'] = 'consumer:overrides.yml';
        }

        // Il prompt deve esistere (default `prompt.md` o quello specificato
        // in agent.yml). Se manca facciamo emergere subito l'errore
        // costruendo un path "atteso" che `Agent::prompt()` userà — meglio
        // un errore chiaro a runtime che un silenzioso bug.
        $promptFile = $config->promptFile;
        $promptPath = $resolvedFiles[$promptFile] ?? null;

        // Caso: il `prompt:` di agent.yml è custom (es. `prompt-it.md`).
        // CANONICAL_FILES cerca solo i nomi standard, quindi rifacciamo
        // la cascade ad hoc per quel filename.
        if ($promptPath === null && $promptFile !== 'prompt.md') {
            foreach ($layers as $layer) {
                $candidate = rtrim((string) ($layer['path'] ?? ''), '/').'/'.$slug.'/'.$promptFile;
                if (is_file($candidate)) {
                    $promptPath = $candidate;
                    $sources[$promptFile] = (string) ($layer['source'] ?? 'unknown');
                    break;
                }
            }
        }

        // Se proprio non c'è, lasciamo un path "atteso" (cartella dello
        // stesso layer di agent.yml) così `Agent::prompt()` darà un
        // errore localizzato sul file mancante invece che generico.
        if ($promptPath === null) {
            $promptPath = dirname($resolvedFiles['agent.yml']).'/'.$promptFile;
        }

        return new Agent(
            slug: $slug,
            config: $config,
            promptPath: $promptPath,
            sources: $sources,
        );
    }

    /**
     * Lista tutti gli slug agente presenti nei layer (unione, deduplicato).
     * Usato da `AgentRegistry::all()` per scoprire cosa esiste prima di
     * risolverlo singolarmente.
     *
     * @param array<int,array{path:string,priority:int,source:string}> $layers
     * @return array<int,string>
     */
    public static function discoverSlugs(array $layers): array
    {
        $slugs = [];

        foreach ($layers as $layer) {
            $path = (string) ($layer['path'] ?? '');
            if ($path === '' || !is_dir($path)) {
                continue;
            }

            foreach (glob(rtrim($path, '/').'/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $slug = basename($dir);
                // Filtra cartelle "speciali" (.gitkeep dir style, hidden)
                if ($slug === '' || str_starts_with($slug, '.') || str_starts_with($slug, '_')) {
                    continue;
                }
                $slugs[$slug] = true;
            }
        }

        $out = array_keys($slugs);
        sort($out);
        return $out;
    }
}
