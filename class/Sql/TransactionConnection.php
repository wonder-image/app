<?php

namespace Wonder\Sql;

/**
 * Operazioni minime per transazioni e savepoint su una connessione.
 * `id()` identifica la connessione fisica (stessa connessione = stesso id).
 */
interface TransactionConnection
{
    public function id(): string;

    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;

    public function savepoint(string $name): void;

    public function rollbackToSavepoint(string $name): void;

    public function releaseSavepoint(string $name): void;
}
