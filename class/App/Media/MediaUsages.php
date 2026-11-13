<?php

namespace Wonder\App\Media;

use Closure;
use Throwable;
use Wonder\App\ResourceRegistry;

/**
 * Registro degli utilizzi della libreria Media: chi usa quale media.
 *
 * Chi salva l'id di un media in un record lo dichiara con `sync()` dopo il
 * salvataggio e con `forget()` quando il record viene eliminato. Il
 * registro blocca l'eliminazione di un media in uso
 * (`MediaResource::assertDeletable()`) e riempie il filtro "usata / non
 * usata" degli elenchi di Media.
 *
 * Nessuna query finché non si chiama uno di questi metodi: lo store si
 * crea e si collega al database solo al primo uso.
 */
final class MediaUsages
{
    /** Utilizzi elencati per esteso in `message()`; gli altri si contano. */
    public const MESSAGE_LIMIT = 5;

    /** Colonne da cui prendere il nome di un record, in ordine. */
    private const NAME_COLUMNS = ['name', 'title', 'label', 'code', 'slug'];

    private static ?Closure $factory = null;
    private static ?MediaUsageStore $store = null;
    private static ?Closure $describer = null;

    /** @var array<string, string> */
    private static array $sectionLabels = [];

    public static function store(): MediaUsageStore
    {
        return self::$store ??= self::$factory !== null ? (self::$factory)() : new DatabaseMediaUsageStore();
    }

    /** @param MediaUsageStore|Closure(): MediaUsageStore|null $store null torna al database */
    public static function useStore(MediaUsageStore|Closure|null $store): void
    {
        self::$store = $store instanceof MediaUsageStore ? $store : null;
        self::$factory = $store instanceof Closure ? $store : null;
    }

    /**
     * Sostituisce il modo in cui `of()` descrive un utilizzo.
     *
     * @param Closure(array{table: string, id: string, column: string}): array<string, mixed>|null $describer
     *        null torna alla descrizione dalla Resource
     */
    public static function describeUsing(?Closure $describer): void
    {
        self::$describer = $describer;
    }

    /**
     * I media usati da `$column` del record `$rowId` di `$table` diventano
     * `$mediaIds`: quelli di prima che non ci sono più si tolgono. Un array
     * vuoto li toglie tutti. Gli id non validi e i doppioni si scartano.
     *
     * @param array<int, int|string|null> $mediaIds
     */
    public static function sync(string $table, int|string $rowId, string $column, array $mediaIds): void
    {
        $table = trim($table);
        $column = trim($column);
        $rowId = trim((string) $rowId);

        if ($table === '' || $column === '' || $rowId === '') {
            return;
        }

        self::store()->replace($table, $rowId, $column, self::ids($mediaIds));
    }

    /** Il record è stato eliminato: non usa più nessun media. */
    public static function forget(string $table, int|string $rowId): void
    {
        $table = trim($table);
        $rowId = trim((string) $rowId);

        if ($table === '' || $rowId === '') {
            return;
        }

        self::store()->forget($table, $rowId);
    }

    public static function inUse(int|string $mediaId): bool
    {
        $mediaId = self::id($mediaId);

        return $mediaId !== null && self::store()->used($mediaId);
    }

    /**
     * Chi usa il media, senza la descrizione per le persone: nessuna
     * lettura delle Resource e dei record.
     *
     * @return list<array{table: string, id: string, column: string}>
     */
    public static function owners(int|string $mediaId): array
    {
        $mediaId = self::id($mediaId);

        return $mediaId === null ? [] : self::store()->owners($mediaId);
    }

