<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;

/**
 * Costruisce la navigation del backend interamente a partire dalle
 * Resource registrate in `ResourceRegistry`.
 *
 * Nessuna sezione è hardcoded qui: il catalogo
 * `BackendNavigationSections` viene popolato a runtime dalle
 * dichiarazioni `NavigationSchema::section(key, title, icon, order)`
 * che le Resource fanno nel proprio `navigationSchema()`.
 *
 * Algoritmo in due passi:
 *
 *  1. **Pass 1 (collect)**: per ogni Resource, invoca
 *     `navigationSchema()`. Ogni chiamata a `section()` registra
 *     la sezione nel registry. Quando il Pass 1 è completo, il
 *     catalogo è completamente popolato.
 *
 *  2. **Pass 2 (build)**: ri-itera le Resource e costruisce sezioni
 *     + subnav usando `resolveSection()` per ottenere i metadati
 *     della sezione dal registry. Valida che ogni `inSection(key)`
 *     referenzi una sezione effettivamente dichiarata.
 *
 * Output: array `[section, …]` ordinato per `__section_order`,
 * pronto per il rendering del menu.
 */
final class BackendNavigation
{
    public static function all(): array
    {
        return self::buildNavigation();
    }

    /**
     * Variant: prende un array di sezioni "esterne" (es. da plugin
     * Module) e le unisce alle sezioni delle Resource. Le sezioni
     * esterne devono già avere `__section_order` settato.
     */
    public static function merge(array $extraSections): array
    {
        $navigation = self::buildNavigation();

        foreach ($extraSections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $index = self::findSectionIndex($navigation, $section);

            if ($index === null) {
                $navigation[] = $section;
                continue;
            }

            $navigation[$index] = self::mergeSection($navigation[$index], $section);
        }

        return self::sortAndCleanup($navigation);
    }

    private static function buildNavigation(): array
    {
        $schemas = self::collectSchemas();
        $sections = self::buildSections($schemas);

        return self::sortAndCleanup($sections);
    }

    /**
     * Pass 1: invoca `navigationSchema()` su ogni Resource per
     * triggerare le registrazioni nel
     * `BackendNavigationSections` registry. Cache delle schema
     * istanziate per evitare di ricostruirle nel Pass 2.
     *
     * @return array<class-string, \Wonder\App\ResourceSchema\NavigationSchema>
     */
    private static function collectSchemas(): array
    {
        $schemas = [];

        foreach (ResourceRegistry::all() as $resourceClass) {
            if (!is_subclass_of($resourceClass, Resource::class)) {
                continue;
            }

            # L'invocazione di `navigationSchema()` ha come side
            # effect le chiamate a `section()` che popolano il registry.
            $schemas[$resourceClass] = $resourceClass::navigationSchema();
        }

        return $schemas;
    }

    /**
     * Pass 2: costruisce l'array di sezioni a partire dalle schema
     * cached + registry (ora completo).
     */
    private static function buildSections(array $schemas): array
    {
        $sections = [];

        foreach ($schemas as $resourceClass => $navigationSchema) {
            $schema = $navigationSchema->all();

            if (empty($schema['enabled'])) {
                continue;
            }

            $subnav = [
                'title' => (string) ($schema['title'] ?? $resourceClass::titleLabel()),
                'folder' => $resourceClass::path(),
                'file' => self::normalizeFile((string) ($schema['file'] ?? '')),
                'authority' => self::normalizeAuthority((array) ($schema['authority'] ?? [])),
                '__resource_order' => (int) ($schema['order'] ?? 100),
            ];

            # Validazione esplicita: se la Resource fa inSection('foo')
            # ma nessuna ha dichiarato 'foo' con section(), errore.
            $sectionKey = $schema['section_key'] ?? null;

            if (is_string($sectionKey) && $sectionKey !== '' && !BackendNavigationSections::has($sectionKey)) {
                throw new RuntimeException(
                    "Resource {$resourceClass} referenzia la sezione '{$sectionKey}' "
                    ."ma nessuna Resource l'ha dichiarata. "
                    ."Una Resource deve chiamare section('{$sectionKey}', \$title, \$icon, \$order) "
                    ."per dichiararla."
                );
            }

            $resolvedSection = $navigationSchema->resolveSection();

            # Standalone (no section): la Resource diventa una sezione
            # top-level a sé. Order = section_order override o 500.
            if ($resolvedSection === null) {
                $sections['resource:'.$resourceClass] = [
                    'title' => $subnav['title'],
                    'folder' => $subnav['folder'],
                    'icon' => (string) ($resourceClass::icon() ?: 'bi-bug'),
                    'file' => $subnav['file'],
                    'authority' => $subnav['authority'],
                    'subnavs' => [],
                    '__section_order' => is_int($schema['section_order'] ?? null)
                        ? (int) $schema['section_order']
                        : 500,
                ];
                continue;
            }

            $sectionFolder = $resolvedSection['folder'];

            if (!isset($sections[$sectionFolder])) {
                $sections[$sectionFolder] = [
                    'title' => $resolvedSection['title'],
                    'folder' => $sectionFolder,
                    'icon' => $resolvedSection['icon'] !== ''
                        ? $resolvedSection['icon']
                        : (string) $resourceClass::icon(),
                    'authority' => self::normalizeAuthority($resolvedSection['authority']),
                    'subnavs' => [],
                    '__section_order' => $resolvedSection['order'],
                ];
            }

            $sections[$sectionFolder]['authority'] = self::mergeAuthority(
                (array) ($sections[$sectionFolder]['authority'] ?? []),
                (array) ($schema['authority'] ?? [])
            );

            $sections[$sectionFolder]['subnavs'] = self::mergeSubnavs(
                (array) ($sections[$sectionFolder]['subnavs'] ?? []),
                $subnav
            );
        }

        return $sections;
    }

