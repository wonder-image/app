<?php

namespace Wonder\Backend\Table;

use Closure;
use RuntimeException;
use Wonder\App\Support\SyncedTables;

/**
 * Un'azione da applicare a più righe selezionate di una lista backend.
 *
 * La Resource la dichiara con `TableLayoutSchema::bulkActions([...])`; il
 * browser manda solo la chiave e gli id a `/api/backend/bulk/`, il gestore
 * lo trova il server fra quelli della Resource. Il gestore riceve gli id già
 * filtrati (esistenti, visibili all'utente, dentro la condizione della lista)
 * e restituisce `['done' => int[], 'failed' => [id => messaggio]]` oppure un
 * intero, che vuol dire «fatti tutti».
 */
final class BulkAction
{
    public const UPDATE = TableAuthorization::UPDATE;
    public const DELETE = TableAuthorization::DELETE;

    private const VARIANTS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];

    private string $label = '';
    private string $icon = '';
    private string $variant = 'dark';
    private string $permission = self::UPDATE;
    /** @var class-string|null la Resource che concede il permesso; null = quella della lista */
    private ?string $permissionResource = null;
    private ?string $confirm = null;
    private ?Closure $handler = null;
    private bool $transaction = true;
    private bool $row = false;
    private ?Closure $rowWhen = null;

    private function __construct(private readonly string $key)
    {
        if (preg_match('/^[a-z0-9_-]{1,64}$/', $key) !== 1) {
            throw new RuntimeException("Chiave non valida per BulkAction: {$key}");
        }
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    /**
     * Mette `$column` a `'true'` o `'false'` su tutte le righe (pubblica,
     * nascondi, attiva...).
     */
    public static function toggle(string $key, string $column, bool $value): self
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
            throw new RuntimeException("Colonna non valida per BulkAction::toggle(): {$column}");
        }

        return self::make($key)
            ->permission(self::UPDATE)
            ->handle(static function (array $ids, string $resourceClass) use ($column, $value): array {
                $table = $resourceClass::modelTable();

                if (SyncedTables::isReadonlyColumn($table, $column)) {
                    throw new RuntimeException('Colonna in sola lettura in questo ambiente.');
                }

                foreach ($ids as $id) {
                    sqlModify($table, [$column => $value ? 'true' : 'false'], 'id', $id);
                }

                return ['done' => $ids, 'failed' => []];
            });
    }

    /**
     * Elimina le righe come il bottone della lista: `assertDeletable()`,
     * poi `deleteRecord()` se la Resource cancella davvero, altrimenti
     * `deleted = 'true'` (e `active`/`visible` a `'false'`). Le posizioni
     * non si ricompattano.
     */
    public static function delete(string $key = 'delete'): self
    {
        $action = self::make($key)
            ->permission(self::DELETE)
            ->variant('danger')
            ->icon('bi bi-trash3')
            ->handle(static fn (array $ids, string $resourceClass): array => self::deleteRows($ids, $resourceClass));

        if (function_exists('__t')) {
            $action->label((string) __t('bulk.delete'))->confirm((string) __t('bulk.delete_confirm'));
        }

        return $action;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** Classi dell'icona, es. `bi bi-eye`. */
    public function icon(string $icon): self
    {
        $this->icon = preg_replace('/[^A-Za-z0-9 _-]/', '', $icon) ?? '';

        return $this;
    }

    /** Colore Bootstrap del bottone: primary, danger, dark, ... */
    public function variant(string $variant): self
    {
        if (!in_array($variant, self::VARIANTS, true)) {
            throw new RuntimeException("Variante non valida per BulkAction: {$variant}");
        }

        $this->variant = $variant;

        return $this;
    }

    /**
     * Il permesso che serve: `update` o `delete`, della Resource della lista
     * o di `$resource` (es. la Resource delle azioni sugli ordini, quando la
     * lista è in sola lettura per i campi ma le azioni hanno una loro pagina).
     *
     * @param class-string|null $resource
     */
    public function permission(string $permission, ?string $resource = null): self
    {
        if (!in_array($permission, [self::UPDATE, self::DELETE], true)) {
            throw new RuntimeException("Permesso non valido per BulkAction: {$permission}");
        }

        if ($resource !== null && !is_subclass_of($resource, \Wonder\App\Resource::class)) {
            throw new RuntimeException("Resource non valida per BulkAction: {$resource}");
        }

        $this->permission = $permission;
        $this->permissionResource = $resource;

        return $this;
    }

    /** Testo della conferma prima di partire; null per partire subito. */
    public function confirm(?string $text): self
    {
        $this->confirm = $text === null || trim($text) === '' ? null : $text;

        return $this;
    }

    /** @param callable(list<int>, string, ?object): (array|int) $handler */
    public function handle(callable $handler): self
    {
        $this->handler = Closure::fromCallable($handler);

        return $this;
    }

    /**
     * Gestore dentro una transazione (predefinito). Chi apre le sue, come le
     * azioni degli ordini, chiede `false`.
     */
    public function transaction(bool $enabled = true): self
    {
        $this->transaction = $enabled;

        return $this;
    }

    /**
     * L'azione anche nel menu a 3 puntini di ogni riga, per quella sola riga.
     * `$when` riceve la riga della lista e dice se la voce si vede (es. lo
     * stato dell'ordine); senza, si vede sempre. L'esito resta del gestore.
     *
     * @param (callable(array<string, mixed>): bool)|null $when
     */
    public function row(?callable $when = null): self
    {
        $this->row = true;
        $this->rowWhen = $when === null ? null : Closure::fromCallable($when);

        return $this;
    }

    public function inRow(): bool
    {
        return $this->row;
    }

    /** @param array<string, mixed> $row */
    public function showsInRow(array $row): bool
    {
        if (!$this->row) {
            return false;
        }

        if ($this->rowWhen === null) {
            return true;
        }

        try {
            return (bool) ($this->rowWhen)($row);
        } catch (\Throwable) {
            return false;
        }
    }

    public function key(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label !== '' ? $this->label : $this->key;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getVariant(): string
    {
        return $this->variant;
    }

    public function getPermission(): string
    {
        return $this->permission;
    }

    /** @return class-string|null */
    public function getPermissionResource(): ?string
    {
        return $this->permissionResource;
    }

    public function getConfirm(): ?string
    {
        return $this->confirm;
    }

    public function getHandler(): ?Closure
    {
        return $this->handler;
    }

    public function usesTransaction(): bool
    {
        return $this->transaction;
    }

    /**
     * @param list<int> $ids
     * @return array{done: list<int>, failed: array<int, string>}
     */
    private static function deleteRows(array $ids, string $resourceClass): array
    {
        $table = $resourceClass::modelTable();
        $done = [];
        $failed = [];

        if (sqlColumnExists($table, 'active')) {
            $values = ['active' => 'false', 'deleted' => 'true'];
        } elseif (sqlColumnExists($table, 'visible')) {
            $values = ['visible' => 'false', 'deleted' => 'true'];
        } else {
            $values = ['deleted' => 'true'];
        }

        foreach ($ids as $id) {
            try {
                $resourceClass::assertDeletable($id);
            } catch (RuntimeException $error) {
                $failed[$id] = $error->getMessage();
                continue;
            }

            if ($resourceClass::listDeletesRecord()) {
                $row = sqlSelect($table, ['id' => $id], 1);
                $rowValues = (array) ($row->row ?? []);
                $result = $resourceClass::deleteRecord($id);

                if (empty($result->success)) {
                    $failed[$id] = function_exists('__t') ? (string) __t('bulk.delete_failed') : 'Eliminazione non riuscita.';
                    continue;
                }

                $resourceClass::mediaInputs()->forget($id);
                $resourceClass::afterDelete($id, $result, $rowValues);
            } else {
                sqlModify($table, $values, 'id', $id);
                $resourceClass::mediaInputs()->forget($id);
            }

            $done[] = $id;
        }

        if ($done !== [] && $resourceClass::listDeletesRecord()) {
            $resourceClass::exportSyncData();
        }

        return ['done' => $done, 'failed' => $failed];
    }
}
