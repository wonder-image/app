<?php

namespace Wonder\Backend\Table;

use Closure;
use Wonder\App\ResourceRegistry;
use Wonder\App\Support\UploadDir;
use Wonder\App\Table as AppTable;

/**
 * Autorizzazione delle mutazioni backend su tabella scelta dal client
 * (`api.backend.change.boolean`, `delete`, `move`, `file.delete`,
 * `file.move`, `authority`).
 *
 * - tabella con Resource registrata: `Resource::allows()` sull'azione
 *   backend (`update` o `delete`) di `permissionSchema()`;
 * - tabella senza Resource (legacy): solo `admin`.
 *
 * Il guard della rotta resta `permit([])` (qualsiasi utente backend): la
 * decisione per tabella e qui.
 */
final class TableAuthorization
{
    public const UPDATE = 'update';
    public const DELETE = 'delete';
    public const LEGACY_AUTHORITY = 'admin';
    public const USER_TABLE = 'user';

    private Closure $resolver;

    /** @param (Closure(string): ?string)|null $resolver tabella => classe Resource */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? static fn (string $table): ?string => ResourceRegistry::resolveByTable($table);
    }

    public static function make(): self
    {
        return new self();
    }

    /** @param list<string> $authorities authority dell'utente backend */
    public function allows(string $table, string $action, array $authorities): bool
    {
        $table = trim($table);

        if ($table === '' || !in_array($action, [self::UPDATE, self::DELETE], true)) {
            return false;
        }

        $authorities = array_values(array_map('strval', $authorities));
        $resourceClass = ($this->resolver)($table);

        if (is_string($resourceClass) && $resourceClass !== '') {
            return $resourceClass::allows($authorities, $action);
        }

        return in_array(self::LEGACY_AUTHORITY, $authorities, true);
    }

    /**
     * `authority.php`: solo righe della tabella `user`, mai la propria, e
     * solo per chi passa `allows(..., update)`.
     */
    public function allowsAuthorityChange(string $table, int $rowId, mixed $user): bool
    {
        if (strtolower(trim($table)) !== self::USER_TABLE || $rowId <= 0) {
            return false;
        }

        $ownId = is_object($user) ? (int) ($user->id ?? 0) : 0;

        if ($ownId <= 0 || $ownId === $rowId) {
            return false;
        }

        return $this->allows(self::USER_TABLE, self::UPDATE, self::authoritiesOf($user));
    }

    /** @return list<string> authority dell'oggetto `$USER` (array o JSON). */
    public static function authoritiesOf(mixed $user): array
    {
        if (!is_object($user)) {
            return [];
        }

        $authority = $user->authority ?? [];

        if (is_string($authority)) {
            $decoded = json_decode($authority, true);
            $authority = is_array($decoded) ? $decoded : [$authority];
        }

        $out = [];

        foreach ((array) $authority as $value) {
            if ((is_string($value) || is_numeric($value)) && trim((string) $value) !== '') {
                $out[] = trim((string) $value);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Tabella e colonna di `file.delete`/`file.move`: tabella registrata in
     * `Table::$list`, colonna dello schema con `input.format.file === true`.
     * Restituisce `[tabella canonica, colonna]` o null.
     *
     * @param array<string,mixed>|null $registry default `Table::$list`
     * @return array{0: string, 1: string}|null
     */
    public static function fileColumn(mixed $table, mixed $column, ?array $registry = null): ?array
    {
        $registry ??= AppTable::$list;
        $table = ListTableRequest::table($table, $registry);

        if ($table === null || !is_string($column) || trim($column) === '') {
            return null;
        }

        $column = trim($column);
        $schema = $registry[$table][$column] ?? null;

        if (!is_array($schema) || (($schema['input']['format']['file'] ?? false) !== true)) {
            return null;
        }

        return [$table, $column];
    }

    /**
     * Cartella di upload inviata dal client (`data-wi-folder`, es.
     * `/profile-picture/`): relativa alla radice upload, senza `..`.
     * `''` o `'/'` => `''`; null se non valida.
     */
    public static function uploadFolder(string $folder): ?string
    {
        $normalized = UploadDir::normalize(trim($folder, '/'));

        return $normalized === null ? null : trim($normalized, '/');
    }
}