    /**
     * Dove è usato il media. Ogni riga ha `table`, `id`, `column` e la
     * descrizione per le persone: `resource` (classe o null), `label` (nome
     * della Resource, altrimenti la tabella), `name` (nome del record,
     * altrimenti `#id`), `field` (etichetta del campo, anche vuota) e `url`
     * (pagina di modifica o null).
     *
     * @return list<array{table: string, id: string, column: string, resource: ?string, label: string, name: string, field: string, url: ?string}>
     */
    public static function of(int|string $mediaId): array
    {
        $mediaId = self::id($mediaId);

        if ($mediaId === null) {
            return [];
        }

        $describe = self::$describer ?? self::describe(...);
        $usages = [];

        foreach (self::store()->owners($mediaId) as $owner) {
            $usages[] = array_merge(self::fallback($owner), (array) $describe($owner), $owner);
        }

        return $usages;
    }

    /**
     * Frase che dice dove è usato il media, stringa vuota se non è usato.
     * Solo testo: i nomi dei record perdono i tag e si accorciano.
     */
    public static function message(int|string $mediaId): string
    {
        $usages = self::of($mediaId);

        if ($usages === []) {
            return '';
        }

        $places = [];

        foreach (array_slice($usages, 0, self::MESSAGE_LIMIT) as $usage) {
            $place = trim((string) $usage['label'].' «'.self::plain((string) $usage['name']).'»');

            if ((string) $usage['field'] !== '') {
                $place .= ' (campo '.self::plain((string) $usage['field']).')';
            }

            $places[] = $place;
        }

        $more = count($usages) - count($places);
        $list = implode(', ', $places);

        if ($more > 0) {
            $list .= $more === 1 ? ' e in un altro punto' : " e in altri {$more} punti";
        }

        return 'In uso in: '.$list.'.';
    }

    /**
     * Condizione SQL per il filtro "usata / non usata" di un elenco di Media.
     * Riceve solo `used` e `unused`; con tutti e due, o nessuno, non filtra.
     *
     * @param array<int, string> $values
     */
    public static function filterCondition(array $values, string $mediaTable = 'media'): string
    {
        $used = in_array('used', $values, true);
        $unused = in_array('unused', $values, true);

        if ($used === $unused) {
            return '';
        }

        $usages = \Wonder\App\Models\Media\MediaUsage::$table;
        $mediaTable = str_replace('`', '', $mediaTable);
        $exists = "EXISTS (SELECT 1 FROM `{$usages}` WHERE `{$usages}`.`media_id` = `{$mediaTable}`.`id`)";

        return $used ? $exists : 'NOT '.$exists;
    }

    /**
     * Le sezioni che usano media (di `$mediaType`, se dato): tabella =>
     * nome della sezione. Vuoto se il registro non risponde.
     *
     * @return array<string, string>
     */
    public static function sections(?string $mediaType = null): array
    {
        try {
            $tables = self::store()->tables($mediaType);
        } catch (Throwable) {
            return [];
        }

        $sections = [];

        foreach ($tables as $table) {
            $table = trim((string) $table);

            if (self::validTable($table)) {
                $sections[$table] = self::sectionLabel($table);
            }
        }

        return $sections;
    }

    /** Il nome della sezione di una tabella: la Resource al plurale, altrimenti la tabella. */
    public static function sectionLabel(string $table): string
    {
        if (array_key_exists($table, self::$sectionLabels)) {
            return self::$sectionLabels[$table];
        }

        $label = $table;

        try {
            $resourceClass = ResourceRegistry::resolveByTable($table);

            if ($resourceClass !== null) {
                $plural = trim((string) $resourceClass::pluralLabel());
                $label = $plural !== '' ? (function_exists('mb_strtoupper')
                    ? mb_strtoupper(mb_substr($plural, 0, 1)).mb_substr($plural, 1)
                    : ucfirst($plural)) : $table;
            }
        } catch (Throwable) {
        }

        return self::$sectionLabels[$table] = $label;
    }

