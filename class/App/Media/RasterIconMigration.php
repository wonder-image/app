<?php

namespace Wonder\App\Media;

use mysqli;
use Wonder\App\Models\Media\Media;

/**
 * Le icone raster della libreria Media diventano immagini: `icon` resta
 * solo per gli svg. Esegue il piano di `RasterIconPlan`: copia i file da
 * `assets/upload/icons` a `assets/upload/images` (gli originali restano,
 * così gli indirizzi scritti a mano nei template continuano a rispondere),
 * poi cambia `type`, e dove serve `file` e `slug`, della riga.
 *
 * Idempotente: una riga cambia tipo solo dopo che tutti i suoi file sono
 * stati copiati, e una copia già fatta non si ripete. Gira in
 * `forge update` dopo `runTables()`.
 */
final class RasterIconMigration
{
    /**
     * @return array{migrated: int, renamed: list<int|string>, skipped: array<int|string, string>}|array{}
     *         vuoto se non c'era niente da fare
     */
    public static function run(mysqli $connection, ?string $uploadRoot = null): array
    {
        $uploadRoot ??= defined('ROOT') ? ROOT.'/assets/upload' : null;

        if ($uploadRoot === null || !self::tableReady($connection)) {
            return [];
        }

        $icons = self::rows($connection, MediaTypes::ICON, 'id, slug, file');

        if ($icons === []) {
            return [];
        }

        return self::apply(
            $icons,
            array_column(self::rows($connection, MediaTypes::IMAGE, 'slug'), 'slug'),
            $uploadRoot,
            static function (array $step) use ($connection): bool {
                $statement = $connection->prepare(
                    'UPDATE `'.Media::$table.'` SET `type` = ?, `file` = ?, `slug` = ? WHERE `id` = ? AND `type` = ?'
                );

                return $statement && $statement->execute([
                    MediaTypes::IMAGE,
                    json_encode($step['file'], JSON_PRETTY_PRINT),
                    $step['slug'],
                    $step['id'],
                    MediaTypes::ICON,
                ]);
            }
        );
    }

    /**
     * Pianifica, copia i file e chiama `$update` per ogni riga i cui file
     * sono tutti al loro posto. Senza database: `$update` riceve il passo
     * (`id`, `slug`, `file`) e dice se la riga è stata aggiornata.
     *
     * @param list<array{id: int|string, slug?: string, file?: mixed}> $icons righe `type = 'icon'`
     * @param list<string> $imageSlugs slug delle immagini esistenti
     * @param callable(array): bool $update
     * @return array{migrated: int, renamed: list<int|string>, skipped: array<int|string, string>}|array{}
     */
    public static function apply(array $icons, array $imageSlugs, string $uploadRoot, callable $update): array
    {
        if (!RasterIconPlan::needed($icons)) {
            return [];
        }

        $from = rtrim($uploadRoot, '/').'/'.MediaTypes::folder(MediaTypes::ICON);
        $to = rtrim($uploadRoot, '/').'/'.MediaTypes::folder(MediaTypes::IMAGE);

        $plan = RasterIconPlan::plan(
            $icons,
            self::files($from),
            self::files($to),
            $imageSlugs,
            static fn (string $source, string $target): bool => self::identical($from.'/'.$source, $to.'/'.$target)
        );

        if ($plan['migrate'] === [] && $plan['skipped'] === []) {
            return [];
        }

        $summary = ['migrated' => 0, 'renamed' => [], 'skipped' => []];

        foreach ($plan['skipped'] as $skipped) {
            $summary['skipped'][$skipped['id']] = $skipped['reason'];
        }

        foreach ($plan['migrate'] as $step) {
            if (!self::copy($step['copies'], $from, $to)) {
                $summary['skipped'][$step['id']] = 'copia dei file non riuscita';
                continue;
            }

            if (!$update($step)) {
                $summary['skipped'][$step['id']] = 'riga non aggiornata';
                continue;
            }

            $summary['migrated']++;

            if ($step['renamed']) {
                $summary['renamed'][] = $step['id'];
            }
        }

        return $summary;
    }

    /**
     * Copia senza sovrascrivere: un file già presente è lo stesso contenuto
     * (lo ha verificato il piano) e si lascia com'è.
     *
     * @param list<array{0: string, 1: string}> $copies
     */
    private static function copy(array $copies, string $from, string $to): bool
    {
        if ($copies !== [] && !is_dir($to) && !@mkdir($to, 0775, true) && !is_dir($to)) {
            return false;
        }

        foreach ($copies as [$source, $target]) {
            if (is_file($to.'/'.$target)) {
                if (!self::identical($from.'/'.$source, $to.'/'.$target)) {
                    return false;
                }

                continue;
            }

            if (!@copy($from.'/'.$source, $to.'/'.$target)) {
                // Una copia a metà sarebbe un file diverso al prossimo giro.
                @unlink($to.'/'.$target);

                return false;
            }
        }

        return true;
    }

    private static function identical(string $source, string $target): bool
    {
        return is_file($source) && is_file($target)
            && filesize($source) === filesize($target)
            && hash_file('sha256', $source) === hash_file('sha256', $target);
    }

    /** @return list<string> */
    private static function files(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        return array_values(array_filter(
            scandir($directory) ?: [],
            static fn (string $name): bool => is_file($directory.'/'.$name)
        ));
    }

    /** @return list<array<string, mixed>> righe non eliminate di quel tipo */
    private static function rows(mysqli $connection, string $type, string $columns): array
    {
        $statement = $connection->prepare(
            'SELECT '.$columns.' FROM `'.Media::$table."` WHERE `type` = ? AND `deleted` = 'false' ORDER BY `id`"
        );

        if (!$statement || !$statement->execute([$type])) {
            return [];
        }

        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private static function tableReady(mysqli $connection): bool
    {
        $exists = $connection->query("SHOW TABLES LIKE '".Media::$table."'");

        return is_object($exists) && (int) ($exists->num_rows ?? 0) > 0;
    }
}
