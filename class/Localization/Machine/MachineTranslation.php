<?php

namespace Wonder\Localization\Machine;

use Wonder\App\Models\Localization\TranslationTerm;
use Wonder\Localization\Content\TranslationStore;

/**
 * Pipeline della traduzione automatica: composizione locale dal vocabolario,
 * protezione dei termini (`translate="no"`), memoria, poi il motore. Solo le
 * stringhe che non si risolvono in locale né in memoria arrivano al motore,
 * in un'unica chiamata per lingua. Senza motore quelle stringhe tornano con
 * `value` ed `engine` null e restano da tradurre.
 */
class MachineTranslation
{
    public const LOCAL = 'local';
    public const MEMORY = 'memory';

    private const MARK_OPEN = '<span translate="no" data-wi-mt>';
    private const SEPARATORS = '/(\s*[,;\/|\n]+\s*)/u';

    /** @var array<string, array{glossary: array<string, string>, keep: array<string, string>}> */
    private array $vocabulary = [];

    public function __construct(
        private readonly TranslationStore $store,
        private readonly ?Translator $translator = null
    ) {}

    /**
     * @param list<string> $texts
     * @return list<array{value: ?string, engine: ?string}>
     */
    public function translate(array $texts, string $from, string $to): array
    {
        $texts = array_values($texts);
        $result = [];
        $missing = [];

        foreach ($texts as $index => $text) {
            if (trim($text) === '') {
                $result[$index] = ['value' => $text, 'engine' => self::LOCAL];
                continue;
            }

            $composed = $this->compose($text, $to);

            if ($composed !== null) {
                $result[$index] = ['value' => $composed, 'engine' => self::LOCAL];
                continue;
            }

            $protected = $this->protect($text, $to);
            $hash = hash('sha256', "{$from}|{$to}|{$protected}");
            $remembered = $this->store->memory($hash);

            if ($remembered !== null) {
                $result[$index] = ['value' => $remembered, 'engine' => self::MEMORY];
                continue;
            }

            $missing[$hash] ??= ['protected' => $protected, 'html' => self::isHtml($text), 'indexes' => []];
            $missing[$hash]['indexes'][] = $index;
        }

        if ($missing !== [] && $this->translator === null) {
            foreach ($missing as $item) {
                foreach ($item['indexes'] as $index) {
                    $result[$index] = ['value' => null, 'engine' => null];
                }
            }
        } elseif ($missing !== []) {
            $translated = $this->translator->translate(array_column($missing, 'protected'), $from, $to);
            $engine = $this->translator->key();

            foreach (array_values($missing) as $position => $item) {
                $value = $this->unprotect((string) ($translated[$position] ?? ''), $item['html']);
                $hash = array_keys($missing)[$position];
                $this->store->remember($hash, $from, $to, $item['protected'], $value, $engine);

                foreach ($item['indexes'] as $index) {
                    $result[$index] = ['value' => $value, 'engine' => $engine];
                }
            }
        }

        ksort($result);

        return array_values($result);
    }

    /** Traduzione già in memoria, senza chiamare il motore. */
    public function remembered(string $text, string $from, string $to): ?string
    {
        return $this->store->memory(hash('sha256', "{$from}|{$to}|".$this->protect($text, $to)));
    }

    /**
     * Segmenti del testo (separati da `, ; / |` e a capo) che il vocabolario
     * non copre: codici, troppo lunghi o con più di 4 parole restano fuori.
     *
     * @return list<string>
     */
    public function uncovered(string $text, string $to): array
    {
        $plain = self::isHtml($text) ? html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $text;
        $vocabulary = $this->vocabulary($to);
        $result = [];

        foreach (preg_split(self::SEPARATORS, $plain) ?: [] as $segment) {
            $segment = trim($segment);
            $lower = mb_strtolower($segment);
            $length = mb_strlen($segment);

            if ($length < 2 || $length > 60 || self::isCode($segment) || count(preg_split('/\s+/u', $segment)) > 4
                || isset($vocabulary['glossary'][$lower]) || isset($vocabulary['keep'][$lower])) {
                continue;
            }

            $result[] = $segment;
        }

        return $result;
    }

    public static function isHtml(string $text): bool
    {
        return preg_match('/<[a-z!\/][^>]*>/i', $text) === 1;
    }

