<?php

namespace Wonder\Localization\Catalog;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Wonder\App\LegacyGlobals;
use Wonder\Localization\LanguageContext;

/**
 * I testi dei JSON di `lang/`: l'id è la chiave di `__t()`, il contesto è il
 * file che la dichiara. Legge core, moduli e sito nello stesso ordine di
 * `TranslationProvider` senza toccarne il singleton; scrive solo nei JSON
 * del sito. Una lista si scrive sempre intera, perché `__t()` cerca la
 * chiave completa nella lingua corrente e non completa una lista a metà.
 */
class JsonTranslationSource implements TranslationSource
{
    /** File che non sono testi da tradurre: gli url hanno la loro gestione, i legali stanno in `legal_documents`. */
    public const EXCLUDED = ['urls', 'legal'];

    /** @var array<string, array{tree: array, context: array<string, string>}> */
    private array $cache = [];

    /**
     * @param list<string>|null $paths cartelle base, ciascuna con una sottocartella per lingua
     */
    public function __construct(
        private readonly ?array $paths = null,
        private readonly ?string $writePath = null,
    ) {
    }

    public function key(): string
    {
        return 'json';
    }

    public function label(): string
    {
        return 'Testi del sito';
    }

    public function entries(string $source, string $target): array
    {
        $sourceTree = $this->load($source);
        $targetLeaves = $this->leaves($this->load($target)['tree']);
        $entries = [];

        foreach ($this->leaves($sourceTree['tree']) as $id => [$text, $group]) {
            $translation = $targetLeaves[$id][0] ?? '';

            $entries[$id] = new Entry(
                $id,
                $sourceTree['context'][$id] ?? '',
                $text,
                $translation,
                $group,
            );
        }

        return $entries;
    }

    public function apply(string $source, string $target, array $changes): array
    {
        $entries = $this->entries($source, $target);
        $sourceTree = $this->load($source)['tree'];
        $targetTree = $this->load($target)['tree'];
        $errors = [];
        $files = [];

        foreach ($changes as $id => $value) {
            $entry = $entries[$id] ?? null;

            if ($entry === null || $entry->context === '') {
                $errors[$id] = 'Chiave inesistente.';
                continue;
            }

            $prefix = str_replace('/', '.', $entry->context);
            $files[$entry->context] ??= $this->readFile($target, $entry->context);

            if ($entry->group === null) {
                self::set($files[$entry->context], substr($id, strlen($prefix) + 1), $value);
                continue;
            }

            $list = array_replace_recursive(
                (array) self::get($sourceTree, $entry->group),
                (array) self::get($targetTree, $entry->group),
            );

            foreach ($changes as $otherId => $otherValue) {
                if (($entries[$otherId] ?? null)?->group === $entry->group) {
                    self::set($list, substr($otherId, strlen($entry->group) + 1), $otherValue);
                }
            }

            if ($entry->group === $prefix) {
                $files[$entry->context] = self::normalize($list);
            } else {
                self::set($files[$entry->context], substr($entry->group, strlen($prefix) + 1), self::normalize($list));
            }
        }

        foreach ($files as $context => $data) {
            $this->writeFile($target, $context, $data);
        }

        $this->cache = [];

        return $errors;
    }

    /** @return array{tree: array, context: array<string, string>} */
    private function load(string $lang): array
    {
        if (isset($this->cache[$lang])) {
            return $this->cache[$lang];
        }

        $tree = [];
        $context = [];

        foreach ($this->paths ?? LanguageContext::getPathFiles() as $basePath) {
            $path = rtrim($basePath, '/')."/{$lang}/";

            if (!is_dir($path)) {
                continue;
            }

            $files = [];

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && $file->getExtension() === 'json') {
                    $files[] = $file->getPathname();
                }
            }

            usort($files, static fn (string $a, string $b): int => filemtime($a) <=> filemtime($b));

            foreach ($files as $filePath) {
                $relative = preg_replace('/\.json$/', '', substr($filePath, strlen($path)));
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', (string) $relative);

                if (in_array($relative, self::EXCLUDED, true)) {
                    continue;
                }

                $content = json_decode((string) file_get_contents($filePath), true);

                if (!is_array($content)) {
                    continue;
                }

                $prefix = str_replace('/', '.', $relative);
                $current = (array) self::get($tree, $prefix);
                self::set($tree, $prefix, self::mergeDeclared($current, $content));

                foreach (array_keys($this->leaves($content, $prefix)) as $id) {
                    $context[$id] = $relative;
                }
            }
        }

        return $this->cache[$lang] = ['tree' => $tree, 'context' => $context];
    }

    /**
     * Foglie di testo, con il gruppo: la lista più esterna che le contiene.
     *
     * @return array<string, array{0: string, 1: ?string}>
     */
    private function leaves(array $tree, string $prefix = '', ?string $group = null): array
    {
        $leaves = [];

        if ($group === null && $prefix !== '' && $tree !== [] && array_is_list($tree)) {
            $group = $prefix;
        }

        foreach ($tree as $key => $value) {
            $id = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $leaves += $this->leaves($value, $id, $group);
            } elseif (is_string($value)) {
                $leaves[$id] = [$value, $group];
            }
        }

        return $leaves;
    }

    private function readFile(string $lang, string $context): array
    {
        $file = $this->filePath($lang, $context);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return is_array($data) ? $data : [];
    }

    private function writeFile(string $lang, string $context, array $data): void
    {
        $file = $this->filePath($lang, $context);

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }

    private function filePath(string $lang, string $context): string
    {
        $base = $this->writePath ?? rtrim((string) LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : ''), '/').'/lang';

        return rtrim($base, '/')."/{$lang}/{$context}.json";
    }

    private static function mergeDeclared(array $ref, array $content): array
    {
        foreach ($content as $key => $value) {
            $ref[$key] = is_array($value) && isset($ref[$key]) && is_array($ref[$key])
                ? self::mergeDeclared($ref[$key], $value)
                : $value;
        }

        return $ref;
    }

    private static function get(array $tree, string $path): mixed
    {
        $current = $tree;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    private static function set(array &$tree, string $path, mixed $value): void
    {
        $ref = &$tree;

        foreach (explode('.', $path) as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        $ref = $value;
    }

    /** Indici di una lista in ordine, così il JSON resta un array. */
    private static function normalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::normalize($item);
            }
        }

        if ($value !== [] && array_filter(array_keys($value), 'is_int') === array_keys($value)) {
            ksort($value);
        }

        return $value;
    }
}
