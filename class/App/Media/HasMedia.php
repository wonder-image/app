<?php

namespace Wonder\App\Media;

use InvalidArgumentException;
use Throwable;
use Wonder\App\Path;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Inputs\InputFileDragDrop;
use Wonder\App\Support\MediaFileManager;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * I file di un Model stanno nella libreria Media del core: la riga ne
 * tiene l'id. Lo usano il core (i loghi del sito) e i moduli (il catalogo).
 *
 * Il Model dichiara i suoi campi in {@see mediaFields()}; da lì nascono le
 * colonne, i campi del form ({@see mediaInput()}), la lettura e la
 * migrazione dei file di prima ({@see legacyMediaDefinitions()},
 * {@see LegacyMediaMigration}).
 *
 * Si legge sempre da qui, mai dalla colonna:
 *
 * ```php
 * Logo::mediaUrl($riga, 'main');           // indirizzo, '' se non c'è
 * Logo::media($riga, 'main');              // MediaFile|null, per Image::fromMedia()
 * Logo::mediaMany($righe, 'main');         // un elenco: una lettura sola
 * ```
 *
 * **Colonna di prima.** Ogni campo aveva una colonna file con lo stesso nome.
 * Resta nella tabella, deprecata: la migrazione la svuota quando ha creato
 * il media, quindi una riga che la ha ancora piena è una riga non migrata,
 * e per quella riga {@see mediaUrl()} ripiega sul file di prima.
 */
trait HasMedia
{
    /**
     * I campi media del Model: nome del campo (che è anche la colonna file
     * di prima) => `columns` (tipo di media => colonna con l'id, nell'ordine
     * in cui si leggono; più tipi possono avere la stessa colonna), `slug` e `alt` (i modelli del media creato dal
     * campo) e `legacy` (la cartella dei file di prima, sotto
     * `assets/upload`).
     *
     * `extensions` (facoltativo) restringe i file ammessi, es. `['png']`.
     * `owner` (facoltativo) per le righe di un repeater: l'utilizzo dei file
     * di prima si registra sul padre ({@see LegacyMediaMigration}).
     *
     * @return array<string, array{columns: array<string, string>, slug: string, alt: string, legacy: string, name?: string, extensions?: list<string>, owner?: array{table: string, key: string, column: string}}>
     */
    abstract public static function mediaFields(): array;

    /**
     * Il campo del form: il drag & drop del core collegato alla libreria
     * ({@see InputFileDragDrop::mediaLibrary()}) sulla colonna con l'id, con
     * i tipi, lo slug e il testo alternativo del campo. Il file caricato
     * diventa un media e il ridimensionatore lo prepara.
     *
     * `$type` sceglie la colonna quando il campo ne ha più d'una; senza,
     * quella del primo tipo.
     */
    public static function mediaInput(string $field, ?string $type = null): InputFileDragDrop
    {
        $config = static::mediaFields()[$field] ?? null;
        $columns = (array) ($config['columns'] ?? []);
        $column = $type === null ? (reset($columns) ?: null) : ($columns[$type] ?? null);

        if ($column === null) {
            throw new InvalidArgumentException('Il campo '.$field.' di '.static::class.' non ha media'.($type === null ? '' : ' di tipo '.$type).'.');
        }

        // I tipi che finiscono nella stessa colonna: un logo può essere svg o raster.
        $types = array_keys(array_filter($columns, static fn (string $value): bool => $value === $column));
        $accept = in_array(MediaTypes::IMAGE, $types, true) ? 'image' : ($types[0] === MediaTypes::ICON ? 'svg' : $types[0]);

        $input = FormField::key($column)->fileDragDrop($accept)
            ->mediaLibrary()
            ->mediaTypes(...$types)
            ->mediaSlug($config['slug']);

        if (!empty($config['extensions'])) {
            $input->mediaExtensions(...(array) $config['extensions']);
        }

        return $config['alt'] !== '' ? $input->mediaAlt($config['alt']) : $input;
    }

