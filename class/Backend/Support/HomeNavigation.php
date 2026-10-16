<?php

namespace Wonder\Backend\Support;

use Wonder\Http\UrlParser;

/**
 * Albero della navigazione backend ridotto per la home: le sezioni visibili
 * all'utente, ciascuna con i propri link già risolti. Applica le autorità a
 * ogni livello come la sidebar, salta "Home" e le sezioni i cui sottomenu
 * dichiarati sono tutti nascosti. Solo dati: la view li passa a `LinkCard`
 * e `LinkListCard`, che raggruppa i link per intestazione.
 */
final class HomeNavigation
{
    /**
     * @param array<int, array<string, mixed>> $navigation sezioni di `BackendNavigation::all()`
     * @param array<int, string> $authorities autorità dell'utente
     * @return list<array{title: string, icon: string, href: ?string, links: list<array{title: string, href: string, group: string}>}>
     */
    public static function sections(array $navigation, array $authorities, string $backendUrl): array
    {
        $sections = [];

        foreach ($navigation as $section) {
            if (!is_array($section) || !self::allowed($section, $authorities)) {
                continue;
            }

            $title = (string) ($section['title'] ?? 'ND');

            if ($title === 'Home') {
                continue;
            }

            $subnavs = (array) ($section['subnavs'] ?? []);
            $links = self::links($subnavs, $authorities, $backendUrl);

            if ($subnavs !== [] && $links === []) {
                continue;
            }

            $sections[] = [
                'title' => $title,
                'icon' => (string) ($section['icon'] ?? '') ?: 'bi-bug',
                'href' => $subnavs === [] ? self::url($section, $backendUrl) : null,
                'links' => $links,
            ];
        }

        return $sections;
    }

    /** @return list<array{title: string, href: string, group: string}> */
    private static function links(array $items, array $authorities, string $backendUrl, string $group = ''): array
    {
        $links = [];

        foreach ($items as $item) {
            if (!is_array($item) || !self::allowed($item, $authorities)) {
                continue;
            }

            $title = (string) ($item['title'] ?? 'ND');
            $children = (array) ($item['subnavs'] ?? []);

            if ($children !== []) {
                array_push($links, ...self::links($children, $authorities, $backendUrl, $title));
                continue;
            }

            $links[] = ['title' => $title, 'href' => self::url($item, $backendUrl), 'group' => $group];
        }

        return $links;
    }

    private static function allowed(array $item, array $authorities): bool
    {
        $required = (array) ($item['authority'] ?? []);

        return $required === [] || array_intersect($required, $authorities) !== [];
    }

    private static function url(array $item, string $backendUrl): string
    {
        $folder = trim((string) ($item['folder'] ?? ''), '/');
        $file = trim((string) ($item['file'] ?? ''), '/');

        if ((new UrlParser((string) ($item['file'] ?? '')))->isAbsolute()) {
            return (string) $item['file'];
        }

        return rtrim($backendUrl, '/').'/'.trim($folder.'/'.$file, '/');
    }
}
