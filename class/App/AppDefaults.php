<?php

namespace Wonder\App;

use Wonder\App\Models\Css\CssFont;
use Wonder\App\Module\Contracts\ModuleDefaults;
use Wonder\App\Support\CssFontFamily;
use Wonder\App\Support\DefaultRows;

/**
 * Le righe precaricate del pacchetto app, aggiunte da `forge update --local`
 * come quelle dei moduli: dopo l'import di `shared/sync-data.json`, che
 * altrimenti cancellerebbe i font nuovi appena scritti dal seed delle righe.
 */
final class AppDefaults implements ModuleDefaults
{
    public static function seed(DefaultRows $rows): void
    {
        $rows->ensure(CssFont::class, 'name', self::fontRows());
    }

    /** @return list<array{name: string, link: string, font_family: string, visible: string}> */
    public static function fontRows(): array
    {
        $rows = [];

        foreach (RuntimeDefaults::defaultFonts() as $font) {
            $rows[] = [
                'name' => (string) $font['name'],
                'link' => (string) $font['link'],
                'font_family' => CssFontFamily::normalize((string) $font['font-family']),
                'visible' => 'true',
            ];
        }

        return $rows;
    }
}
