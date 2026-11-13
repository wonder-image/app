<?php
/** php tests/Backend/Table/BulkActionsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/../../../app/function/backend/plugin.php';

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Backend\Table\BulkAction;
use Wonder\Backend\Table\BulkActions;
use Wonder\Backend\Table\Field;
use Wonder\Backend\Table\ListProvider;

final class BulkActionsTestResource extends Resource
{
}

// La lista concede `update` solo all'amministratore: le azioni le dà un'altra Resource.
final class BulkActionsListOnlyResource extends Resource
{
    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)->backend(['list', 'view'], ['vendite'])->backend(['update'], ['admin']);
    }
}

final class BulkActionsOwnerResource extends Resource
{
    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)->backend(['update'], ['vendite']);
    }
}

$throws = static function (callable $fn): bool {
    try {
        $fn();

        return false;
    } catch (RuntimeException) {
        return true;
    }
};

check('chiavi, varianti e permessi non validi sono rifiutati', fn () =>
    $throws(fn () => BulkAction::make('Pubblica!'))
    && $throws(fn () => BulkAction::make('ok')->variant('rosa'))
    && $throws(fn () => BulkAction::make('ok')->permission('list'))
    && $throws(fn () => BulkAction::toggle('ok', 'active`; DROP', true))
    && BulkAction::make('metti-in_bozza2')->key() === 'metti-in_bozza2'
);

check('bulkActions() salva le azioni, scarta il resto e rifiuta le chiavi doppie', function () use ($throws) {
    $schema = TableLayoutSchema::for(BulkActionsTestResource::class);

    if ($schema->get('bulk_actions') !== []) {
        return false;
    }

    $schema->bulkActions([BulkAction::make('a'), 'b', BulkAction::make('c')]);
    $keys = array_map(static fn (BulkAction $a): string => $a->key(), $schema->get('bulk_actions'));

    return $keys === ['a', 'c']
        && $throws(fn () => TableLayoutSchema::for(BulkActionsTestResource::class)->bulkActions([BulkAction::make('a'), BulkAction::make('a')]));
});

check('gli id: interi positivi, senza doppioni, al massimo 500', fn () =>
    BulkActions::ids(['3', 3, '0', -2, 'x', '7', 1.5, ['9'], '12a']) === [3, 7]
    && BulkActions::ids('3') === []
    && count(BulkActions::ids(range(1, 500))) === 500
    && BulkActions::ids(range(1, 501)) === null
);

check('la colonna va in testa, o subito dopo i segnali di riga', fn () =>
    BulkActions::position(['name', 'row_signals']) === 0
    && BulkActions::position(['row_signals', 'name']) === 1
    && BulkActions::position([]) === 0
);

check('il ritorno del gestore resta dentro gli id chiesti', fn () =>
    BulkActions::result(2, [1, 2]) === ['done' => [1, 2], 'failed' => []]
    && BulkActions::result(null, [4]) === ['done' => [4], 'failed' => []]
    && BulkActions::result(['done' => [1, 99, 2], 'failed' => [2 => 'Stato non valido', 88 => 'x', 3 => '']], [1, 2, 3])
        === ['done' => [1], 'failed' => [2 => 'Stato non valido', 3 => 'Operazione non riuscita.']]
);

check('la risposta conta e raggruppa i messaggi uguali', fn () =>
    BulkActions::response(['done' => [1, 2], 'failed' => [3 => 'No', 4 => 'No', 5 => 'Altro']])
        === ['done' => 2, 'failed' => 3, 'messages' => ['No (2)', 'Altro']]
);

check('{{rows}} nel messaggio diventa il numero di righe', function () {
    $messages = BulkActions::response(['done' => [], 'failed' => [
        3 => 'Non disponibile per {{rows}}.', 4 => 'Non disponibile per {{rows}}.', 5 => 'Sparito per {{rows}}.',
    ]])['messages'];

    return count($messages) === 2
        && str_starts_with($messages[0], 'Non disponibile per 2 ')
        && !str_contains($messages[0], '{{')
        && !str_contains($messages[0], '(2)')
        && str_starts_with($messages[1], 'Sparito per 1 ');
});

check('row(): la voce di riga segue la condizione, che non può rompere il menu', function () {
    $always = BulkAction::make('a')->row();
    $when = BulkAction::make('b')->row(static fn (array $row): bool => ($row['status'] ?? '') === 'pending');
    $broken = BulkAction::make('c')->row(static function (): bool {
        throw new RuntimeException('x');
    });

    return !BulkAction::make('d')->inRow()
        && !BulkAction::make('d')->showsInRow([])
        && $always->showsInRow([])
        && $when->showsInRow(['status' => 'pending'])
        && !$when->showsInRow(['status' => 'confirmed'])
        && !$broken->showsInRow(['status' => 'pending']);
});

check('le voci di riga: solo le azioni con row(), con chiave e slug', fn () =>
    BulkActions::rowEntries([
        'a' => BulkAction::make('a')->row(),
        'b' => BulkAction::make('b'),
    ], 'ordini') === ['bulk:a' => ['bulk' => 'a', 'resource' => 'ordini']]
);

check('la voce del menu porta riga, azione e conferma, tutto escapato', function () {
    $html = BulkActions::rowItem(
        BulkAction::make('cancel')->label('Annulla <b>')->variant('danger')->icon('bi bi-x')->confirm('Sicuro "davvero"?'),
        '7"'
    );
    $plain = BulkActions::rowItem(BulkAction::make('ok')->label('Conferma'), 9);

    return str_contains($html, 'class="dropdown-item text-danger"')
        && str_contains($html, 'data-wi-bulk-row="7" data-wi-bulk-action="cancel"')
        && str_contains($html, 'data-wi-confirm="Sicuro &quot;davvero&quot;?" data-wi-confirm-variant="danger"')
        && str_contains($html, '<i class="bi bi-x me-1"></i>Annulla &lt;b&gt;</a>')
        && $plain === '<a class="dropdown-item" role="button" href="#" data-wi-bulk-row="9" data-wi-bulk-action="ok">Conferma</a>';
});

check('una voce di una Resource sconosciuta non si vede', fn () =>
    BulkActions::rowMenuItem(['bulk' => 'a', 'resource' => 'non-esiste'], ['id' => 1]) === ''
);

check('un gestore che lancia fa fallire tutte le righe con il suo messaggio', function () {
    $action = BulkAction::make('x')->transaction(false)->handle(static function (): never {
        throw new RuntimeException('Bloccato');
    });
    $ok = BulkAction::make('y')->transaction(false)->handle(static fn (array $ids, string $class, ?object $user): array => [
        'done' => $class === BulkActionsTestResource::class && $user === null ? $ids : [],
    ]);

    return BulkActions::run($action, BulkActionsTestResource::class, [1, 2], null) === ['done' => [], 'failed' => [1 => 'Bloccato', 2 => 'Bloccato']]
        && BulkActions::run($ok, BulkActionsTestResource::class, [5, 6], null) === ['done' => [5, 6], 'failed' => []]
        && BulkActions::run(BulkAction::make('z'), BulkActionsTestResource::class, [1], null) === ['done' => [], 'failed' => []];
});

check('la barra porta tabella, endpoint, resource e azioni, tutto escapato', function () {
    $html = BulkActions::bar('t"able', '/api/backend/bulk/', 'ordini', [
        'cancel' => BulkAction::make('cancel')->label('Annulla <b>')->variant('danger')->icon('bi bi-x"><script>')->confirm('Sicuro "davvero"?'),
        'ok' => BulkAction::make('ok')->label('Conferma'),
    ]);

    return str_contains($html, 'data-wi-bulk="t&quot;able"')
        && str_contains($html, 'data-wi-bulk-endpoint="/api/backend/bulk/"')
        && str_contains($html, 'data-wi-bulk-resource="ordini"')
        && str_contains($html, 'class="btn btn-sm btn-outline-danger" data-wi-bulk-action="cancel" data-wi-confirm="Sicuro &quot;davvero&quot;?" data-wi-confirm-variant="danger"')
        && str_contains($html, '<i class="bi bi-xscript"></i> Annulla &lt;b&gt;')
        && str_contains($html, 'data-wi-bulk-action="ok">Conferma</button>')
        && !str_contains($html, 'data-wi-bulk-action="ok" data-wi-confirm')
        && str_contains($html, 'class="d-none mb-2"')
        && !str_contains($html, '<script>');
});

check('checkbox di riga e d\'intestazione sono form-check-input con etichetta', fn () =>
    BulkActions::checkbox('7"') === '<input class="form-check-input m-0" type="checkbox" data-wi-bulk-id="7" aria-label="Seleziona la riga">'
    && str_contains(BulkActions::header(), 'data-wi-bulk-all')
);

check('la colonna delle checkbox legge l\'id della riga', function () {
    $field = new Field(
        (object) ['id' => 't', 'table' => 'ordini', 'connection' => null, 'database' => 'main', 'field' => [], 'page' => 0, 'length' => 10, 'link' => []],
        (object) ['site' => '', 'backend' => '/backend', 'app' => '/app', 'api' => '/api'],
        (object) ['titleS' => 'x', 'titleP' => 'x', 'last' => '', 'all' => '', 'article' => '', 'full' => '', 'empty' => '', 'this' => ''],
        (object) ['area' => '', 'authority' => ''],
        (object) ['redirect' => '', 'redirectBase64' => '']
    );
    $column = ListProvider::buildColumns([['name' => BulkActions::COLUMN, 'other' => []]], false, 1, $field)[0];

    // Come la lista vera: SSP passa 2 argomenti quando il formato è vuoto.
    $out = \Wonder\Backend\Table\SSP::data_output([$column], [['id' => 42]]);

    return $column['db'] === 'id'
        && str_contains((string) ($out[0][0] ?? ''), 'data-wi-bulk-id="42"');
});

check('toggle() e delete() hanno permesso e aspetto giusti', function () {
    $toggle = BulkAction::toggle('publish', 'active', true);
    $delete = BulkAction::delete();

    return $toggle->getPermission() === 'update'
        && $toggle->getHandler() !== null
        && $toggle->usesTransaction()
        && $delete->getPermission() === 'delete'
        && $delete->getVariant() === 'danger'
        && $delete->key() === 'delete';
});

check('il permesso è della Resource della lista o di quella indicata', function () use ($throws) {
    $own = BulkAction::make('a');
    $other = BulkAction::make('b')->permission('update', BulkActionsOwnerResource::class);
    $actions = ['a' => $own, 'b' => $other];

    return !BulkActions::allowed($own, BulkActionsListOnlyResource::class, ['vendite'])
        && BulkActions::allowed($other, BulkActionsListOnlyResource::class, ['vendite'])
        && !BulkActions::allowed($other, BulkActionsListOnlyResource::class, ['catalogo'])
        && array_keys(BulkActions::allowedActions($actions, BulkActionsListOnlyResource::class, ['vendite'])) === ['b']
        && $other->getPermissionResource() === BulkActionsOwnerResource::class
        && $throws(fn () => BulkAction::make('c')->permission('update', stdClass::class));
});

summary();
