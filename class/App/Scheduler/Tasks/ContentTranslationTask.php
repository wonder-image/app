<?php

namespace Wonder\App\Scheduler\Tasks;

use Wonder\App\Credentials;
use Wonder\App\Models\Localization\{Translation, TranslationTerm};
use Wonder\App\Scheduler\{AbstractTask, Context};
use Wonder\Localization\Catalog\ModelTranslationSource;
use Wonder\Localization\Content\{TranslationStore, Translations};
use Wonder\Localization\LanguageContext;
use Wonder\Localization\Machine\{GoogleTranslator, MachineTranslation, Translator};

/**
 * Traduce i campi dei Model `Translatable` nelle lingue attive. Le righe
 * `automatic` o mancanti si rifanno quando cambia il testo base; una
 * `reviewed` il cui testo base è cambiato passa a `review` e non si tocca.
 * Senza `GCP_API_KEY` lavorano solo memoria e composizione locale.
 *
 * Parametri: `budget` (testi per esecuzione) e `threshold` (ripetizioni
 * oltre le quali un segmento non coperto diventa un termine `candidate`).
 */
class ContentTranslationTask extends AbstractTask
{
    /** @param list<class-string>|null $models */
    public function __construct(
        private readonly ?Translator $translator = null,
        private readonly ?TranslationStore $store = null,
        private readonly ?array $models = null
    ) {
    }

    public function key(): string { return 'wonder.content-translation'; }
    public function label(): string { return 'Traduzione automatica dei contenuti'; }
    public function expression(): string { return '*/10 * * * *'; }
    public function enabled(): bool { return true; }
    public function defaultParameters(): array { return ['budget' => 200, 'threshold' => 3]; }

    public function validate(array $parameters): array
    {
        $unknown = array_diff(array_keys($parameters), ['budget', 'threshold']);

        if ($unknown !== []) {
            throw new \InvalidArgumentException('Parametri non ammessi: '.implode(', ', $unknown).'.');
        }

        $budget = filter_var($parameters['budget'] ?? 200, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2000]]);
        $threshold = filter_var($parameters['threshold'] ?? 3, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2, 'max_range' => 1000]]);

        if ($budget === false || $threshold === false) {
            throw new \InvalidArgumentException('budget va da 1 a 2000, threshold da 2 a 1000.');
        }

        return ['budget' => $budget, 'threshold' => $threshold];
    }

    public function run(Context $context): array
    {
        $parameters = $this->validate($context->parameters + $this->defaultParameters());
        $store = $this->store ?? Translations::store();
        $translator = $this->translator ?? $this->defaultTranslator();
        $machine = new MachineTranslation($store, $translator);
        $base = LanguageContext::getDefaultLang();
        $targets = array_values(array_diff(array_keys(LanguageContext::getLangs()), [$base]));
        $budget = $parameters['budget'];
        $stats = ['translated' => 0, 'local' => 0, 'memory' => 0, 'review' => 0, 'waiting' => 0, 'candidates' => 0];
        $segments = [];

        if ($translator === null) {
            $context->log('Chiave GCP_API_KEY assente: solo memoria e vocabolario.');
        }

        foreach ($this->models ?? ModelTranslationSource::translatableModels() as $model) {
            $table = (string) $model::$table;
            $softDelete = ModelTranslationSource::softDelete($model);

            foreach (array_keys($model::translatableFields()) as $field) {
                foreach ($targets as $target) {
                    if ($budget < 1) {
                        break 3;
                    }

                    $context->checkDeadline();
                    $rows = $store->pending($table, $field, $target, $budget, $softDelete);
                    $budget -= count($rows);
                    $todo = [];

                    foreach ($rows as $row) {
                        if ($row['status'] === Translation::REVIEWED) {
                            $store->save($table, $row['id'], $field, $target, ['value' => null, 'status' => Translation::REVIEW, 'source_hash' => $row['source_hash']]);
                            $stats['review']++;
                        } else {
                            $todo[] = $row;
                        }
                    }

                    if ($todo === []) {
                        continue;
                    }

                    $results = $machine->translate(array_column($todo, 'value'), $base, $target);

                    foreach ($todo as $index => $row) {
                        foreach (array_unique($machine->uncovered($row['value'], $target)) as $segment) {
                            $segments[mb_strtolower($segment)] ??= ['term' => $segment, 'count' => 0];
                            $segments[mb_strtolower($segment)]['count']++;
                        }

                        $result = $results[$index];

                        if ($result['value'] === null) {
                            $stats['waiting']++;
                            continue;
                        }

                        $store->save($table, $row['id'], $field, $target, [
                            'value' => $result['value'],
                            'status' => Translation::AUTOMATIC,
                            'source_hash' => Translations::hash($row['value']),
                            'engine' => $result['engine'],
                        ]);
                        $stats[match ($result['engine']) {
                            MachineTranslation::LOCAL => 'local',
                            MachineTranslation::MEMORY => 'memory',
                            default => 'translated',
                        }]++;
                    }
                }
            }
        }

        $stats['candidates'] = $this->proposeCandidates($store, $machine, $segments, $parameters['threshold'], $base, $targets);

        return $translator === null ? $stats + ['skipped' => 'missing_api_key'] : $stats;
    }

    /**
     * Segmenti ripetuti almeno `$threshold` volte diventano `candidate`, con
     * la traduzione della memoria se c'è. Un termine esistente (anche
     * `rejected`) non si ripropone: a un candidato si aggiornano le occorrenze.
     *
     * @param array<string, array{term: string, count: int}> $segments
     * @param list<string> $targets
     */
    private function proposeCandidates(TranslationStore $store, MachineTranslation $machine, array $segments, int $threshold, string $base, array $targets): int
    {
        $known = [];

        foreach ($store->terms() as $term) {
            $known[mb_strtolower((string) $term['term'])] = $term;
        }

        $proposed = 0;

        foreach ($segments as $lower => $segment) {
            if ($segment['count'] < $threshold) {
                continue;
            }

            if (isset($known[$lower])) {
                if ($known[$lower]['status'] === TranslationTerm::CANDIDATE && (int) $known[$lower]['occurrences'] < $segment['count']) {
                    $store->saveTerm((string) $known[$lower]['term'], [
                        'mode' => (string) $known[$lower]['mode'],
                        'status' => TranslationTerm::CANDIDATE,
                        'occurrences' => $segment['count'],
                    ]);
                }
                continue;
            }

            $id = $store->saveTerm($segment['term'], ['mode' => TranslationTerm::GLOSSARY, 'status' => TranslationTerm::CANDIDATE, 'occurrences' => $segment['count']]);
            $proposed++;

            foreach ($targets as $target) {
                $value = $machine->remembered($segment['term'], $base, $target);

                if ($value !== null) {
                    $store->save(TranslationTerm::$table, $id, 'term', $target, ['value' => $value, 'status' => Translation::AUTOMATIC, 'engine' => MachineTranslation::MEMORY]);
                }
            }
        }

        return $proposed;
    }

    private function defaultTranslator(): ?Translator
    {
        $key = trim((string) (Credentials::api()->gcp_api_key ?? ''));

        return $key === '' ? null : new GoogleTranslator($key);
    }
}
