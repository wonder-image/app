<?php

namespace Wonder\Backend\Table;

use RuntimeException;
use Wonder\App\Resource;
use Wonder\App\Visibility\RowVisibility;
use Wonder\Backend\Support\RowDrag;
use Wonder\Sql\Query;
use Wonder\Sql\Transaction;

/**
 * Le righe di una lista backend da riordinare trascinandole.
 *
 * La Resource lo chiede con `TableLayoutSchema::reorderable($column, $group)`.
 * La lista aggiunge a sinistra la colonna {@see COLUMN} con la maniglia di
 * {@see RowDrag}, visibile solo quando la lista è ordinata per `$column`
 * dal basso in alto ({@see active()}). Lasciata la riga, il browser manda a
 * `/api/backend/reorder/` la riga spostata e gli id della pagina nell'ordine
 * nuovo; il server ({@see apply()}) la mette accanto alla vicina più vicina
 * dello stesso gruppo e riscrive `$column` da 1 in su per tutto il gruppo,
 * togliendo i buchi lasciati dalle righe eliminate.
 *
 * Funziona anche con ricerca, filtri e paginazione: la riga va prima o dopo
 * una riga che si vede, e le altre restano dove sono. Fra due pagine non si
 * trascina: si allarga la pagina.
 *
 * {@see config()}, {@see active()} e {@see move()} sono pure.
 */
final class Reorder
{
    /** Il nome della colonna della maniglia nella config della tabella. */
    public const COLUMN = 'row-drag';

    /** La posizione di una riga che non ne ha: dopo tutte le altre. */
    private const UNPLACED = 2147483647;