    /**
     * Condizione SQL per il filtro "Sezione" di un elenco di Media: i media
     * usati da almeno una delle tabelle scelte. Passano solo le tabelle di
     * `$allowed` (le opzioni del filtro); nessuna, nessun filtro.
     *
     * @param array<int, string> $values
     * @param array<int, string> $allowed
     */
    public static function sectionCondition(array $values, array $allowed, string $mediaTable = 'media'): string
    {
        $tables = array_values(array_filter(
            array_unique(array_map(static fn ($value): string => is_scalar($value) ? trim((string) $value) : '', $values)),
            static fn (string $table): bool => in_array($table, $allowed, true) && self::validTable($table)
        ));

        if ($tables === []) {
            return '';
        }

        $usages = \Wonder\App\Models\Media\MediaUsage::$table;
        $mediaTable = str_replace('`', '', $mediaTable);
        $list = implode(', ', array_map(static fn (string $table): string => "'{$table}'", $tables));

        return "EXISTS (SELECT 1 FROM `{$usages}` WHERE `{$usages}`.`media_id` = `{$mediaTable}`.`id` AND `{$usages}`.`owner_table` IN ({$list}))";
    }

    /**
     * Colonna calcolata per l'elenco di Media: le tabelle che usano il
     * media, separate da virgola (NULL se nessuna).
     */
    public static function tablesSelect(string $alias, string $mediaTable = 'media'): string
    {
        $usages = \Wonder\App\Models\Media\MediaUsage::$table;
        $mediaTable = str_replace('`', '', $mediaTable);
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias);

        return "(SELECT GROUP_CONCAT(DISTINCT `{$usages}`.`owner_table` ORDER BY `{$usages}`.`owner_table` SEPARATOR ',') "
            ."FROM `{$usages}` WHERE `{$usages}`.`media_id` = `{$mediaTable}`.`id`) AS `{$alias}`";
    }

    /** Solo nomi di tabella semplici: finiscono dentro una query. */
    private static function validTable(string $table): bool
    {
        return preg_match('/^[A-Za-z0-9_]{1,64}$/D', $table) === 1;
    }

    /** @return array{resource: ?string, label: string, name: string, field: string, url: ?string} */
    private static function fallback(array $owner): array
    {
        return [
            'resource' => null,
            'label' => (string) $owner['table'],
            'name' => '#'.$owner['id'],
            'field' => '',
            'url' => null,
        ];
    }

    /** Descrizione dalla Resource della tabella; vuota se non c'è o se fallisce. */
    private static function describe(array $owner): array
    {
        try {
            $resourceClass = ResourceRegistry::resolveByTable((string) $owner['table']);

            if ($resourceClass === null) {
                return [];
            }

            $description = [
                'resource' => $resourceClass,
                'label' => (string) $resourceClass::label(),
                'field' => (string) $resourceClass::getLabel((string) $owner['column']),
            ];

            $row = $resourceClass::modelClass()::findById($owner['id']);

            foreach (is_array($row) ? self::NAME_COLUMNS : [] as $column) {
                if (is_scalar($row[$column] ?? null) && trim((string) $row[$column]) !== '') {
                    $description['name'] = trim((string) $row[$column]);
                    break;
                }
            }

            $url = function_exists('__r')
                ? (string) __r('backend.resource.'.$resourceClass::slug().'.update', ['id' => $owner['id']])
                : '';

            if ($url !== '') {
                $description['url'] = $url;
            }

            return $description;
        } catch (Throwable) {
            return [];
        }
    }

    private static function plain(string $text): string
    {
        $text = trim(str_replace(['<', '>'], '', strip_tags($text)));

        return function_exists('mb_strimwidth') ? mb_strimwidth($text, 0, 60, '…', 'UTF-8') : substr($text, 0, 60);
    }

    /** @return list<int> */
    private static function ids(array $mediaIds): array
    {
        $ids = [];

        foreach ($mediaIds as $mediaId) {
            $id = is_int($mediaId) || is_string($mediaId) ? self::id($mediaId) : null;

            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function id(int|string $mediaId): ?int
    {
        if (is_string($mediaId) && preg_match('/^\d+$/D', trim($mediaId)) !== 1) {
            return null;
        }

        $id = (int) (is_string($mediaId) ? trim($mediaId) : $mediaId);

        return $id > 0 ? $id : null;
    }
}
