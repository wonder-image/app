<?php

namespace Wonder\App\Support;

/**
 * I fogli di stile delle righe di `css_font`, pronti per la testata.
 *
 * Un link relativo al sito (`/vendor/…`) diventa assoluto con l'indirizzo del
 * sito, così vale anche in una cartella; Google Fonts prende `display=swap`.
 * Più righe con lo stesso foglio lo caricano una volta sola.
 */
final class CssFontLinks
{
    /**
     * @param iterable<mixed> $rows le righe visibili di `css_font`
     * @return list<string>
     */
    public static function hrefs(iterable $rows, string $siteUrl): array
    {
        $hrefs = [];

        foreach ($rows as $row) {
            $link = is_array($row) ? trim((string) ($row['link'] ?? '')) : '';

            if ($link === '') {
                continue;
            }

            if (str_starts_with($link, '/') && !str_starts_with($link, '//')) {
                $link = rtrim($siteUrl, '/').$link;
            }

            // display=swap: testo subito visibile con il fallback, niente FOIT.
            if (str_contains($link, 'fonts.googleapis.com') && !str_contains($link, 'display=')) {
                $link .= (str_contains($link, '?') ? '&' : '?').'display=swap';
            }

            $hrefs[$link] = true;
        }

        return array_keys($hrefs);
    }
}