    /**
     * La config di `reorderable()`, ripulita; null se spenta o non valida.
     *
     * @return array{column: string, group: ?string}|null
     */
    public static function config(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $column = is_string($value['column'] ?? null) ? $value['column'] : '';
        $group = is_string($value['group'] ?? null) && $value['group'] !== '' ? $value['group'] : null;

        foreach ([$column, $group ?? 'id'] as $name) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
                return null;
            }
        }

        return ['column' => $column, 'group' => $group];
    }

    /** La maniglia si vede solo con la lista ordinata per la posizione, dal basso in alto. */
    public static function active(?array $config, string $orderColumn, string $orderDirection): bool
    {
        return $config !== null
            && $orderColumn === $config['column']
            && strtolower(trim($orderDirection)) !== 'desc';
    }

    /** La maniglia di una riga, con il suo id per lo script. */
    public static function handle(int|string $id): string
    {
        return RowDrag::handle(['data-wi-reorder-id' => (int) $id]);
    }

    /**
     * L'ordine nuovo di un gruppo dopo lo spostamento di `$id`.
     *
     * `$group` sono gli id del gruppo nell'ordine di oggi, `$page` quelli che
     * la pagina mostra dopo lo spostamento. La riga va subito dopo la prima
     * riga del gruppo che la precede nella pagina; se non ce n'è, subito
     * prima della prima che la segue. Null se non c'è niente da cambiare: la
     * riga non è del gruppo o non è nella pagina, nessuna vicina del suo
     * gruppo si vede, o l'ordine resta lo stesso.
     *
     * @param list<int|string> $group
     * @param list<int|string> $page
     * @return list<int>|null
     */
    public static function move(array $group, int $id, array $page): ?array
    {
        $group = array_values(array_map('intval', $group));
        $page = array_values(array_map('intval', $page));
        $members = array_flip($group);
        $index = array_search($id, $page, true);

        if (!isset($members[$id]) || $index === false) {
            return null;
        }

        $after = 0;
        $before = 0;

        for ($k = $index - 1; $k >= 0 && $after === 0; $k--) {
            $after = isset($members[$page[$k]]) && $page[$k] !== $id ? $page[$k] : 0;
        }

        for ($k = $index + 1, $n = count($page); $after === 0 && $k < $n && $before === 0; $k++) {
            $before = isset($members[$page[$k]]) && $page[$k] !== $id ? $page[$k] : 0;
        }

        if ($after === 0 && $before === 0) {
            return null;
        }

        $rest = array_values(array_filter($group, static fn (int $other): bool => $other !== $id));
        $at = $after !== 0
            ? (int) array_search($after, $rest, true) + 1
            : (int) array_search($before, $rest, true);
        array_splice($rest, $at, 0, [$id]);

        return $rest === $group ? null : $rest;
    }

    /**
     * Le posizioni da scrivere, `id => posizione` da 1: solo quelle che
     * cambiano rispetto a `$current` (`id => posizione di oggi`).
     *
     * @param list<int> $ordered
     * @param array<int, mixed> $current
     * @return array<int, int>
     */
    public static function positions(array $ordered, array $current): array
    {
        $out = [];

        foreach (array_values($ordered) as $index => $id) {
            $position = $index + 1;

            if (!array_key_exists($id, $current) || (string) $current[$id] !== (string) $position) {
                $out[$id] = $position;
            }
        }

        return $out;
    }

    /**
     * Sposta una riga della lista di una Resource e riscrive le posizioni
     * del suo gruppo. Restituisce quante righe sono cambiate.
     *
     * Il gruppo sono le righe della lista (`querySchema()` della Resource,
     * cioè di solito quelle non cancellate) che l'utente vede e che hanno lo
     * stesso valore della colonna di gruppo. Chi chiama ha già controllato
     * permessi e visibilità della riga.
     *
     * @param class-string<Resource> $resourceClass
     * @param list<int|string> $page gli id della pagina, nell'ordine nuovo
     */
    public static function apply(string $resourceClass, int $id, array $page, ?object $user = null): int
    {
        $config = self::config($resourceClass::tableLayoutSchema()->get('reorder'));

        if ($config === null) {
            throw new RuntimeException('Questa lista non si riordina.');
        }

        $table = $resourceClass::modelTable();
        $column = Query::escapeIdentifier($config['column']);
        $row = sqlSelect($table, ['id' => $id], 1)->row ?? null;

        if (!is_array($row) || $row === []) {
            throw new RuntimeException('Riga non trovata.');
        }

        $where = [];
        $condition = $resourceClass::querySchema()['condition'] ?? null;

        if ($condition !== null && $condition !== '' && $condition !== []) {
            $where[] = '('.RowVisibility::toSql($condition).')';
        }

        if ($config['group'] !== null) {
            $groupColumn = Query::escapeIdentifier($config['group']);
            $value = $row[$config['group']] ?? null;
            $where[] = $value === null || $value === '' || (string) $value === '0'
                ? "({$groupColumn} IS NULL OR {$groupColumn} = 0)"
                : $groupColumn." = '".addslashes((string) $value)."'";
        }

        $sql = RowVisibility::applySql($table, $where === [] ? '1 = 1' : implode(' AND ', $where), $user);

        return (int) Transaction::run(static function () use ($table, $sql, $column, $id, $page, $config): int {
            // Bloccate fino alla fine: due spostamenti insieme non si mescolano.
            $rows = sqlSelectForUpdate($table, $sql, null, 'COALESCE('.$column.', '.self::UNPLACED.'), `id`', 'ASC')->row ?? [];
            $rows = is_array($rows) && isset($rows['id']) ? [$rows] : array_values(array_filter((array) $rows, 'is_array'));
            $current = array_column($rows, $config['column'], 'id');
            $ordered = self::move(array_keys($current), $id, $page);

            if ($ordered === null) {
                return 0;
            }

            $changes = self::positions($ordered, $current);

            foreach ($changes as $rowId => $position) {
                sqlModify($table, [$config['column'] => $position], 'id', $rowId);
            }

            return count($changes);
        });
    }

    /**
     * Lo script che lega il trascinamento a una lista: dopo ogni spostamento
     * (anche da tastiera, raccolti per 400 ms) manda la riga e l'ordine della
     * pagina all'endpoint, poi ricarica la lista. Un errore lo mostra il
     * backend e la lista torna com'era.
     */
    public static function script(string $tableId, string $endpoint, string $resource): string
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
        $settings = json_encode(['table' => $tableId, 'endpoint' => $endpoint, 'resource' => $resource], $flags);

        return RowDrag::script().'<script>(function (settings) {'.<<<'JS'

    'use strict';

    function rowId(tr) {
        var handle = tr && tr.querySelector('[data-wi-reorder-id]');

        return handle ? Number(handle.getAttribute('data-wi-reorder-id')) || 0 : 0;
    }

    function reload() {
        if (typeof reloadDataTable === 'function') {
            reloadDataTable('#' + settings.table);
        }
    }

    function bind() {
        var table = document.getElementById(settings.table);
        var body = table && table.tBodies[0];
        var moved = null;
        var timer = null;

        if (!body || !window.wiRowDrag || body.wiReorder) {
            return;
        }

        body.wiReorder = true;

        function save() {
            var tr = moved;
            var id = rowId(tr);
            var csrf = typeof wiCsrfToken === 'function' ? wiCsrfToken(settings.endpoint) : '';
            var page = [];
            var rows = body.rows;
            var k;

            moved = null;

            if (!id) {
                return;
            }

            for (k = 0; k < rows.length; k++) {
                if (rowId(rows[k])) {
                    page.push(rowId(rows[k]));
                }
            }

            $.ajax({
                url: settings.endpoint,
                type: 'POST',
                headers: csrf ? {'X-WI-CSRF': csrf} : {},
                data: {resource: settings.resource, id: id, page: page},
                success: reload,
                error: function (request) {
                    if (typeof ajaxRequestError === 'function') {
                        ajaxRequestError(request);
                    }

                    reload();
                }
            });
        }

        window.wiRowDrag(body, function (tr) {
            moved = tr;
            clearTimeout(timer);
            timer = setTimeout(save, 400);
        });
    }

    window.addEventListener('loaded', bind, {once: true});

    if (document.readyState === 'complete') {
        bind();
    }
JS.'})('.$settings.');</script>';
    }
}
