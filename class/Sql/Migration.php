<?php

namespace Wonder\Sql;

use mysqli;
use RuntimeException;

/**
 * Operazioni ammesse dentro una migrazione versionata.
 *
 * Tutte idempotenti: rieseguirle dopo un errore a metà non fa danni
 * (su MariaDB il DDL non è transazionale). `dropColumn` e `dropIndex`
 * funzionano solo se la migrazione dichiara `destructive() === true`.
 */
class Migration
{
    /** @var string[] SQL eseguito, o che sarebbe stato eseguito con `pretend`. */
    private array $statements = [];

    public function __construct(
        private mysqli $connection,
        private bool $destructive = false,
        private bool $pretend = false
    ) {}

    /** @return string[] */
    public function statements(): array
    {
        return $this->statements;
    }

    /** `$definition` è il tipo con le opzioni, es. `VARCHAR(200) NOT NULL DEFAULT ''`. */
    public function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }

        $this->run('ALTER TABLE '.$this->id($table).' ADD COLUMN '.$this->id($column).' '.$definition);
    }

    /** @param string[]|string $columns */
    public function addIndexIfMissing(string $table, string $name, array|string $columns, bool $unique = false): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        $cols = implode(', ', array_map(fn ($c) => $this->id($c), (array) $columns));

        $this->run('ALTER TABLE '.$this->id($table).' ADD '.($unique ? 'UNIQUE ' : '').'INDEX '.$this->id($name).' ('.$cols.')');
    }

    public function dropColumn(string $table, string $column): void
    {
        $this->requireDestructive(__FUNCTION__);

        if (!$this->columnExists($table, $column)) {
            return;
        }

        $this->run('ALTER TABLE '.$this->id($table).' DROP COLUMN '.$this->id($column));
    }

    public function dropIndex(string $table, string $name): void
    {
        $this->requireDestructive(__FUNCTION__);

        if (!$this->indexExists($table, $name)) {
            return;
        }

        $this->run('ALTER TABLE '.$this->id($table).' DROP INDEX '.$this->id($name));
    }

    /** Rinomina una colonna se esiste ancora con il vecchio nome. `$definition` è il tipo completo. */
    public function renameColumn(string $table, string $from, string $to, string $definition): void
    {
        if (!$this->columnExists($table, $from) || $this->columnExists($table, $to)) {
            return;
        }

        $this->run('ALTER TABLE '.$this->id($table).' CHANGE COLUMN '.$this->id($from).' '.$this->id($to).' '.$definition);
    }

    /** SQL libero: chi lo usa garantisce l'idempotenza. */
    public function raw(string $sql): void
    {
        $this->run($sql);
    }

    private function requireDestructive(string $operation): void
    {
        if (!$this->destructive) {
            throw new RuntimeException($operation.'() richiede che la migrazione dichiari destructive() === true.');
        }
    }

    private function run(string $sql): void
    {
        $this->statements[] = $sql;

        if ($this->pretend) {
            return;
        }

        if ($this->connection->query($sql) === false) {
            throw new RuntimeException('Migrazione fallita: '.$this->connection->error.' ['.$sql.']');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $t = $this->connection->real_escape_string($table);
        $c = $this->connection->real_escape_string($column);
        $result = $this->connection->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");

        return is_object($result) && ($result->num_rows ?? 0) > 0;
    }

    private function indexExists(string $table, string $name): bool
    {
        $t = $this->connection->real_escape_string($table);
        $n = $this->connection->real_escape_string($name);
        $result = $this->connection->query("SHOW INDEX FROM `{$t}` WHERE Key_name = '{$n}'");

        return is_object($result) && ($result->num_rows ?? 0) > 0;
    }

    private function id(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }
}