    /**
     * Sort top-level + cleanup degli internal markers (`__section_order`,
     * `__resource_order` nelle subnav).
     */
    private static function sortAndCleanup(array $navigation): array
    {
        # Sort stabile (PHP 8+): a parità di __section_order si rispetta
        # l'ordine di inserimento.
        usort($navigation, static function ($left, $right): int {
            if (!is_array($left) || !is_array($right)) {
                return 0;
            }

            return ((int) ($left['__section_order'] ?? 500))
                <=> ((int) ($right['__section_order'] ?? 500));
        });

        foreach ($navigation as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            unset($navigation[$index]['__section_order']);

            if (isset($item['subnavs']) && is_array($item['subnavs'])) {
                $navigation[$index]['subnavs'] = self::sortSubnavs($item['subnavs']);
            }
        }

        return array_values($navigation);
    }

    private static function findSectionIndex(array $navigation, array $section): ?int
    {
        foreach ($navigation as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $folderMatches = (($item['folder'] ?? null) === ($section['folder'] ?? null));
            $titleMatches = (($item['title'] ?? null) === ($section['title'] ?? null));

            if ($folderMatches || $titleMatches) {
                return $index;
            }
        }

        return null;
    }

    private static function mergeSection(array $base, array $other): array
    {
        $base['title'] = (string) ($base['title'] ?? $other['title'] ?? '');
        $base['folder'] = (string) ($base['folder'] ?? $other['folder'] ?? '');
        $base['icon'] = (string) ($base['icon'] ?? $other['icon'] ?? 'bi-bug');
        $base['file'] = (string) ($base['file'] ?? $other['file'] ?? '');
        $base['authority'] = self::mergeAuthority(
            (array) ($base['authority'] ?? []),
            (array) ($other['authority'] ?? [])
        );
        $base['subnavs'] = self::mergeSubnavs(
            (array) ($base['subnavs'] ?? []),
            ...(array) ($other['subnavs'] ?? [])
        );
        $base['__section_order'] = (int) (
            $base['__section_order']
            ?? $other['__section_order']
            ?? 500
        );

        return $base;
    }

    /**
     * Append/merge di nuovi subnav alla lista. Non rimuove
     * `__resource_order` perché questa funzione è chiamata più volte
     * (una per Resource); l'ordinamento e la rimozione del marker
     * avvengono al termine in `sortSubnavs()` chiamato da
     * `sortAndCleanup()`.
     */
    private static function mergeSubnavs(array $baseSubnavs, array ...$incomingSubnavs): array
    {
        foreach ($incomingSubnavs as $subnav) {
            $merged = false;

            foreach ($baseSubnavs as $index => $existing) {
                if (!is_array($existing)) {
                    continue;
                }

                $sameFolder = (($existing['folder'] ?? null) === ($subnav['folder'] ?? null));
                $sameTitle = self::normalizeTitle((string) ($existing['title'] ?? ''))
                    === self::normalizeTitle((string) ($subnav['title'] ?? ''));

                if (!$sameFolder && !$sameTitle) {
                    continue;
                }

                $baseSubnavs[$index] = array_merge($existing, $subnav);
                $baseSubnavs[$index]['authority'] = self::mergeAuthority(
                    (array) ($existing['authority'] ?? []),
                    (array) ($subnav['authority'] ?? [])
                );
                $merged = true;
                break;
            }

            if (!$merged) {
                $baseSubnavs[] = $subnav;
            }
        }

        return $baseSubnavs;
    }

    /**
     * Ordinamento finale delle subnav di una sezione (stabile via
     * `__resource_order`) e rimozione del marker. Chiamato una volta
     * sola per sezione, dopo che tutte le Resource sono state
     * processate.
     */
    private static function sortSubnavs(array $subnavs): array
    {
        usort($subnavs, static function (array $left, array $right): int {
            return ((int) ($left['__resource_order'] ?? 1000))
                <=> ((int) ($right['__resource_order'] ?? 1000));
        });

        foreach ($subnavs as $index => $subnav) {
            unset($subnavs[$index]['__resource_order']);
        }

        return array_values($subnavs);
    }

    private static function mergeAuthority(array $base, array $incoming): array
    {
        return self::normalizeAuthority(array_merge($base, $incoming));
    }

    private static function normalizeAuthority(array $authority): array
    {
        $authority = array_filter($authority, 'is_string');
        $authority = array_map(static fn (string $value): string => trim($value), $authority);
        $authority = array_filter($authority, static fn (string $value): bool => $value !== '');

        return array_values(array_unique($authority));
    }

    private static function normalizeFile(string $file): string
    {
        $file = trim($file);

        return in_array($file, ['', 'list', 'list.php'], true) ? '' : $file;
    }

    private static function normalizeTitle(string $title): string
    {
        return mb_strtolower(trim($title));
    }
}
