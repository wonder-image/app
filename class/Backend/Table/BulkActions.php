<?php

namespace Wonder\Backend\Table;

use RuntimeException;
use Throwable;
use Wonder\App\LegacyGlobals;
use Wonder\App\ResourceRegistry;
use Wonder\App\Visibility\RowVisibility;
use Wonder\Sql\Transaction;

/**
 * Selezione multipla nelle liste backend: la colonna delle checkbox, la
 * barra delle azioni sopra la tabella e l'esecuzione di un'azione sugli id
 * scelti (vedi {@see BulkAction} e `/api/backend/bulk/`).
 *
 * Il JS sta nella lib (`backend/js/bulk.js`): si aggancia agli attributi
 * `data-wi-bulk*` stampati qui. La selezione vale per la pagina visibile e
 * si svuota a ogni ridisegno della tabella.
 *
 * Tutto è puro tranne {@see visibleIds()} e {@see run()}.
 */
final class BulkActions
{
    /** Il nome della colonna delle checkbox nella config della tabella. */
    public const COLUMN = 'row-select';

    /** Righe al massimo per richiesta (come il riordino). */
    public const MAX = 500;

    /** Prefisso delle voci di riga nella colonna `menu`. */
    public const ROW_PREFIX = 'bulk:';

    /** La colonna dei segnali di riga, se c'è, resta la prima. */
    private const LEADING = 'row_signals';

    /**
     * Le azioni dichiarate, per chiave. Le chiavi doppie sono un errore.
     *
     * @return array<string, BulkAction>
     */
    public static function normalize(mixed $actions): array
    {
        $list = [];

        foreach (is_array($actions) ? $actions : [] as $action) {
            if (!$action instanceof BulkAction) {
                continue;
            }

            if (isset($list[$action->key()])) {
                throw new RuntimeException("Azione di massa doppia: {$action->key()}");
            }

            $list[$action->key()] = $action;
        }

        return $list;
    }

