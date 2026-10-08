<?php

namespace Wonder\App;

use Wonder\App\Models\Css\CssFont;
use Wonder\App\Module\Contracts\ModuleDefaults;
use Wonder\App\Support\CssFontFamily;
use Wonder\App\Support\DefaultRows;
use Wonder\Data\Formatters\String\SlugFormatter;

/**
 * Le righe precaricate del pacchetto app, aggiunte da `forge update --local`
 * come quelle dei moduli: dopo l'import di `shared/sync-data.json`, che
 * altrimenti cancellerebbe i font nuovi appena scritti dal seed delle righe.
 *
 * I font si riconoscono dallo slug: prima lo prendono quelli che non lo
 * hanno ancora, poi si aggiungono quelli che mancano.
 */
final class AppDefaults implements ModuleDefaults
{
    public static function seed(DefaultRows $rows): void
    {
        self::fillSlugs();
        $rows->ensure(CssFont::class, 'slug', self::fontRows());
    }

    /** @return list<array{name: string, slug: string, link: string, font_family: string, visible: string}> */
    public static function fontRows(): array
    {
        $rows = [];

        foreach (RuntimeDefaults::defaultFonts() as $font) {
            $rows[] = [
                'name' => (string) $font['name'],
                'slug' => (string) SlugFormatter::format((string) $font['name']),
                'link' => (string) $font['link'],
                'font_family' => CssFontFamily::normalize((string) $font['font-family']),
                'visible' => 'true',
            ];
        }

        return $rows;
    }

    /**
     * Lo slug dei font che non lo hanno, nato dal nome (classe pura):
     * `id => slug`, con `-2`, `-3`… se è già preso.
     *
     * @param array<int, mixed> $rows righe di `css_font` con id, name e slug
     * @return array<int, string>
     */
    public static function missingSlugs(array $rows): array
    {
        $taken = [];

        foreach ($rows as $row) {
            $slug = is_array($row) ? (string) ($row['slug'] ?? '') : '';

            if ($slug !== '') {
                $taken[$slug] = true;
            }
        }

        $missing = [];

        foreach ($rows as $row) {
            if (!is_array($row) || (string) ($row['slug'] ?? '') !== '') {
                continue;
            }

            $base = (string) SlugFormatter::format((string) ($row['name'] ?? ''));
            $id = (int) ($row['id'] ?? 0);

            if ($base === '' || $id <= 0) {
                continue;
            }

            $slug = $base;

            for ($n = 2; isset($taken[$slug]); $n++) {
                $slug = $base.'-'.$n;
            }

            $taken[$slug] = true;
            $missing[$id] = $slug;
        }

        return $missing;
    }

    private static function fillSlugs(): void
    {
        $rows = CssFont::query()->Select(CssFont::$table, null, null, null, null, ['id', 'name', 'slug'])->row;

        foreach (self::missingSlugs(is_array($rows) ? $rows : []) as $id => $slug) {
            CssFont::query()->Update(CssFont::$table, ['slug' => $slug], 'id', $id);
        }
    }
}
