<?php

namespace Wonder\Sql\Utility;

use Exception;

/**
 * Errore SQL: il messaggio non contiene la query, che resta
 * disponibile solo per il log tramite query().
 */
class QueryException extends Exception
{
    public function __construct(
        private string $action,
        private string $table,
        private string $query,
        private int $errno,
        private string $error,
    ) {
        $message = "\r\n";
        $message .= "Action: $action\r\n";
        $message .= "Table: $table\r\n";
        $message .= "\r\n";
        $message .= "Error N°$errno\r\n";
        $message .= "$error\r\n";
        $message .= "\r\n";

        parent::__construct($message);
    }

    public function action(): string
    {
        return $this->action;
    }

    public function table(): string
    {
        return $this->table;
    }

    public function query(): string
    {
        return $this->query;
    }

    public function errno(): int
    {
        return $this->errno;
    }

    public function error(): string
    {
        return $this->error;
    }
}
