<?php

namespace Wonder\App\Media;

use Closure;
use mysqli;
use RuntimeException;
use Throwable;

/**
 * Porta nella libreria Media i file salvati in una colonna di prima (un
 * elenco JSON di nomi, come lo scrive `uploadFiles()`): per ogni riga nasce
 * un media del tipo giusto, la riga ne prende l'id e l'utilizzo finisce nel
 * registro (`MediaUsages`). La usano il core (i loghi del sito) e i moduli.
 *
 * Esegue il piano di {@see LegacyMediaPlan}:
 *
 * - il file si **copia** nella cartella della libreria; l'originale resta
 *   dov'è, niente si sovrascrive;
 * - media, id sulla riga, colonna di prima svuotata e utilizzo si scrivono
 *   in una transazione sola: o tutto o niente;
 * - le misure di un'immagine vanno alla coda del core (o si generano subito
 *   dove lo scheduler è fermo).
 *
 * Idempotente: lavora solo le righe con la colonna di prima ancora piena.
 * Le colonne con l'id che mancano le aggiunge da sé (`INT NULL`). Senza la
 * libreria o il registro non fa niente: ci riprova all'update successivo.
 *
 * Una definizione:
 *
 * ```php
 * [
 *     'table' => 'logos',
 *     'field' => 'main',                  // la colonna di prima
 *     'columns' => ['image' => 'main_id'], // tipo => colonna con l'id
 *     'slug' => 'logo-{slug}',            // modello, valori della riga
 *     'alt' => 'Logo {name}',
 *     'name' => 'Logo {name}',            // facoltativo, di base {name}
 *     'legacy' => 'logos',                // cartella sotto assets/upload
 *     // facoltativo, righe di un repeater: l'utilizzo va sul padre
 *     'owner' => ['table' => 'gst_attributes', 'key' => 'attribute_id', 'column' => 'values.image_id'],
 *     // facoltativo, solo queste righe: chi ha appena scritto un file di prima
 *     'ids' => [12, 13],
 * ]
 * ```
 */
final class LegacyMediaMigration
{
    public const MEDIA_TABLE = 'media';
    public const USAGES_TABLE = 'media_usages';

    /** La colonna di prima svuotata: un elenco di file vuoto, che è JSON valido. */
    public const EMPTY = '[]';

    /** Le misure del core, per quando il sito non le ha dichiarate. */
    private const DEFAULT_WIDTHS = [240, 480, 620, 960, 1200, 1440, 1920, 2400];

    /**
     * @param list<array<string, mixed>> $definitions
     * @param (Closure(string): string)|null $slug fa uno slug da un testo (di base {@see LegacyMediaPlan::slug()})
     * @param (callable(string): void)|null $sizes prepara le misure di un'immagine copiata (percorso assoluto)
     * @return array{migrated?: int, cleared?: int, skipped?: array<string, string>} vuoto se niente è cambiato
     */
    public static function run(mysqli $connection, array $definitions, ?string $uploadRoot = null, ?Closure $slug = null, ?callable $sizes = null): array
    {
        $uploadRoot ??= defined('ROOT') ? rtrim((string) constant('ROOT'), '/').'/assets/upload' : null;

        if ($uploadRoot === null
            || !self::tableExists($connection, self::MEDIA_TABLE)
            || !self::tableExists($connection, self::USAGES_TABLE)) {
            return [];
        }

        $uploadRoot = rtrim($uploadRoot, '/');
        $sources = self::sources($connection, $definitions, $uploadRoot);

        if ($sources === []) {
            return [];
        }

        $library = [];

        foreach (MediaTypes::all() as $type) {
            $folder = MediaTypes::folder($type);
            $library[$folder] = self::files($uploadRoot.'/'.$folder);
        }

        $plan = LegacyMediaPlan::plan(
            $sources,
            array_column(self::all($connection, 'SELECT `slug` FROM `'.self::MEDIA_TABLE.'`'), 'slug'),
            $library,
            static fn (string $source, string $target): bool => self::identical($uploadRoot.'/'.$source, $uploadRoot.'/'.$target),
            $slug
        );

        $sizes ??= self::sizes(...);
        $withSource = in_array('source', self::columns($connection, self::MEDIA_TABLE), true);

        // L'utilizzo si scrive sulla stessa connessione, dentro la transazione della riga.
        MediaUsages::useStore(new DatabaseMediaUsageStore($connection));

        try {
            return self::apply(
                $plan,
                static fn (string $source, string $target): bool => self::copy($uploadRoot.'/'.$source, $uploadRoot.'/'.$target),
                static fn (array $step): bool => self::save($connection, $step, $withSource),
                static fn (array $step): bool => self::clear($connection, $step),
                static function (array $step) use ($uploadRoot, $sizes): void {
                    if ($step['type'] === MediaTypes::IMAGE) {
                        $sizes($uploadRoot.'/'.$step['target']);
                    }
                }
            );
        } finally {
            MediaUsages::useStore(null);
        }
    }

