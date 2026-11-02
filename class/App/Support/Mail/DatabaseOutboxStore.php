<?php

namespace Wonder\App\Support\Mail;

use RuntimeException;
use Throwable;
use Wonder\App\Scheduler\Repository;
use Wonder\App\Table;
use Wonder\Sql\Connection;

/**
 * Coda in `mail_log`. Le colonne mostrate dal backend passano da
 * `Table::prepare()` come in `sendMail()`; `payload` (JSON degli argomenti
 * grezzi di `sendMail()`), `attempts`, `next_attempt_at` e i cambi di stato
 * usano prepared statement, perché il corpo deve tornare identico byte per
 * byte e la presa in carico deve essere atomica.
 */
class DatabaseOutboxStore implements OutboxStore
{
    private \mysqli $connection;

    public function __construct(?\mysqli $connection = null)
    {
        $this->connection = $connection ?? ($GLOBALS['mysqli'] ?? null) ?? Connection::Connect('main');
    }

    public function heartbeat(): ?string
    {
        try {
            return (new Repository($this->connection))->state('heartbeat');
        } catch (Throwable) {
            return null;
        }
    }

    public function queue(array $message, string $nextAttempt): int
    {
        $payload = json_encode($message, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

        $id = mailLogInsert([
            'to_email' => $message['to'],
            'reply_to_email' => $message['from'],
            'subject' => $message['subject'],
            'template' => $message['template'],
            'attachments' => $message['attachments'],
            'service' => '',
            'status' => 'queued',
        ]);

        if ($id === null) {
            throw new RuntimeException('Riga della coda email non scritta.');
        }

        try {
            $this->execute('UPDATE mail_log SET payload = ?, attempts = 0, next_attempt_at = ? WHERE id = ?', [$payload, $nextAttempt, $id]);
        } catch (Throwable $exception) {
            // Senza payload la riga non partirebbe mai: chiusa, e si spedisce subito.
            $this->connection->query("UPDATE mail_log SET status = 'failed' WHERE id = ".$id);
            throw $exception;
        }

        return $id;
    }

    public function release(string $now): int
    {
        return $this->execute("UPDATE mail_log SET status = 'queued' WHERE status = 'sending' AND next_attempt_at <= ? AND payload IS NOT NULL", [$now])->affected_rows;
    }

    public function due(string $now, int $limit): array
    {
        $rows = $this->execute("SELECT id FROM mail_log WHERE status = 'queued' AND payload IS NOT NULL AND next_attempt_at <= ? ORDER BY next_attempt_at, id LIMIT ?", [$now, $limit])
            ->get_result()->fetch_all(MYSQLI_ASSOC);

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    public function claim(int $id, string $until): ?array
    {
        if ($this->execute("UPDATE mail_log SET status = 'sending', next_attempt_at = ? WHERE id = ? AND status = 'queued' AND payload IS NOT NULL", [$until, $id])->affected_rows !== 1) {
            return null;
        }

        $row = $this->execute('SELECT payload, attempts FROM mail_log WHERE id = ?', [$id])->get_result()->fetch_assoc();
        $message = json_decode((string) ($row['payload'] ?? ''), true);

        if (!is_array($message)) {
            $this->finish($id, 'failed', (int) ($row['attempts'] ?? 0), ['error' => 'Payload della coda illeggibile.']);

            return null;
        }

        return ['message' => $message, 'attempts' => (int) ($row['attempts'] ?? 0)];
    }

    public function finish(int $id, string $status, int $attempts, array $delivery, ?string $nextAttempt = null): void
    {
        $display = array_filter([
            'from_email' => $delivery['from_email'] ?? null,
            'reply_to_email' => $delivery['reply_to_email'] ?? null,
            'body_raw' => $delivery['body_raw'] ?? null,
            'body_text' => $delivery['body_text'] ?? null,
            'service' => $delivery['service'] ?? null,
        ], static fn ($value): bool => $value !== null && $value !== '');
        $display['error_message'] = (string) ($delivery['error'] ?? '');

        try {
            sqlModify('mail_log', Table::key('mail_log')->prepare($display), 'id', $id);
        } catch (Throwable $exception) {
            __log($exception, '[outbox]', '[finish]');
        }

        if ($status === 'queued') {
            $this->execute("UPDATE mail_log SET status = 'queued', attempts = ?, next_attempt_at = ? WHERE id = ?", [$attempts, $nextAttempt, $id]);
        } else {
            $this->execute('UPDATE mail_log SET status = ?, attempts = ?, next_attempt_at = NULL, payload = NULL WHERE id = ?', [$status, $attempts, $id]);
        }
    }

    public function oldestDue(): ?string
    {
        $row = $this->execute("SELECT MIN(next_attempt_at) AS due FROM mail_log WHERE status = 'queued' AND payload IS NOT NULL")->get_result()->fetch_assoc();

        return $row['due'] ?? null;
    }

    private function execute(string $sql, array $parameters = []): \mysqli_stmt
    {
        $statement = $this->connection->prepare($sql);

        if (!$statement || !$statement->execute($parameters)) {
            throw new RuntimeException('Operazione sulla coda email fallita.');
        }

        return $statement;
    }
}
