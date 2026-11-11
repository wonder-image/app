<?php

namespace Wonder\App\Media;

use mysqli;
use mysqli_stmt;
use RuntimeException;
use Wonder\App\Models\Media\MediaUsage;
use Wonder\Sql\Connection;

/**
 * Registro degli utilizzi in `media_usages`. Solo prepared statement: la
 * tabella, la riga e la colonna del proprietario arrivano dal codice che
 * salva il record, ma restano valori, mai SQL.
 */
final class DatabaseMediaUsageStore implements MediaUsageStore
{
    private ?mysqli $connection;

    public function __construct(?mysqli $connection = null)
    {
        $this->connection = $connection;
    }

    public function replace(string $table, string $rowId, string $column, array $mediaIds): void
    {
        // Niente transazione propria: chi salva il record può averne già una
        // aperta, e aprirne un'altra chiuderebbe la sua.
        $this->execute(
            'DELETE FROM `'.MediaUsage::$table.'` WHERE `owner_table` = ? AND `owner_id` = ? AND `owner_column` = ?',
            [$table, $rowId, $column]
        );

        if ($mediaIds === []) {
            return;
        }

        $parameters = [];

        foreach ($mediaIds as $mediaId) {
            array_push($parameters, (int) $mediaId, $table, $rowId, $column);
        }

        $this->execute(
            'INSERT INTO `'.MediaUsage::$table.'` (`media_id`, `owner_table`, `owner_id`, `owner_column`) VALUES '
                .implode(', ', array_fill(0, count($mediaIds), '(?, ?, ?, ?)')),
            $parameters
        );
    }

    public function forget(string $table, string $rowId): void
    {
        $this->execute(
            'DELETE FROM `'.MediaUsage::$table.'` WHERE `owner_table` = ? AND `owner_id` = ?',
            [$table, $rowId]
        );
    }

    public function used(int $mediaId): bool
    {
        $row = $this->execute(
            'SELECT 1 FROM `'.MediaUsage::$table.'` WHERE `media_id` = ? LIMIT 1',
            [$mediaId]
        )->get_result()->fetch_row();

        return $row !== null && $row !== false;
    }

    public function owners(int $mediaId): array
    {
        $rows = $this->execute(
            'SELECT `owner_table`, `owner_id`, `owner_column` FROM `'.MediaUsage::$table.'` WHERE `media_id` = ? ORDER BY `id`',
            [$mediaId]
        )->get_result()->fetch_all(MYSQLI_ASSOC);

        return array_map(static fn (array $row): array => [
            'table' => (string) $row['owner_table'],
            'id' => (string) $row['owner_id'],
            'column' => (string) $row['owner_column'],
        ], $rows);
    }

    /** La connessione si apre alla prima operazione, non alla costruzione. */
    private function connection(): mysqli
    {
        return $this->connection ??= ($GLOBALS['mysqli'] ?? null) ?? Connection::Connect('main');
    }

    private function execute(string $sql, array $parameters): mysqli_stmt
    {
        // Anche con la tabella mancante (prima di `forge update`) l'errore è
        // uno solo e senza dettagli del database: chi chiede se un media è
        // in uso deve fermarsi, non proseguire come se non lo fosse.
        try {
            $statement = $this->connection()->prepare($sql);

            if ($statement && $statement->execute($parameters)) {
                return $statement;
            }
        } catch (\Throwable) {
        }

        throw new RuntimeException('Il registro degli utilizzi dei media non risponde: riprova dopo l\'aggiornamento del sito.');
    }
}