    /**
     * I campi come li vuole {@see LegacyMediaMigration::run()}: uno per
     * campo, con la tabella del Model.
     *
     * @return list<array<string, mixed>>
     */
    public static function legacyMediaDefinitions(): array
    {
        $definitions = [];

        foreach (static::mediaFields() as $field => $config) {
            $definitions[] = ['table' => (string) static::$table, 'field' => $field] + $config;
        }

        return $definitions;
    }

    /** L'id del media di un campo: la prima colonna piena, 0 se nessuna. */
    public static function mediaId(array $row, string $field): int
    {
        foreach (array_unique(static::mediaFields()[$field]['columns'] ?? []) as $column) {
            $id = $row[$column] ?? null;

            if (is_scalar($id) && (int) $id > 0) {
                return (int) $id;
            }
        }

        return 0;
    }

    /** Il media di un campo, null se la riga non ne ha o non c'è più. */
    public static function media(array $row, string $field): ?MediaFile
    {
        $id = static::mediaId($row, $field);

        return $id > 0 ? MediaFile::find($id) : null;
    }

    /**
     * I media di un campo per più righe, con una lettura sola. Le chiavi
     * sono quelle di `$rows`; chi non ha un media manca dal risultato.
     *
     * @param array<int|string, array<string, mixed>> $rows
     * @return array<int|string, MediaFile>
     */
    public static function mediaMany(array $rows, string $field): array
    {
        $ids = [];

        foreach ($rows as $key => $row) {
            $id = is_array($row) ? static::mediaId($row, $field) : 0;

            if ($id > 0) {
                $ids[$key] = $id;
            }
        }

        $found = MediaFile::many(array_values($ids));
        $media = [];

        foreach ($ids as $key => $id) {
            if (isset($found[$id])) {
                $media[$key] = $found[$id];
            }
        }

        return $media;
    }

    /**
     * L'indirizzo del file di un campo, vuoto se non c'è. Passare il media
     * già letto con {@see mediaMany()} evita una lettura per riga.
     */
    public static function mediaUrl(array $row, string $field, ?MediaFile $media = null): string
    {
        if (static::mediaId($row, $field) > 0) {
            return (string) ($media ?? static::media($row, $field))?->url();
        }

        return static::legacyMediaUrl($row, $field);
    }

    /**
     * L'indirizzo del file nella colonna di prima, per le righe non ancora
     * migrate.
     *
     * @deprecated solo per il passaggio alla libreria Media.
     */
    public static function legacyMediaUrl(array $row, string $field): string
    {
        $config = static::mediaFields()[$field] ?? null;
        $name = trim((string) (MediaFileManager::decodeStoredFiles($row[$field] ?? '')[0] ?? ''));

        if ($config === null || $name === '') {
            return '';
        }

        if (filter_var($name, FILTER_VALIDATE_URL) !== false) {
            return $name;
        }

        try {
            $upload = rtrim((string) (new Path())->upload, '/');
        } catch (Throwable) {
            // Fuori dal sito avviato (test, comandi) l'indirizzo è quello relativo.
            $upload = '/assets/upload';
        }

        return $upload.'/'.trim($config['legacy'], '/').'/'.ltrim($name, '/');
    }

    /**
     * Le colonne con l'id dei media: un intero che può restare vuoto.
     *
     * @return list<Column>
     */
    protected static function mediaTableColumns(): array
    {
        $columns = [];

        foreach (static::mediaColumns() as $column) {
            $columns[] = Column::key($column)->int()->null();
        }

        return $columns;
    }

    /** @return list<object> i campi dei dati delle colonne con l'id */
    protected static function mediaDataFields(): array
    {
        $fields = [];

        foreach (static::mediaColumns() as $column) {
            $fields[] = Field::key($column)->number();
        }

        return $fields;
    }

    /**
     * Le colonne con l'id, una volta sola anche se più tipi ci finiscono.
     *
     * @return list<string>
     */
    public static function mediaColumns(): array
    {
        $columns = [];

        foreach (static::mediaFields() as $config) {
            array_push($columns, ...array_values($config['columns']));
        }

        return array_values(array_unique($columns));
    }
}