    /** Un segmento con cifre o senza lettere è un codice: non si traduce. */
    public static function isCode(string $segment): bool
    {
        return preg_match('/\p{N}/u', $segment) === 1 || preg_match('/\p{L}/u', $segment) !== 1;
    }

    /** Il testo semplice fatto solo di termini noti e codici si compone senza motore. */
    private function compose(string $text, string $to): ?string
    {
        if (self::isHtml($text)) {
            return null;
        }

        $vocabulary = $this->vocabulary($to);
        $parts = preg_split(self::SEPARATORS, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        foreach ($parts as $position => $part) {
            if ($position % 2 === 1) {
                continue;
            }

            $segment = trim($part);
            $lower = mb_strtolower($segment);

            if ($segment === '' || self::isCode($segment) || isset($vocabulary['keep'][$lower])) {
                continue;
            }

            if (!isset($vocabulary['glossary'][$lower])) {
                return null;
            }

            $parts[$position] = str_replace($segment, $vocabulary['glossary'][$lower], $part);
        }

        return implode('', $parts);
    }

    private function protect(string $text, string $to): string
    {
        $html = self::isHtml($text);
        $source = $html ? $text : nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        $pattern = $this->pattern($to);
        $vocabulary = $this->vocabulary($to);
        $parts = preg_split('/(<[^>]+>)/', $source, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        foreach ($parts as $position => $part) {
            if ($position % 2 === 1 || $part === '') {
                continue;
            }

            $parts[$position] = preg_replace_callback($pattern, static function (array $match) use ($vocabulary): string {
                $lower = mb_strtolower(html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $value = isset($vocabulary['glossary'][$lower])
                    ? htmlspecialchars($vocabulary['glossary'][$lower], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    : $match[0];

                return self::MARK_OPEN.$value.'</span>';
            }, $part) ?? $part;
        }

        return implode('', $parts);
    }

    private function unprotect(string $value, bool $html): string
    {
        $value = preg_replace('/<span\s+translate="no"\s+data-wi-mt(?:="")?\s*>(.*?)<\/span>/su', '$1', $value) ?? $value;

        if ($html) {
            return $value;
        }

        $value = preg_replace('/\s*<br\s*\/?>\s*/i', "\n", $value) ?? $value;

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Segnaposto `{{nome}}` e termini del vocabolario, i più lunghi per primi. */
    private function pattern(string $to): string
    {
        $vocabulary = $this->vocabulary($to);
        $terms = array_merge(array_keys($vocabulary['glossary']), array_keys($vocabulary['keep']));
        usort($terms, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $alternatives = ['\{\{[^{}]+\}\}'];

        foreach ($terms as $term) {
            $alternatives[] = '(?<![\p{L}\p{N}])'.preg_quote(htmlspecialchars($term, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '/').'(?![\p{L}\p{N}])';
        }

        return '/'.implode('|', $alternatives).'/iu';
    }

    /**
     * Termini confermati: i `keep` restano come sono, i `glossary` valgono
     * solo con una traduzione nella lingua di destinazione.
     *
     * @return array{glossary: array<string, string>, keep: array<string, string>}
     */
    private function vocabulary(string $to): array
    {
        if (isset($this->vocabulary[$to])) {
            return $this->vocabulary[$to];
        }

        $terms = $this->store->terms(TranslationTerm::CONFIRMED);
        $glossaryIds = [];
        $vocabulary = ['glossary' => [], 'keep' => []];

        foreach ($terms as $term) {
            if ($term['mode'] === TranslationTerm::KEEP) {
                $vocabulary['keep'][mb_strtolower((string) $term['term'])] = (string) $term['term'];
            } else {
                $glossaryIds[(int) $term['id']] = (string) $term['term'];
            }
        }

        $translations = $glossaryIds === [] ? [] : $this->store->translations(TranslationTerm::$table, array_keys($glossaryIds), $to);

        foreach ($glossaryIds as $id => $term) {
            $value = trim((string) ($translations[$id]['term']['value'] ?? ''));

            if ($value !== '') {
                $vocabulary['glossary'][mb_strtolower($term)] = $value;
            }
        }

        return $this->vocabulary[$to] = $vocabulary;
    }
}
