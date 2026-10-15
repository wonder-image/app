<?php

namespace Wonder\Frontend\Support;

use Wonder\App\Module\Manifest;

/**
 * Contenuto di robots.txt generato da `php forge update`.
 *
 * I moduli aggiungono percorsi da escludere e sitemap proprie nel loro
 * module.json: `"seo": {"robotsDisallow": ["/cart/"], "sitemaps": ["/shared/sitemap/catalogo.xml"]}`.
 * Il file porta un marcatore: finché c'è, l'update lo riscrive; chi lo
 * personalizza toglie il marcatore e il file non viene più toccato.
 */
final class RobotsTxt
{
    public const MARKER = '# Generato da Wonder: togli questa riga per personalizzare il file.';

    /** @param iterable<Manifest> $modules */
    public static function build(string $domain, bool $forceWww = false, iterable $modules = []): string
    {
        $domain = trim($domain);
        $base = 'https://'.($forceWww && !str_starts_with($domain, 'www.') ? 'www.' : '').$domain;

        $disallow = ['/backend/', '/api/'];
        $sitemaps = ['/shared/sitemap/sitemap.xml'];

        foreach ($modules as $module) {
            foreach ((array) $module->get('seo.robotsDisallow', []) as $path) {
                $disallow[] = self::path((string) $path);
            }
            foreach ((array) $module->get('seo.sitemaps', []) as $path) {
                $sitemaps[] = self::path((string) $path);
            }
        }

        $lines = [self::MARKER, 'User-agent: *'];
        foreach (array_unique(array_filter($disallow)) as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = '';
        foreach (array_unique(array_filter($sitemaps)) as $path) {
            $lines[] = 'Sitemap: '.$base.$path;
        }

        return implode("\n", $lines)."\n";
    }

    /** Il file va (ri)scritto se manca, se è ancora quello generato o se ha il dominio vuoto. */
    public static function shouldWrite(?string $current): bool
    {
        if ($current === null) {
            return true;
        }

        return str_contains($current, self::MARKER) || str_contains($current, 'https:///');
    }

    private static function path(string $path): string
    {
        $path = trim($path);

        return $path === '' ? '' : '/'.ltrim($path, '/');
    }
}