    /**
     * Gli id inviati: interi positivi, senza doppioni, nell'ordine arrivato.
     * null se sono più di {@see MAX}.
     *
     * @return list<int>|null
     */
    public static function ids(mixed $raw): ?array
    {
        $ids = [];

        foreach (is_array($raw) ? $raw : [] as $value) {
            $id = is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : 0;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return count($ids) > self::MAX ? null : array_values($ids);
    }

    /**
     * Dove va la colonna fra quelle della tableSchema: dopo `row_signals`
     * se è la prima, altrimenti in testa (la maniglia del riordino è già
     * prima di tutte).
     *
     * @param list<int|string> $keys
     */
    public static function position(array $keys): int
    {
        return ($keys[0] ?? null) === self::LEADING ? 1 : 0;
    }

    /** La checkbox di una riga. */
    public static function checkbox(int|string $id): string
    {
        $label = self::text('bulk.select_row', 'Seleziona la riga');

        return '<input class="form-check-input m-0" type="checkbox" data-wi-bulk-id="'.(int) $id.'" aria-label="'.self::e($label).'">';
    }

    /** La checkbox dell'intestazione: seleziona tutta la pagina. */
    public static function header(): string
    {
        $label = self::text('bulk.select_all', 'Seleziona tutte le righe della pagina');

        return '<input class="form-check-input m-0" type="checkbox" data-wi-bulk-all aria-label="'.self::e($label).'">';
    }

    /**
     * La barra sopra la tabella, nascosta finché non si sceglie una riga.
     *
     * @param array<string, BulkAction> $actions
     */
    public static function bar(string $tableId, string $endpoint, string $resource, array $actions): string
    {
        $buttons = '';

        foreach ($actions as $action) {
            $icon = $action->getIcon() !== '' ? '<i class="'.self::e($action->getIcon()).'"></i> ' : '';
            $confirm = $action->getConfirm() !== null
                ? ' data-wi-confirm="'.self::e($action->getConfirm()).'"'.($action->getVariant() === 'danger' ? ' data-wi-confirm-variant="danger"' : '')
                : '';

            $buttons .= '<button type="button" class="btn btn-sm btn-outline-'.self::e($action->getVariant()).'"'
                .' data-wi-bulk-action="'.self::e($action->key()).'"'.$confirm.'>'
                .$icon.self::e($action->getLabel()).'</button>';
        }

        return '<div class="d-none mb-2" data-wi-bulk="'.self::e($tableId).'"'
            .' data-wi-bulk-endpoint="'.self::e($endpoint).'"'
            .' data-wi-bulk-resource="'.self::e($resource).'">'
            .'<div class="d-flex flex-wrap align-items-center gap-2">'
            .'<span class="small text-body-secondary me-1" aria-live="polite" data-wi-bulk-count'
            .' data-wi-bulk-one="'.self::e(self::text('bulk.selected_one', '1 selezionato')).'"'
            .' data-wi-bulk-many="'.self::e(self::text('bulk.selected_many', '{{count}} selezionati')).'"></span>'
            .$buttons
            .'<button type="button" class="btn btn-sm btn-link" data-wi-bulk-clear>'.self::e(self::text('bulk.clear', 'Deseleziona')).'</button>'
            .'</div></div>';
    }

    /**
     * Le voci del menu di riga per le azioni con `row()`, pronte per la
     * colonna `menu`: viaggiano nel POST della lista, quindi solo chiave e
     * slug, l'azione la ritrova {@see rowMenuItem()} sul server.
     *
     * @param array<string, BulkAction> $actions già filtrate per permesso
     * @return array<string, array{bulk: string, resource: string}>
     */
    public static function rowEntries(array $actions, string $resource): array
    {
        $entries = [];

        foreach ($actions as $action) {
            if ($action instanceof BulkAction && $action->inRow()) {
                $entries[self::ROW_PREFIX.$action->key()] = ['bulk' => $action->key(), 'resource' => $resource];
            }
        }

        return $entries;
    }

    /**
     * La voce del menu di riga, se l'azione esiste, l'utente la può usare e
     * `row()` la mostra per questa riga; altrimenti ''.
     *
     * @param array<string, mixed> $entry una voce di {@see rowEntries()}
     * @param array<string, mixed> $row
     */
    public static function rowMenuItem(array $entry, array $row): string
    {
        $action = self::rowAction((string) ($entry['resource'] ?? ''), (string) ($entry['bulk'] ?? ''));

        return $action !== null && $action->showsInRow($row) ? self::rowItem($action, $row['id'] ?? 0) : '';
    }

    /** La voce del dropdown: bulk.js la manda con la barra della tabella. */
    public static function rowItem(BulkAction $action, int|string $id): string
    {
        $confirm = $action->getConfirm() !== null
            ? ' data-wi-confirm="'.self::e($action->getConfirm()).'"'.($action->getVariant() === 'danger' ? ' data-wi-confirm-variant="danger"' : '')
            : '';
        $color = $action->getVariant() === 'danger' ? ' text-danger' : '';
        $icon = $action->getIcon() !== '' ? '<i class="'.self::e($action->getIcon()).' me-1"></i>' : '';

        return '<a class="dropdown-item'.$color.'" role="button" href="#"'
            .' data-wi-bulk-row="'.(int) $id.'" data-wi-bulk-action="'.self::e($action->key()).'"'.$confirm.'>'
            .$icon.self::e($action->getLabel()).'</a>';
    }

    /** @var array<string, array<string, BulkAction>> azioni di riga permesse, per slug */
    private static array $rowActions = [];

    /** Azione di riga di una Resource, se l'utente corrente la può usare. */
    private static function rowAction(string $slug, string $key): ?BulkAction
    {
        if (!isset(self::$rowActions[$slug])) {
            self::$rowActions[$slug] = [];

            try {
                $resourceClass = ResourceRegistry::resolve($slug);

                if (!$resourceClass::isReadonly()) {
                    self::$rowActions[$slug] = self::allowedActions(
                        self::normalize($resourceClass::tableLayoutSchema()->get('bulk_actions')),
                        $resourceClass,
                        TableAuthorization::authoritiesOf(LegacyGlobals::get('USER'))
                    );
                }
            } catch (Throwable) {
                // Resource sconosciuta o schema rotto: nessuna voce.
            }
        }

        return self::$rowActions[$slug][$key] ?? null;
    }

    /**
     * Il ritorno del gestore, ridotto agli id chiesti: un intero (o null)
     * vuol dire tutti fatti; gli id né fatti né falliti contano come fatti
     * solo se il gestore non ha restituito un elenco.
     *
     * @param list<int> $ids
     * @return array{done: list<int>, failed: array<int, string>}
     */
    public static function result(mixed $value, array $ids): array
    {
        if (!is_array($value)) {
            return ['done' => $ids, 'failed' => []];
        }

        $allowed = array_flip($ids);
        $failed = [];

        foreach ((array) ($value['failed'] ?? []) as $id => $message) {
            if (isset($allowed[(int) $id])) {
                $failed[(int) $id] = trim((string) $message) !== '' ? (string) $message : self::text('bulk.failed', 'Operazione non riuscita.');
            }
        }

        $done = [];

        foreach ((array) ($value['done'] ?? []) as $id) {
            $id = (int) $id;

            if (isset($allowed[$id]) && !isset($failed[$id])) {
                $done[$id] = $id;
            }
        }

        return ['done' => array_values($done), 'failed' => $failed];
    }

    /**
     * La risposta per il browser: quante fatte, quante no, e i messaggi
     * delle fallite, uno per testo. `{{rows}}` nel messaggio diventa
     * «1 riga» / «N righe»; senza segnaposto il conteggio va in coda, «(N)».
     *
     * @param array{done: list<int>, failed: array<int, string>} $result
     * @return array{done: int, failed: int, messages: list<string>}
     */
    public static function response(array $result): array
    {
        $messages = [];

        foreach ($result['failed'] as $message) {
            $messages[$message] = ($messages[$message] ?? 0) + 1;
        }

        $list = [];

        foreach ($messages as $message => $count) {
            $message = (string) $message;

            if (str_contains($message, '{{rows}}')) {
                $list[] = str_replace('{{rows}}', self::rows($count), $message);
            } else {
                $list[] = $count > 1 ? $message.' ('.$count.')' : $message;
            }
        }

        return [
            'done' => count($result['done']),
            'failed' => count($result['failed']),
            'messages' => $list,
        ];
    }

    /** «1 riga» / «N righe», dalle traduzioni se ci sono. */
    private static function rows(int $count): string
    {
        $key = $count === 1 ? 'bulk.rows_one' : 'bulk.rows_many';
        $text = function_exists('__t') ? (string) __t($key, ['count' => (string) $count]) : '';

        if ($text === '' || $text === $key) {
            $text = $count === 1 ? '1 riga' : '{{count}} righe';
        }

        return str_replace('{{count}}', (string) $count, $text);
    }

    /**
     * Gli id che esistono, rispettano la condizione della lista e l'utente
     * vede. Gli altri spariscono senza errore.
     *
     * @param list<int> $ids
     * @return list<int>
     */
    public static function visibleIds(string $table, array $ids, string|array|null $condition, ?object $user): array
    {
        if ($ids === []) {
            return [];
        }

        $sql = '`id` IN ('.implode(', ', array_map('intval', $ids)).')';
        $scope = RowVisibility::applySql($table, $condition, $user);

        if (trim($scope) !== '') {
            $sql .= ' AND ('.$scope.')';
        }

        $found = [];
        $query = sqlSelect($table, $sql, null, null, null, 'id');
        $rows = (array) ($query->row ?? []);

        // Una riga sola può arrivare senza elenco attorno.
        if (isset($rows['id'])) {
            $rows = [$rows];
        }

        foreach ($rows as $row) {
            $found[(int) ($row['id'] ?? 0)] = true;
        }

        return array_values(array_filter($ids, static fn (int $id): bool => isset($found[$id])));
    }

    /**
     * Esegue l'azione sugli id (già filtrati): dentro una transazione se
     * l'azione la vuole. Un'eccezione del gestore fa fallire tutti gli id
     * con il suo messaggio.
     *
     * @param list<int> $ids
     * @return array{done: list<int>, failed: array<int, string>}
     */
    public static function run(BulkAction $action, string $resourceClass, array $ids, ?object $user): array
    {
        $handler = $action->getHandler();

        if ($handler === null || $ids === []) {
            return ['done' => [], 'failed' => []];
        }

        $call = static fn (): mixed => $handler($ids, $resourceClass, $user);

        try {
            $value = $action->usesTransaction() ? Transaction::run($call) : $call();
        } catch (Throwable $error) {
            $message = $error instanceof RuntimeException && $error->getMessage() !== ''
                ? $error->getMessage()
                : self::text('bulk.failed', 'Operazione non riuscita.');

            return ['done' => [], 'failed' => array_fill_keys($ids, $message)];
        }

        return self::result($value, $ids);
    }

    /**
     * L'utente può usare l'azione? Il permesso lo dà la Resource indicata
     * in `permission()`, altrimenti quella della lista.
     *
     * @param list<string> $authorities
     */
    public static function allowed(BulkAction $action, string $resourceClass, array $authorities): bool
    {
        $owner = $action->getPermissionResource() ?? $resourceClass;

        return is_subclass_of($owner, \Wonder\App\Resource::class)
            && $owner::allows($authorities, $action->getPermission());
    }

    /**
     * Solo le azioni che l'utente può usare.
     *
     * @param array<string, BulkAction> $actions
     * @param list<string> $authorities
     * @return array<string, BulkAction>
     */
    public static function allowedActions(array $actions, string $resourceClass, array $authorities): array
    {
        return array_filter($actions, static fn (BulkAction $action): bool => self::allowed($action, $resourceClass, $authorities));
    }

    private static function text(string $key, string $fallback): string
    {
        if (!function_exists('__t')) {
            return $fallback;
        }

        $value = __t($key);

        return is_string($value) && $value !== '' && $value !== $key ? $value : $fallback;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
