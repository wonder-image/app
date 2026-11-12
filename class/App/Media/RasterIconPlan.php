<?php

namespace Wonder\App\Media;

use Closure;

/**
 * Decide come le icone raster diventano immagini, senza toccare database
 * né disco: `RasterIconMigration` esegue il piano.
 *
 * Un'icona passa a `image` se ha almeno un file e nessuno è svg. I suoi
 * file (originale, webp e misure `nome-<larghezza>`) si copiano da
 * `icons/` a `images/`: mai spostati, mai sovrascritti. Se in `images/`
 * c'è già un file diverso con lo stesso nome, o un'immagine con lo stesso
 * slug, l'icona prende un nome nuovo (`nome-icona`, `nome-icona2`, ...).
 */
final class RasterIconPlan
{
    public const SUFFIX = '-icona';

    /** Tentativi di nome prima di rinunciare a una riga. */
    private const MAX_ATTEMPTS = 50;

    /**
     * @param list<array{id: int|string, slug?: string, file?: mixed}> $icons righe `type = 'icon'`
     * @param list<string> $iconFiles nomi dei file in `icons/`
     * @param list<string> $imageFiles nomi dei file in `images/`
     * @param list<string> $imageSlugs slug delle immagini esistenti
     * @param (Closure(string, string): bool)|null $identical vero se il file di `icons/` (primo nome) e
     *        quello di `images/` (secondo) hanno lo stesso contenuto: una copia già fatta non è una collisione
     *
     * @return array{
     *     migrate: list<array{id: int|string, slug: string, file: list<string>, copies: list<array{0: string, 1: string}>, renamed: bool}>,
     *     skipped: list<array{id: int|string, reason: string}>
     * }
     */
    public static function plan(
        array $icons,
        array $iconFiles,
        array $imageFiles,
        array $imageSlugs = [],
        ?Closure $identical = null
    ): array {
        $identical ??= static fn (string $from, string $to): bool => false;
        $iconFiles = array_fill_keys($iconFiles, true);
        $taken = array_fill_keys($imageFiles, true);
        $slugs = array_fill_keys($imageSlugs, true);
        $owned = self::ownedFiles($icons);
        $plan = ['migrate' => [], 'skipped' => []];

        foreach ($icons as $icon) {
            $files = self::storedFiles($icon['file'] ?? null);

            if (!self::isRaster($files)) {
                continue;
            }

            $slug = trim((string) ($icon['slug'] ?? ''));
            $step = null;

            for ($attempt = 0; $attempt < self::MAX_ATTEMPTS && $step === null; $attempt++) {
                $suffix = $attempt === 0 ? '' : self::SUFFIX.($attempt > 1 ? $attempt : '');
                $step = self::step($icon['id'], $slug, $files, $suffix, $iconFiles, $taken, $slugs, $owned, $identical);
            }

            if ($step === null) {
                $plan['skipped'][] = ['id' => $icon['id'], 'reason' => 'nessun nome libero in images/'];
                continue;
            }

            if ($step === false) {
                $plan['skipped'][] = ['id' => $icon['id'], 'reason' => 'file mancante in icons/ e nome già occupato in images/'];
                continue;
            }

            foreach ($step['copies'] as [, $to]) {
                $taken[$to] = true;
            }

            $slugs[$step['slug']] = true;
            $plan['migrate'][] = $step;
        }

        return $plan;
    }

    /** Vero se almeno una riga è un'icona raster: senza, non serve nemmeno leggere le cartelle. */
    public static function needed(array $icons): bool
    {
        foreach ($icons as $icon) {
            if (self::isRaster(self::storedFiles($icon['file'] ?? null))) {
                return true;
            }
        }

        return false;
    }

    /**
     * File da copiare per un file salvato: l'originale e le sue misure
     * presenti in `icons/`, con il nome di arrivo. Un file che è
     * l'originale di un altro media non è una misura di questo.
     *
     * @param array<string, true> $iconFiles
     * @param array<string, true> $owned
     * @return list<array{0: string, 1: string}>
     */
    public static function copies(string $file, string $suffix, array $iconFiles, array $owned = []): array
    {
        $name = (string) pathinfo($file, PATHINFO_FILENAME);
        $extension = MediaTypes::extension($file);
        $pattern = '/^'.preg_quote($name, '/').'(-\d+)?\.('.preg_quote($extension, '/').'|webp)$/D';
        $copies = [];

        foreach (array_keys($iconFiles) as $candidate) {
            $candidate = (string) $candidate;

            if (preg_match($pattern, $candidate, $match) !== 1) {
                continue;
            }

            if ($candidate !== $file && isset($owned[$candidate])) {
                continue;
            }

            $copies[] = [$candidate, $name.$suffix.($match[1] ?? '').'.'.$match[2]];
        }

        sort($copies);

        return $copies;
    }

    /**
     * Il passo per una riga con quel suffisso: null se il nome è occupato
     * (si prova il prossimo), false se la riga va lasciata com'è.
     */
    private static function step(
        int|string $id,
        string $slug,
        array $files,
        string $suffix,
        array $iconFiles,
        array $taken,
        array $slugs,
        array $owned,
        Closure $identical
    ): array|false|null {
        // Lo slug cambia solo se un'immagine ha già quello dell'icona.
        $newSlug = isset($slugs[$slug]) ? $slug.$suffix : $slug;

        if ($newSlug !== '' && isset($slugs[$newSlug])) {
            return null;
        }

        $copies = [];
        $stored = [];

        foreach ($files as $file) {
            $target = (string) pathinfo($file, PATHINFO_FILENAME).$suffix.'.'.MediaTypes::extension($file);
            $fileCopies = self::copies($file, $suffix, $iconFiles, $owned);

            // Originale sparito da icons/: la riga cambia tipo solo se il suo
            // nome in images/ è libero, altrimenti mostrerebbe il file di un altro.
            if (!isset($iconFiles[$file])) {
                if (isset($taken[$target])) {
                    return $suffix === '' ? false : null;
                }

                $fileCopies = [];
            }

            foreach ($fileCopies as [$from, $to]) {
                if (isset($taken[$to]) && !$identical($from, $to)) {
                    return null;
                }
            }

            $copies = array_merge($copies, $fileCopies);
            $stored[] = $target;
        }

        return [
            'id' => $id,
            'slug' => $newSlug,
            'file' => $stored,
            'copies' => $copies,
            'renamed' => $suffix !== '',
        ];
    }

    /** Vero se c'è almeno un file e nessuno è svg. */
    private static function isRaster(array $files): bool
    {
        if ($files === []) {
            return false;
        }

        foreach ($files as $file) {
            if (MediaTypes::accepts(MediaTypes::ICON, $file)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, true> gli originali di tutte le righe */
    private static function ownedFiles(array $icons): array
    {
        $owned = [];

        foreach ($icons as $icon) {
            foreach (self::storedFiles($icon['file'] ?? null) as $file) {
                $owned[$file] = true;
            }
        }

        return $owned;
    }

    /** @return list<string> nomi di file semplici, senza cartelle */
    private static function storedFiles(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $files = [];

        foreach (is_array($value) ? $value : [] as $file) {
            if (is_string($file) && ($file = trim($file)) !== '' && basename($file) === $file) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
