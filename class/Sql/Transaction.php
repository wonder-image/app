<?php

namespace Wonder\Sql;

use mysqli;
use Throwable;

/**
 * Transazioni annidabili.
 *
 * Il livello esterno apre, conferma o annulla la transazione; i livelli
 * interni usano `SAVEPOINT wi_sp_<n>`. Un errore annulla il proprio livello
 * e viene rilanciato: se nessuno lo gestisce, annulla tutto.
 */
final class Transaction
{
    /** @var array<string, int> profondità per connessione */
    private static array $depth = [];

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function run(callable $callback, ?string $database = null): mixed
    {
        return self::runOn(new MysqliTransactionConnection(Connection::Connect($database ?? 'main')), $callback);
    }

    public static function runOn(TransactionConnection $connection, callable $callback): mixed
    {
        $key = $connection->id();
        $depth = self::$depth[$key] ?? 0;
        $savepoint = 'wi_sp_'.$depth;

        if ($depth === 0) {
            $connection->begin();
        } else {
            $connection->savepoint($savepoint);
        }

        self::$depth[$key] = $depth + 1;

        try {
            $result = $callback();
        } catch (Throwable $throwable) {
            self::restoreDepth($key, $depth);

            if ($depth === 0) {
                $connection->rollback();
            } else {
                $connection->rollbackToSavepoint($savepoint);
            }

            throw $throwable;
        }

        self::restoreDepth($key, $depth);

        if ($depth === 0) {
            $connection->commit();
        } else {
            $connection->releaseSavepoint($savepoint);
        }

        return $result;
    }

    public static function active(?string $database = null): bool
    {
        return self::activeForMysqli(Connection::Connect($database ?? 'main'));
    }

    public static function activeOn(TransactionConnection $connection): bool
    {
        return (self::$depth[$connection->id()] ?? 0) > 0;
    }

    public static function activeForMysqli(mysqli $mysqli): bool
    {
        return (self::$depth[MysqliTransactionConnection::idFor($mysqli)] ?? 0) > 0;
    }

    private static function restoreDepth(string $key, int $depth): void
    {
        if ($depth === 0) {
            unset(self::$depth[$key]);
            return;
        }

        self::$depth[$key] = $depth;
    }
}
