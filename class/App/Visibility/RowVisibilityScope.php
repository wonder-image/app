<?php

namespace Wonder\App\Visibility;

use InvalidArgumentException;
use Wonder\App\Model;

/**
 * Letture di un utente con la visibilità per riga applicata: punto
 * d'ingresso per il codice che non passa dal core (tool MCP, widget,
 * opzioni dei campi relazione).
 */
final class RowVisibilityScope
{
    public function __construct(private readonly ?object $user)
    {
    }

    public function user(): ?object
    {
        return $this->user;
    }

    /** Condizione di `$table` con la regola dell'utente (identica senza regola). */
    public function condition(string $table, string|array|null $condition = null): string|array|null
    {
        return RowVisibility::apply($table, $condition, $this->user);
    }

    /**
     * @param class-string<Model> $modelClass
     */
    public function find(
        string $modelClass,
        string|array|null $condition = null,
        string|int|null $limit = null,
        ?string $order = null,
        ?string $orderDirection = null,
        string|array $columns = '*'
    ): mixed {
        return $modelClass::find(
            $this->condition(self::table($modelClass), $condition),
            $limit,
            $order,
            $orderDirection,
            $columns
        );
    }

    /**
     * Riga per id, o `null` se non esiste o l'utente non la vede.
     *
     * @param class-string<Model> $modelClass
     */
    public function findById(string $modelClass, int|string $id): ?array
    {
        $row = $this->find($modelClass, [ 'id' => (int) $id ], 1);

        return is_array($row) && $row !== [] ? $row : null;
    }

    /**
     * Conteggio con il soft delete del Model (come `find()`).
     *
     * @param class-string<Model> $modelClass
     */
    public function count(string $modelClass, string|array|null $condition = null): int
    {
        $rows = $this->find($modelClass, $condition, null, null, null, 'id');

        if (!is_array($rows) || $rows === []) {
            return 0;
        }

        // Con una sola riga Query restituisce la riga, non la lista.
        return array_is_list($rows) ? count($rows) : 1;
    }

    public function visible(string $table, int|string $id): bool
    {
        return RowVisibility::visible($table, $id, $this->user);
    }

    private static function table(string $modelClass): string
    {
        if (!is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("RowVisibilityScope: {$modelClass} non è un Model.");
        }

        return (string) $modelClass::$table;
    }
}