    /**
     * Esegue un piano senza sapere di database e disco: copia, scrive,
     * svuota e prepara le misure con le funzioni che riceve.
     *
     * @param array{create: list<array<string, mixed>>, clear: list<array<string, mixed>>, skipped: list<array<string, mixed>>} $plan
     * @param callable(string, string): bool $copy copia il file di prima in libreria (percorsi sotto `assets/upload`)
     * @param callable(array): bool $save scrive media, id sulla riga e utilizzo, o niente
     * @param callable(array): bool $clear svuota la colonna di prima di una riga che ha già il suo media
     * @param callable(array): void $after dopo il salvataggio di un media (le misure)
     * @return array{migrated?: int, cleared?: int, skipped?: array<string, string>}
     */
    public static function apply(array $plan, callable $copy, callable $save, callable $clear, callable $after): array
    {
        if (!LegacyMediaPlan::needed($plan)) {
            return [];
        }

        $summary = ['migrated' => 0, 'cleared' => 0, 'skipped' => []];
        $label = static fn (array $step): string => $step['table'].'#'.$step['id'].'.'.$step['field'];

        foreach ($plan['skipped'] as $skipped) {
            $summary['skipped'][$label($skipped)] = $skipped['reason'];
        }

        foreach ($plan['create'] as $step) {
            if (!$copy($step['source'], $step['target'])) {
                $summary['skipped'][$label($step)] = 'copia del file non riuscita';
                continue;
            }

            if (!$save($step)) {
                $summary['skipped'][$label($step)] = 'media non salvato';
                continue;
            }

            $summary['migrated']++;
            $after($step);
        }

        foreach ($plan['clear'] as $step) {
            if ($clear($step)) {
                $summary['cleared']++;
            }
        }

        return array_filter($summary);
    }

