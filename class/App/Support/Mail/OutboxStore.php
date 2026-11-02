<?php

namespace Wonder\App\Support\Mail;

/**
 * Dove vive la coda delle email (`mail_log` con `DatabaseOutboxStore`).
 * Le date sono stringhe UTC `Y-m-d H:i:s`, calcolate da `Outbox` in PHP.
 * Iniettabile perché i test di `Outbox` non tocchino il database.
 */
interface OutboxStore
{
    /** Heartbeat dello scheduler (UTC) o null se non c'è mai stato. */
    public function heartbeat(): ?string;

    /** Accoda `$message` (chiavi di `Outbox::queue()`) e ritorna l'id. */
    public function queue(array $message, string $nextAttempt): int;

    /** Rimette in coda le righe `sending` con presa in carico scaduta; ritorna quante. */
    public function release(string $now): int;

    /** @return list<int> id delle righe `queued` da spedire entro `$now`, le più vecchie prima */
    public function due(string $now, int $limit): array;

    /**
     * Passa la riga da `queued` a `sending` fino a `$until`, solo se è ancora
     * in coda. Ritorna `['message' => array, 'attempts' => int]` o null.
     */
    public function claim(int $id, string $until): ?array;

    /**
     * Chiude un tentativo. `$status`: `sent`, `failed` (il payload si
     * svuota) o `queued` (riprova a `$nextAttempt`). `$delivery` è il
     * risultato di `mailDeliver()`: servizio, errore, mittente e corpo finale.
     */
    public function finish(int $id, string $status, int $attempts, array $delivery, ?string $nextAttempt = null): void;

    /** Il prossimo tentativo (UTC) più vecchio tra le righe in coda, o null. */
    public function oldestDue(): ?string;
}
