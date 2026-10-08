<?php

namespace Wonder\Docs;

use RuntimeException;

/**
 * Il catalogo dei componenti documentati.
 *
 * Legge `docs/components/<categoria>/*.php` del pacchetto (e le cartelle
 * aggiunte con `addPath()`, per siti e moduli): ogni file ritorna un
 * `ComponentDoc`. La categoria, se la scheda non la dichiara, è il nome della
 * cartella. Gli slug devono essere unici: due schede con lo stesso slug
 * fermano il caricamento con un errore che nomina i file.
 */
final class Catalog
{
    /** @var array<string, ComponentDoc>|null */
    private static ?array $docs = null;

    /** @var string[] */
    private static array $extraPaths = [];

    /** @var array<string, Category>|null */
    private static ?array $categories = null;

    public static function packagePath(): string
    {
        return dirname(__DIR__, 2).'/docs/components';
    }

    /** @return string[] */
    public static function paths(): array
    {
        return array_values(array_unique(array_filter(
            array_merge([self::packagePath()], self::$extraPaths),
            static fn (string $path): bool => is_dir($path)
        )));
    }

    /** Una cartella in più con lo stesso formato (schede di un sito o di un modulo). */
    public static function addPath(string $path): void
    {
        $path = rtrim(trim($path), '/');

        if ($path !== '' && !in_array($path, self::$extraPaths, true)) {
            self::$extraPaths[] = $path;
            self::$docs = null;
        }
    }

    /** @return array<string, Category> */
    public static function categories(): array
    {
        if (self::$categories === null) {
            self::$categories = [];

            foreach (self::defaultCategories() as $category) {
                self::$categories[$category->key] = $category;
            }
        }

        $categories = self::$categories;
        uasort($categories, static fn (Category $a, Category $b): int => [$a->order, $a->title] <=> [$b->order, $b->title]);

        return $categories;
    }

    public static function category(string $key): ?Category
    {
        return self::categories()[strtolower(trim($key))] ?? null;
    }

    public static function addCategory(Category $category): void
    {
        self::categories();
        self::$categories[$category->key] = $category;
    }

    /** @return array<string, ComponentDoc> slug => scheda, nell'ordine del catalogo */
    public static function all(): array
    {
        if (self::$docs === null) {
            self::$docs = self::load();
        }

        return self::$docs;
    }

    public static function has(string $slug): bool
    {
        return isset(self::all()[strtolower(trim($slug))]);
    }

    public static function find(string $slug): ?ComponentDoc
    {
        return self::all()[strtolower(trim($slug))] ?? null;
    }

    /** La scheda che documenta una classe Element. */
    public static function findByClass(string $class): ?ComponentDoc
    {
        $class = ltrim($class, '\\');

        foreach (self::all() as $doc) {
            if ($doc->getClass() === $class) {
                return $doc;
            }
        }

        return null;
    }

    /** @return ComponentDoc[] */
    public static function byCategory(string $key): array
    {
        $key = strtolower(trim($key));

        return array_values(array_filter(
            self::all(),
            static fn (ComponentDoc $doc): bool => $doc->getCategory() === $key
        ));
    }

    /**
     * Le schede per categoria e gruppo: `[categoria => [gruppo => schede]]`.
     * Il gruppo `''` raccoglie le schede senza gruppo e viene per primo.
     *
     * @return array<string, array<string, ComponentDoc[]>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::categories() as $category) {
            $docs = self::byCategory($category->key);

            if ($docs === []) {
                continue;
            }

            $groups = [];

            foreach ($docs as $doc) {
                $groups[$doc->getGroup()][] = $doc;
            }

            uksort($groups, static function (string $a, string $b) use ($category): int {
                if ($a === '' || $b === '') {
                    return $a === '' ? -1 : 1;
                }

                return [$category->groupOrder($a), $a] <=> [$category->groupOrder($b), $b];
            });

            $grouped[$category->key] = $groups;
        }

        return $grouped;
    }

    /**
     * La scheda prima e dopo nell'ordine del catalogo, per la navigazione.
     *
     * @return array{previous: ?ComponentDoc, next: ?ComponentDoc}
     */
    public static function neighbors(ComponentDoc $doc): array
    {
        $slugs = array_keys(self::all());
        $position = array_search($doc->getSlug(), $slugs, true);

        if ($position === false) {
            return ['previous' => null, 'next' => null];
        }

        return [
            'previous' => $position > 0 ? self::all()[$slugs[$position - 1]] : null,
            'next' => isset($slugs[$position + 1]) ? self::all()[$slugs[$position + 1]] : null,
        ];
    }

