<?php

namespace Wonder\Backend\Support;

use Throwable;
use Wonder\App\Logger;
use Wonder\Backend\Contracts\HomeLinkSummary;
use Wonder\Http\UrlParser;

/**
 * Albero della navigazione backend ridotto per la home: le sezioni visibili
 * all'utente, ciascuna con i propri link già risolti. Applica le autorità a
 * ogni livello come la sidebar, salta "Home" e le sezioni i cui sottomenu
 * dichiarati sono tutti nascosti. Solo dati: la view li passa a `LinkCard`
 * e `LinkListCard`, che raggruppa i link per intestazione. Un link con
 * `home_summary` (vedi `NavigationSchema::homeSummary()`) riceve badge e
 * riga secondaria calcolati qui; se la classe solleva un'eccezione il link
 * resta senza, la home non si rompe.
 */
final class HomeNavigation
{
    /**
     * @param array<int, array<string, mixed>> $navigation sezioni di `BackendNavigation::all()`
     * @param array<int, string> $authorities autorità dell'utente
     * @return list<array{title: string, icon: string, href: ?string, links: list<array{title: string, href: string, group: string, badge: string, detail: string}>}>
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

    /** @return list<array{title: string, href: string, group: string, badge: string, detail: string}> */
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

            $links[] = ['title' => $title, 'href' => self::url($item, $backendUrl), 'group' => $group]
                + self::summary($item['home_summary'] ?? null, $authorities);
        }

        return $links;
    }

    /** @return array{badge: string, detail: string} */
    private static function summary(mixed $class, array $authorities): array
    {
        $empty = ['badge' => '', 'detail' => ''];

        if (!is_string($class) || !is_subclass_of($class, HomeLinkSummary::class)) {
            return $empty;
        }

        try {
            $summary = (new $class())->summary($authorities) ?? [];
        } catch (Throwable $exception) {
            Logger::log($exception, 'backend', 'home_link_summary', 'ERROR', 'error', ['summary' => $class], false);

            return $empty;
        }

        return [
            'badge' => trim((string) ($summary['badge'] ?? '')),
            'detail' => trim((string) ($summary['detail'] ?? '')),
        ];
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
