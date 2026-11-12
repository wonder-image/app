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
