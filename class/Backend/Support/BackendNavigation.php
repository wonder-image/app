<?php

namespace Wonder\Backend\Support;

use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;

final class BackendNavigation
{
    public static function merge(array $navigation): array
    {
        $resources = self::resourceSections();

        foreach ($resources as $sectionKey => $resourceSection) {
            $index = self::findSectionIndex($navigation, $resourceSection);

            if ($index === null) {
                $navigation[] = $resourceSection;
                continue;
            }

            $navigation[$index] = self::mergeSection($navigation[$index], $resourceSection);
        }

        return array_values($navigation);
    }

    private static function resourceSections(): array
    {
        $sections = [];

        foreach (ResourceRegistry::all() as $resourceClass) {
            if (!is_subclass_of($resourceClass, Resource::class)) {
                continue;
            }

            $schema = $resourceClass::navigationSchema()->all();

            if (empty($schema['enabled'])) {
                continue;
            }

            $sectionFolder = trim((string) ($schema['section_folder'] ?? ''));
            $sectionTitle = trim((string) ($schema['section'] ?? ''));

            if ($sectionFolder === '' || $sectionTitle === '') {
                continue;
            }

            $subnav = [
                'title' => (string) ($schema['title'] ?? $resourceClass::titleLabel()),
                'folder' => $resourceClass::path(),
                'file' => self::normalizeFile((string) ($schema['file'] ?? '')),
                'authority' => self::normalizeAuthority((array) ($schema['authority'] ?? [])),
                '__resource_order' => (int) ($schema['order'] ?? 100),
            ];

            if (!isset($sections[$sectionFolder])) {
                $sections[$sectionFolder] = [
                    'title' => $sectionTitle,
                    'folder' => $sectionFolder,
                    'icon' => (string) ($schema['section_icon'] ?? $resourceClass::icon()),
                    'authority' => self::normalizeAuthority((array) ($schema['authority'] ?? [])),
                    'subnavs' => [],
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

    private static function mergeSection(array $base, array $resourceSection): array
    {
        $base['title'] = (string) ($base['title'] ?? $resourceSection['title'] ?? '');
        $base['folder'] = (string) ($base['folder'] ?? $resourceSection['folder'] ?? '');
        $base['icon'] = (string) ($base['icon'] ?? $resourceSection['icon'] ?? 'bi-bug');
        $base['file'] = (string) ($base['file'] ?? '');
        $base['authority'] = self::mergeAuthority(
            (array) ($base['authority'] ?? []),
            (array) ($resourceSection['authority'] ?? [])
        );
        $base['subnavs'] = self::mergeSubnavs(
            (array) ($base['subnavs'] ?? []),
            ...(array) ($resourceSection['subnavs'] ?? [])
        );

        return $base;
    }

    private static function mergeSubnavs(array $baseSubnavs, array ...$resourceSubnavs): array
    {
        foreach ($resourceSubnavs as $resourceSubnav) {
            $merged = false;

            foreach ($baseSubnavs as $index => $subnav) {
                if (!is_array($subnav)) {
                    continue;
                }

                $sameFolder = (($subnav['folder'] ?? null) === ($resourceSubnav['folder'] ?? null));
                $sameTitle = self::normalizeTitle((string) ($subnav['title'] ?? '')) === self::normalizeTitle((string) ($resourceSubnav['title'] ?? ''));

                if (!$sameFolder && !$sameTitle) {
                    continue;
                }

                $baseSubnavs[$index] = array_merge($subnav, $resourceSubnav);
                $baseSubnavs[$index]['authority'] = self::mergeAuthority(
                    (array) ($subnav['authority'] ?? []),
                    (array) ($resourceSubnav['authority'] ?? [])
                );
                $merged = true;
                break;
            }

            if (!$merged) {
                $baseSubnavs[] = $resourceSubnav;
            }
        }

        usort($baseSubnavs, static function (array $left, array $right): int {
            $leftOrder = (int) ($left['__resource_order'] ?? 1000);
            $rightOrder = (int) ($right['__resource_order'] ?? 1000);

            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        foreach ($baseSubnavs as $index => $subnav) {
            unset($baseSubnavs[$index]['__resource_order']);
        }

        return array_values($baseSubnavs);
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
