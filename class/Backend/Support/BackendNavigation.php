<?php

namespace Wonder\Backend\Support;

use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;

final class BackendNavigation
{
    public static function all(): array
    {
        return self::merge([
            ...self::defaultTop(),
            ...self::defaultBottom(),
        ]);
    }

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

        foreach ($navigation as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            unset($navigation[$index]['__resource_order']);
        }

        return array_values($navigation);
    }

    private static function defaultTop(): array
    {
        return [
            [
                'title' => 'Home',
                'folder' => 'home',
                'icon' => 'bi-house-door',
                'file' => self::backendHomeFile(),
                'authority' => [],
                'subnavs' => [],
            ],
        ];
    }

    private static function defaultBottom(): array
    {
        return [
            [
                'title' => 'Media',
                'folder' => 'media',
                'icon' => 'bi-image',
                'authority' => ['admin'],
                'subnavs' => [
                    ['title' => 'Logo', 'folder' => 'app/media/logos', 'file' => '', 'authority' => ['admin']],
                    ['title' => 'Immagini', 'folder' => 'app/media/images', 'file' => 'list.php', 'authority' => ['admin']],
                    ['title' => 'Icone', 'folder' => 'app/media/icons', 'file' => 'list.php', 'authority' => ['admin']],
                    ['title' => 'Documenti', 'folder' => 'app/media/documents', 'file' => 'list.php', 'authority' => ['admin']],
                    ['title' => 'Upload di massa', 'folder' => 'app/media/upload-massive', 'file' => '', 'authority' => ['admin']],
                ],
            ],
            [
                'title' => 'Set Up',
                'folder' => 'set-up',
                'icon' => 'bi-gear',
                'authority' => ['admin'],
                'subnavs' => [
                    ['title' => 'Dati aziendali', 'folder' => 'app/config/corporate-data', 'file' => '', 'authority' => ['admin']],
                    ['title' => 'Seo', 'folder' => 'app/config/seo', 'file' => '', 'authority' => ['admin']],
                    ['title' => 'Documenti legali', 'folder' => 'app/config/legal-documents', 'file' => 'list.php', 'authority' => ['admin']],
                    ['title' => 'Utenti', 'folder' => 'app/config/user', 'file' => 'list.php', 'authority' => ['admin']],
                    ['title' => 'Utenti API', 'folder' => 'app/config/api-users', 'file' => 'list.php', 'authority' => ['admin']],
                    ['title' => 'Analitica', 'folder' => 'app/config/analytics', 'file' => '', 'authority' => ['admin']],
                    ['title' => 'Credenziali', 'folder' => 'app/config/credentials', 'file' => '', 'authority' => ['admin']],
                    ['title' => 'Editor', 'folder' => 'app/config/configuration-file', 'file' => '', 'authority' => ['admin']],
                    ['title' => 'Errori SQL', 'folder' => 'app/config/sql-error', 'file' => '', 'authority' => ['admin']],
                    ['title' => 'Download', 'folder' => 'app/config/sql-download', 'file' => '', 'authority' => ['admin']],
                ],
            ],
            [
                'title' => 'Stile',
                'folder' => 'css',
                'icon' => 'bi-award',
                'authority' => ['admin'],
                'subnavs' => [
                    ['title' => 'Default', 'folder' => 'app/css/default', 'file' => '', 'authority' => ['admin']],
                    ['title' => 'Font', 'folder' => 'app/css/font', 'file' => 'list.php', 'authority' => ['admin']],
                    ['title' => 'Colori', 'folder' => 'app/css/color', 'file' => 'list.php', 'authority' => ['admin']],
                    ['title' => 'Input', 'folder' => 'app/css/input', 'file' => '', 'authority' => ['admin']],
                    ['title' => 'Modal', 'folder' => 'app/css/modal', 'file' => '', 'authority' => ['admin']],
                    ['title' => 'Dropdown', 'folder' => 'app/css/dropdown', 'file' => '', 'authority' => ['admin']],
                    ['title' => 'Alert', 'folder' => 'app/css/alert', 'file' => '', 'authority' => ['admin']],
                ],
            ],
            [
                'title' => 'Log',
                'folder' => 'log',
                'icon' => 'bi-ear',
                'authority' => ['admin', 'administrator'],
                'subnavs' => [
                    ['title' => 'Accessi Utente', 'folder' => 'app/log/auth-users', 'file' => 'list.php', 'authority' => ['admin', 'administrator']],
                    ['title' => 'Email', 'folder' => 'app/log/email', 'file' => 'list.php', 'authority' => ['admin', 'administrator']],
                    ['title' => 'Consensi', 'folder' => 'app/log/consent', 'file' => 'list.php', 'authority' => ['admin', 'administrator']],
                ],
            ],
        ];
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

            $subnav = [
                'title' => (string) ($schema['title'] ?? $resourceClass::titleLabel()),
                'folder' => $resourceClass::path(),
                'file' => self::normalizeFile((string) ($schema['file'] ?? '')),
                'authority' => self::normalizeAuthority((array) ($schema['authority'] ?? [])),
                '__resource_order' => (int) ($schema['order'] ?? 100),
            ];

            if ($sectionFolder === '' || $sectionTitle === '') {
                $sections['resource:'.$resourceClass] = [
                    'title' => $subnav['title'],
                    'folder' => $subnav['folder'],
                    'icon' => (string) ($resourceClass::icon() ?: 'bi-bug'),
                    'file' => $subnav['file'],
                    'authority' => $subnav['authority'],
                    'subnavs' => [],
                    '__resource_order' => $subnav['__resource_order'],
                ];
                continue;
            }

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
        $base['file'] = (string) ($base['file'] ?? $resourceSection['file'] ?? '');
        $base['authority'] = self::mergeAuthority(
            (array) ($base['authority'] ?? []),
            (array) ($resourceSection['authority'] ?? [])
        );
        $base['subnavs'] = self::mergeSubnavs(
            (array) ($base['subnavs'] ?? []),
            ...(array) ($resourceSection['subnavs'] ?? [])
        );
        $base['__resource_order'] = (int) ($base['__resource_order'] ?? $resourceSection['__resource_order'] ?? 1000);

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

    private static function backendHomeFile(): string
    {
        $permits = $GLOBALS['PERMITS']['backend']['links']['home'] ?? '';

        return is_string($permits) ? $permits : '';
    }
}
