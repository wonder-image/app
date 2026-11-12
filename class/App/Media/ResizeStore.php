<?php

namespace Wonder\App\Media;

/**
 * Dove vive la coda dei ridimensionamenti (`image_resizes` con
 * `DatabaseResizeStore`). I percorsi sono relativi a `ROOT`, le date stringhe
 * UTC `Y-m-d H:i:s` calcolate da `ResizeQueue`. Iniettabile perché i test
 * della coda non tocchino il database.
 */
interface ResizeStore
{
    /** Vero se la coda si può scrivere (la tabella esiste). */
    public function usable(): bool;

    /** Heartbeat dello scheduler (UTC) o null se non c'è mai stato. */
    public function heartbeat(): ?string;

    /**
     * Una riga per percorso: se c'è già torna `pending` da capo (tentativi
     * a zero, nessuna presa in carico). `$job`: `widths` (lista di interi),
     * `webp` (bool), `quality` (int o null), `delete` (lista di percorsi).
     */
    public function put(string $path, array $job, string $now): void;

    /** `['status', 'error', 'delete']` della riga di `$path`, o null se non è mai stata accodata. */
    public function find(string $path): ?array;

    /**
     * Libera le prese in carico scadute a `$now`. Chi ha già speso
     * `$maxAttempts` tentativi chiude `failed` con `$error`: un file che fa
     * cadere il processo non deve ripartire all'infinito. Ritorna quante righe.
     */
    public function release(string $now, int $maxAttempts, string $error): int;

    /** @return list<int> id delle righe `pending` libere da lavorare entro `$now`, le più vecchie prima */
    public function due(string $now, int $limit): array;

    /**
     * Prende in carico la riga con `$token` fino a `$until`, solo se è ancora
     * `pending` e libera, e conta il tentativo. Ritorna `path`, `widths`,
     * `webp`, `quality`, `delete`, `attempts` (già contato) o null.
     */
    public function claim(int $id, string $token, string $until): ?array;

    /**
     * Chiude il tentativo preso con `$token`: `ready`, `failed` o `pending`
     * (riprova a `$nextAttempt`). Falso se nel frattempo la riga è stata
     * riaccodata: resta `pending` e chi chiama non deve toccare altro.
     */
    public function finish(int $id, string $token, string $status, string $error, string $now, ?string $nextAttempt = null): bool;

    /** Rimette `pending` le righe `failed` (tentativi a zero); ritorna quante. */
    public function retryFailed(string $now): int;

    /** La data di accodamento (UTC) più vecchia tra le righe `pending`, o null. */
    public function oldestPending(): ?string;

    /** Righe `failed` chiuse da `$since` in poi, escluse quelle con errore `$except`. */
    public function failed(string $since, string $except): int;
}
