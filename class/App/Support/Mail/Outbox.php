<?php

namespace Wonder\App\Support\Mail;

use Throwable;
use Wonder\App\Scheduler\Context;
use Wonder\Backend\Support\HomeStatus;

/**
 * Coda delle email dentro `mail_log`: `queueMail()` scrive una riga
 * `queued`, il task `wonder.mail-outbox` (sempre attivo, ogni minuto) la
 * passa a `sending`, spedisce con `mailDeliver()` e la chiude `sent` o
 * `failed`. Gli errori temporanei riprovano dopo `RETRY_DELAYS`, fino a
 * `MAX_ATTEMPTS`; quelli permanenti (`permanent()`) chiudono subito.
 *
 * Fallback: senza heartbeat dello scheduler negli ultimi
 * `HomeStatus::HEARTBEAT_TOLERANCE` secondi, o se la coda non si scrive,
 * `queue()` spedisce subito con `sendMail()`. Una riga `sending` oltre
 * `CLAIM_TIMEOUT` torna in coda: la consegna è almeno una volta.
 */
final class Outbox
{
    public const MAX_ATTEMPTS = 5;

    /** Attesa dopo il tentativo n (secondi); oltre la lista vale l'ultima. */
    public const RETRY_DELAYS = [60, 300, 900, 3600];

    public const CLAIM_TIMEOUT = 900;

    /** Oltre questi secondi una mail in coda è segnalata nella home del backend. */
    public const STUCK_AFTER = 900;

    public const BATCH = 50;

    /** Secondi lasciati liberi prima della scadenza del giro: il resto aspetta il prossimo. */
    public const DEADLINE_MARGIN = 30;

    /**
     * `$message`: `from`, `to`, `subject`, `body`, `attachments`, `template`
     * (gli argomenti di `sendMail()`). `$send` riceve gli stessi argomenti
     * in ordine; di default è `sendMail()`.
     */
    public static function queue(array $message, ?OutboxStore $store = null, ?callable $send = null, ?int $now = null): bool
    {
        $message = self::message($message);
        $now ??= time();
        $send ??= 'sendMail';

        try {
            $store ??= new DatabaseOutboxStore();

            if (self::alive($store->heartbeat(), $now)) {
                $store->queue($message, self::time($now));

                return true;
            }
        } catch (Throwable $exception) {
            __log($exception, '[outbox]', '[queueMail]');
        }

        return (bool) $send(...array_values($message));
    }

    /**
     * Un giro della coda. `$deliver` riceve gli argomenti di `sendMail()` e
     * ritorna almeno `sent`, `service`, `error` (come `mailDeliver()`).
     *
     * @return array{released: int, sent: int, retried: int, failed: int}
     */
    public static function process(OutboxStore $store, callable $deliver, ?Context $context = null, ?int $now = null, int $limit = self::BATCH): array
    {
        $now ??= time();
        $result = ['released' => $store->release(self::time($now)), 'sent' => 0, 'retried' => 0, 'failed' => 0];

        foreach ($store->due(self::time($now), $limit) as $id) {
            if ($context !== null && microtime(true) >= $context->deadline - self::DEADLINE_MARGIN) {
                break;
            }

            $claim = $store->claim((int) $id, self::time($now + self::CLAIM_TIMEOUT));
            if ($claim === null) {
                continue;
            }

            $attempts = (int) $claim['attempts'] + 1;

            try {
                $delivery = $deliver(...array_values(self::message((array) $claim['message'])));
            } catch (Throwable $exception) {
                $delivery = ['sent' => false, 'error' => $exception->getMessage()];
            }

            $delivery = (array) $delivery;

            if (!empty($delivery['sent'])) {
                $store->finish((int) $id, 'sent', $attempts, $delivery);
                $result['sent']++;
            } elseif ($attempts >= self::MAX_ATTEMPTS || self::permanent((string) ($delivery['error'] ?? ''))) {
                $store->finish((int) $id, 'failed', $attempts, $delivery);
                $result['failed']++;
            } else {
                $store->finish((int) $id, 'queued', $attempts, $delivery, self::time(self::retryAt($attempts, $now)));
                $result['retried']++;
            }
        }

        return $result;
    }

    /** Vero se lo scheduler ha battuto un colpo da poco. */
    public static function alive(?string $heartbeat, int $now): bool
    {
        $seen = $heartbeat ? strtotime($heartbeat.' UTC') : false;

        return $seen !== false && $now - $seen <= HomeStatus::HEARTBEAT_TOLERANCE;
    }

    public static function retryAt(int $attempts, int $now): int
    {
        $delays = self::RETRY_DELAYS;

        return $now + $delays[min(max($attempts, 1), count($delays)) - 1];
    }

    /**
     * Errori che un nuovo tentativo non risolve: indirizzo rifiutato
     * (SMTP 550-554, `Invalid address`) o allegato sparito. Autenticazione
     * e rete restano temporanei: si sistemano le credenziali e la coda riparte.
     */
    public static function permanent(string $error): bool
    {
        return preg_match('/\b55[0-4]\b|Invalid address|recipients failed|Could not access file|Allegato non trovato/i', $error) === 1;
    }

    /** Una mail doveva partire da oltre `STUCK_AFTER` secondi: la coda è ferma. */
    public static function stuck(?string $oldestDue, int $now): bool
    {
        $due = $oldestDue ? strtotime($oldestDue.' UTC') : false;

        return $due !== false && $now - $due > self::STUCK_AFTER;
    }

    public static function time(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /** Normalizza le chiavi del messaggio nell'ordine degli argomenti di `sendMail()`. */
    public static function message(array $message): array
    {
        return [
            'from' => (string) ($message['from'] ?? ''),
            'to' => (string) ($message['to'] ?? ''),
            'subject' => (string) ($message['subject'] ?? ''),
            'body' => (string) ($message['body'] ?? ''),
            'attachments' => $message['attachments'] ?? null,
            'template' => (string) ($message['template'] ?? 'basic'),
        ];
    }
}