    /** Dimentica le schede caricate (test, o dopo `addPath()`). */
    public static function reset(): void
    {
        self::$docs = null;
        self::$extraPaths = [];
        self::$categories = null;
    }

    /** @return array<string, ComponentDoc> */
    private static function load(): array
    {
        $docs = [];
        $files = [];

        foreach (self::paths() as $root) {
            foreach (glob($root.'/*.php') ?: [] as $file) {
                $files[] = ['file' => $file, 'category' => ''];
            }

            foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
                $category = strtolower(basename($directory));

                foreach (glob($directory.'/*.php') ?: [] as $file) {
                    $files[] = ['file' => $file, 'category' => $category];
                }
            }
        }

        foreach ($files as $entry) {
            $doc = self::loadFile($entry['file']);

            if ($doc->getCategory() === '') {
                $doc->category($entry['category']);
            }

            if ($doc->getCategory() === '') {
                throw new RuntimeException("La scheda {$entry['file']} non ha una categoria: mettila in una sottocartella o dichiara category().");
            }

            if (self::category($doc->getCategory()) === null) {
                throw new RuntimeException("La scheda {$entry['file']} usa la categoria '{$doc->getCategory()}' che non esiste. Categorie: ".implode(', ', array_keys(self::categories())).'.');
            }

            $slug = $doc->getSlug();

            if (isset($docs[$slug])) {
                throw new RuntimeException(
                    "Slug '{$slug}' duplicato: {$docs[$slug]->getFile()} e {$entry['file']}. Dichiara slug() in una delle due schede."
                );
            }

            $docs[$slug] = $doc->file($entry['file']);
        }

        uasort($docs, static function (ComponentDoc $a, ComponentDoc $b): int {
            $categoryA = self::category($a->getCategory());
            $categoryB = self::category($b->getCategory());

            return [
                $categoryA?->order ?? PHP_INT_MAX,
                $a->getCategory(),
                $a->getGroup() === '' ? -1 : ($categoryA?->groupOrder($a->getGroup()) ?? PHP_INT_MAX),
                $a->getGroup(),
                $a->getOrder(),
                $a->getTitle(),
            ] <=> [
                $categoryB?->order ?? PHP_INT_MAX,
                $b->getCategory(),
                $b->getGroup() === '' ? -1 : ($categoryB?->groupOrder($b->getGroup()) ?? PHP_INT_MAX),
                $b->getGroup(),
                $b->getOrder(),
                $b->getTitle(),
            ];
        });

        return $docs;
    }

    private static function loadFile(string $file): ComponentDoc
    {
        $doc = (static function (string $__file): mixed {
            return require $__file;
        })($file);

        if (!$doc instanceof ComponentDoc) {
            throw new RuntimeException("Il file {$file} deve ritornare un ".ComponentDoc::class.'.');
        }

        return $doc;
    }

    /** @return Category[] */
    private static function defaultCategories(): array
    {
        return [
            new Category('form', 'Form', 'Campi e controlli dei form: testo e numeri, scelte, date, file e azioni. Sono gli Element che `FormField` costruisce sotto il cofano.', 10, 'bi-input-cursor-text', [
                'structure' => 'Struttura',
                'text' => 'Testo e numeri',
                'choice' => 'Scelte',
                'date' => 'Date e orari',
                'file' => 'File',
                'action' => 'Azioni',
                'advanced' => 'Avanzati',
            ]),
            new Category('components', 'Componenti', 'I blocchi con cui si compongono pagine e layout: bottoni, badge, card, avvisi, finestre, testi.', 20, 'bi-puzzle', [
                'action' => 'Azioni',
                'feedback' => 'Feedback',
                'layout' => 'Layout',
                'content' => 'Contenuto',
                'choice' => 'Scelte e percorsi',
                'docs' => 'Documentazione',
            ]),
            new Category('media', 'Media', 'Immagini responsive, video, iframe, gallery, caroselli e mappe.', 30, 'bi-image'),
            new Category('charts', 'Grafici', 'Grafici Chart.js dichiarati in PHP, con dataset e opzioni.', 40, 'bi-bar-chart'),
        ];
    }
}
