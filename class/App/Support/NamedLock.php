<?php

namespace Wonder\App\Support;

use InvalidArgumentException;
use Wonder\Sql\Connection;

/**
 * Esegue un callback solo se un lock nominale è libero (es. cron che non
 * deve partire due volte in parallelo). Il lock viene sempre rilasciato.
 */
final class NamedLock
{
    public const NOT_ACQUIRED = '__wonder_named_lock_not_acquired__';

    public static function run(string $name, callable $callback, int $timeout = 0, ?string $database = null): mixed
    {
        return self::runOn(
            new MysqliNamedLockConnection(Connection::Connect($database ?? 'main')),
            $name,
            $callback,
            $timeout
        );
    }

    public static function runOn(NamedLockConnection $connection, string $name, callable $callback, int $timeout = 0): mixed
    {
        $lockName = self::lockName($name);

        if (!$connection->acquire($lockName, $timeout)) {
            return self::NOT_ACQUIRED;
        }

        try {
            return $callback();
        } finally {
            $connection->release($lockName);
        }
    }

    /**
     * Nome valido per MySQL (massimo 64 caratteri): i nomi lunghi diventano
     * un hash stabile.
     */
    public static function lockName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Nome del lock vuoto.');
        }

        return strlen($name) <= 64 ? $name : 'wi_lock_'.sha1($name);
    }
}
