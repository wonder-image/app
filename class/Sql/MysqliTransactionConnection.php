<?php

namespace Wonder\Sql;

use mysqli;
use RuntimeException;

final class MysqliTransactionConnection implements TransactionConnection
{
    public function __construct(private readonly mysqli $mysqli)
    {
    }

    public static function idFor(mysqli $mysqli): string
    {
        return 'mysqli:'.spl_object_id($mysqli);
    }

    public function id(): string
    {
        return self::idFor($this->mysqli);
    }

    public function begin(): void
    {
        if (!$this->mysqli->begin_transaction()) {
            throw new RuntimeException('Impossibile aprire la transazione: '.$this->mysqli->error);
        }
    }

    public function commit(): void
    {
        if (!$this->mysqli->commit()) {
            throw new RuntimeException('Impossibile confermare la transazione: '.$this->mysqli->error);
        }
    }

    public function rollback(): void
    {
        $this->mysqli->rollback();
    }

    public function savepoint(string $name): void
    {
        $this->query('SAVEPOINT '.$this->identifier($name));
    }

    public function rollbackToSavepoint(string $name): void
    {
        $this->query('ROLLBACK TO SAVEPOINT '.$this->identifier($name));
    }

    public function releaseSavepoint(string $name): void
    {
        $this->query('RELEASE SAVEPOINT '.$this->identifier($name));
    }

    private function query(string $sql): void
    {
        if (!$this->mysqli->query($sql)) {
            throw new RuntimeException('Errore SQL ('.$sql.'): '.$this->mysqli->error);
        }
    }

    private function identifier(string $name): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new RuntimeException('Nome savepoint non valido: '.$name);
        }

        return '`'.$name.'`';
    }
}