    /** Accoda le misure di un'immagine; senza coda le genera subito. Mai un'eccezione. */
    public static function sizes(string $absolutePath): void
    {
        if (!is_file($absolutePath) || !in_array(strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return;
        }

        $widths = defined('RESPONSIVE_IMAGE_SIZES') ? constant('RESPONSIVE_IMAGE_SIZES') : null;
        $widths = is_array($widths) && $widths !== [] ? array_values(array_map('intval', $widths)) : self::DEFAULT_WIDTHS;
        $webp = defined('RESPONSIVE_IMAGE_WEBP') ? (bool) constant('RESPONSIVE_IMAGE_WEBP') : true;

        try {
            if (!ResizeQueue::enqueue($absolutePath, $widths, $webp)) {
                ResizeQueue::resize($absolutePath, $widths, $webp);
            }
        } catch (Throwable $error) {
            // L'originale c'è e il sito lo mostra: una misura mancata non ferma l'update.
            if (function_exists('__log')) {
                \__log($error, '[media]', '[legacy]');
            }
        }
    }

    /**
     * Le definizioni con le righe che hanno ancora un file nella colonna di
     * prima. Aggiunge le colonne con l'id che mancano.
     *
     * @param list<array<string, mixed>> $definitions
     * @return list<array<string, mixed>>
     */
    private static function sources(mysqli $connection, array $definitions, string $uploadRoot): array
    {
        $sources = [];
        $known = [];

        foreach ($definitions as $definition) {
            $table = (string) ($definition['table'] ?? '');
            $field = (string) ($definition['field'] ?? '');

            if ($table === '' || $field === '' || !self::tableExists($connection, $table)) {
                continue;
            }

            $columns = $known[$table] ??= self::columns($connection, $table);

            if (!in_array($field, $columns, true)) {
                continue;
            }

            foreach ((array) ($definition['columns'] ?? []) as $column) {
                if (!in_array($column, $columns, true)) {
                    // DDL: come lo scriverà l'allineamento delle tabelle.
                    if ($connection->query('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` INT NULL') === false) {
                        throw new RuntimeException('Colonna '.$table.'.'.$column.' non aggiunta: '.$connection->error);
                    }

                    $columns[] = $column;
                    $known[$table] = $columns;
                }
            }

            $where = '`'.$field.'` IS NOT NULL';

            if (in_array('deleted', $columns, true)) {
                $where .= " AND `deleted` = 'false'";
            }

            if (isset($definition['ids'])) {
                $ids = array_values(array_filter(array_map('intval', (array) $definition['ids']), static fn (int $id): bool => $id > 0));

                if ($ids === []) {
                    continue;
                }

                $where .= ' AND `id` IN ('.implode(',', $ids).')';
            }

            $rows = self::all($connection, 'SELECT * FROM `'.$table.'` WHERE '.$where.' ORDER BY `id`');

            if ($rows === []) {
                continue;
            }

            // I file di prima li ha scritti il form, nella cartella della sua pagina.
            $dir = trim((string) ($definition['legacy'] ?? ''), '/');

            $sources[] = [
                'table' => $table,
                'field' => $field,
                'columns' => (array) $definition['columns'],
                'slug' => (string) ($definition['slug'] ?? $table.'-{id}'),
                'alt' => (string) ($definition['alt'] ?? ''),
                'name' => (string) ($definition['name'] ?? '{name}'),
                'owner' => (array) ($definition['owner'] ?? []),
                'dirs' => [$dir => self::files($uploadRoot.'/'.$dir)],
                'rows' => $rows,
            ];
        }

        return $sources;
    }

    /** Media, id sulla riga, colonna di prima svuotata e utilizzo: tutto o niente. */
    private static function save(mysqli $connection, array $step, bool $withSource): bool
    {
        $table = '`'.$step['table'].'`';
        $column = '`'.$step['column'].'`';

        try {
            $connection->begin_transaction();

            $fields = ['slug', 'name', 'alt', 'type', 'file'];
            $values = [
                $step['slug'],
                $step['name'],
                $step['alt'],
                $step['type'],
                json_encode([basename($step['target'])], JSON_PRETTY_PRINT),
            ];

            if ($withSource) {
                // La sezione da cui viene: la usa il filtro della libreria.
                $fields[] = 'source';
                $values[] = $step['table'];
            }

            $insert = $connection->prepare(
                'INSERT INTO `'.self::MEDIA_TABLE.'` (`'.implode('`, `', $fields).'`) VALUES ('.implode(', ', array_fill(0, count($fields), '?')).')'
            );

            if (!$insert || !$insert->execute($values)) {
                throw new RuntimeException($connection->error);
            }

            $mediaId = (int) $connection->insert_id;

            // Scrive solo se la riga è ancora da migrare: due update insieme non creano due media.
            $update = $connection->prepare(
                'UPDATE '.$table.' SET '.$column.' = ?, `'.$step['field']."` = '".self::EMPTY."' WHERE `id` = ? AND (".$column.' IS NULL OR '.$column.' = 0)'
            );

            if ($mediaId <= 0 || !$update || !$update->execute([$mediaId, $step['id']]) || $update->affected_rows !== 1) {
                throw new RuntimeException($connection->error);
            }

            if (isset($step['owner'])) {
                self::syncOwner($connection, $step);
            } else {
                MediaUsages::sync($step['table'], $step['id'], $step['column'], [$mediaId]);
            }

            return $connection->commit();
        } catch (Throwable) {
            $connection->rollback();

            return false;
        }
    }

    /**
     * Riga di un repeater: l'utilizzo è del padre e raccoglie i media di
     * tutte le sue righe, non solo di quella appena migrata.
     */
    private static function syncOwner(mysqli $connection, array $step): void
    {
        $owner = $step['owner'];
        $column = '`'.$step['column'].'`';
        $where = '`'.$owner['key'].'` = '.(int) $owner['id'].' AND '.$column.' > 0';

        if (in_array('deleted', self::columns($connection, $step['table']), true)) {
            $where .= " AND `deleted` = 'false'";
        }

        $ids = array_map('intval', array_column(self::all($connection, 'SELECT '.$column.' FROM `'.$step['table'].'` WHERE '.$where), $step['column']));

        MediaUsages::sync($owner['table'], $owner['id'], $owner['column'], array_values(array_unique($ids)));
    }

    private static function clear(mysqli $connection, array $step): bool
    {
        $statement = $connection->prepare('UPDATE `'.$step['table'].'` SET `'.$step['field']."` = '".self::EMPTY."' WHERE `id` = ?");

        return $statement && $statement->execute([$step['id']]);
    }

    /** Copia senza sovrascrivere: un file già presente dev'essere la stessa copia. */
    private static function copy(string $source, string $target): bool
    {
        if (is_file($target)) {
            return self::identical($source, $target);
        }

        $directory = dirname($target);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        if (!@copy($source, $target)) {
            // Una copia a metà sarebbe un file diverso al prossimo giro.
            @unlink($target);

            return false;
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
        if ($directory === '' || !is_dir($directory)) {
            return [];
        }

        return array_values(array_filter(
            scandir($directory) ?: [],
            static fn (string $name): bool => is_file($directory.'/'.$name)
        ));
    }

    /** @return list<array<string, mixed>> */
    private static function all(mysqli $connection, string $sql): array
    {
        $result = $connection->query($sql);

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /** @return list<string> */
    private static function columns(mysqli $connection, string $table): array
    {
        $result = $connection->query('SHOW COLUMNS FROM `'.$table.'`');

        return $result ? array_map(static fn (array $row): string => (string) $row[0], $result->fetch_all()) : [];
    }

    private static function tableExists(mysqli $connection, string $table): bool
    {
        $result = $connection->query("SHOW TABLES LIKE '".$connection->real_escape_string($table)."'");

        return $result !== false && $result->num_rows > 0;
    }
}
