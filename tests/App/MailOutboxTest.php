<?php
/** php tests/App/MailOutboxTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Scheduler\Context;
use Wonder\App\Support\Mail\Outbox;
use Wonder\App\Support\Mail\OutboxStore;
use Wonder\Backend\Support\HomeStatus;

if (!function_exists('__log')) {
    function __log(...$arguments): void {}
}

/** `mail_log` in memoria: stesse transizioni di `DatabaseOutboxStore`. */
final class MemoriaOutbox implements OutboxStore
{
    public array $rows = [];
    public bool $broken = false;

    public function __construct(public ?string $beat = null) {}

    public function heartbeat(): ?string { return $this->beat; }

    public function queue(array $message, string $nextAttempt): int
    {
        if ($this->broken) {
            throw new RuntimeException('database giù');
        }
        $id = count($this->rows) + 1;
        $this->rows[$id] = ['status' => 'queued', 'attempts' => 0, 'next' => $nextAttempt, 'payload' => json_encode($message), 'error' => '', 'service' => ''];

        return $id;
    }

    public function release(string $now): int
    {
        $count = 0;
        foreach ($this->rows as &$row) {
            if ($row['status'] === 'sending' && $row['next'] <= $now && $row['payload'] !== null) {
                $row['status'] = 'queued';
                $count++;
            }
        }

        return $count;
    }

    public function due(string $now, int $limit): array
    {
        $ids = array_keys(array_filter($this->rows, static fn (array $row): bool => $row['status'] === 'queued' && $row['payload'] !== null && $row['next'] <= $now));

        return array_slice($ids, 0, $limit);
    }

    public function claim(int $id, string $until): ?array
    {
        if (($this->rows[$id]['status'] ?? '') !== 'queued') {
            return null;
        }
        $this->rows[$id]['status'] = 'sending';
        $this->rows[$id]['next'] = $until;

        return ['message' => json_decode($this->rows[$id]['payload'], true), 'attempts' => $this->rows[$id]['attempts']];
    }

    public function finish(int $id, string $status, int $attempts, array $delivery, ?string $nextAttempt = null): void
    {
        $this->rows[$id] = array_merge($this->rows[$id], [
            'status' => $status,
            'attempts' => $attempts,
            'next' => $status === 'queued' ? $nextAttempt : null,
            'payload' => $status === 'queued' ? $this->rows[$id]['payload'] : null,
            'error' => (string) ($delivery['error'] ?? ''),
            'service' => (string) ($delivery['service'] ?? ''),
        ]);
    }

    public function oldestDue(): ?string
    {
        $due = array_column(array_filter($this->rows, static fn (array $row): bool => $row['status'] === 'queued'), 'next');

        return $due === [] ? null : min($due);
    }
}

$now = strtotime('2026-11-02 10:00:00 UTC');
$message = ['from' => 'negozio@example.test', 'to' => 'cliente@example.test', 'subject' => 'Ordine', 'body' => "<p>Caffè &amp; \"virgolette\" \\ 'apici'</p>", 'attachments' => ['/tmp/fattura.pdf'], 'template' => 'basic'];
$spedite = [];
$sync = static function (...$args) use (&$spedite): bool { $spedite[] = $args; return true; };
$riuscita = static fn (...$args): array => ['sent' => true, 'service' => 'brevo', 'error' => ''];
$errore = static fn (string $error): Closure => static fn (...$args): array => ['sent' => false, 'service' => 'phpmailer', 'error' => $error];

check('senza heartbeat dello scheduler queue() spedisce subito con sendMail()', static function () use ($message, $sync, &$spedite, $now): bool {
    $spedite = [];
    $store = new MemoriaOutbox(null);

    return Outbox::queue($message, $store, $sync, $now) && $store->rows === [] && count($spedite) === 1
        && $spedite[0] === Outbox::arguments($message);
});

check('con un heartbeat vecchio queue() spedisce subito', static function () use ($message, $sync, &$spedite, $now): bool {
    $spedite = [];
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now - HomeStatus::HEARTBEAT_TOLERANCE - 1));

    return Outbox::queue($message, $store, $sync, $now) && $store->rows === [] && count($spedite) === 1;
});

check('con lo scheduler vivo queue() accoda senza spedire, pronta subito', static function () use ($message, $sync, &$spedite, $now): bool {
    $spedite = [];
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now - 60));

    return Outbox::queue($message, $store, $sync, $now) && $spedite === []
        && $store->rows[1]['status'] === 'queued' && $store->rows[1]['next'] === Outbox::time($now);
});

check('se la coda non si scrive queue() spedisce subito', static function () use ($message, $sync, &$spedite, $now): bool {
    $spedite = [];
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now));
    $store->broken = true;

    return Outbox::queue($message, $store, $sync, $now) && count($spedite) === 1;
});

check('il worker riceve il messaggio identico a quello accodato e svuota il payload', static function () use ($message, $now): bool {
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now));
    Outbox::queue($message, $store, null, $now);
    $ricevuto = null;
    $result = Outbox::process($store, static function (...$args) use (&$ricevuto): array { $ricevuto = $args; return ['sent' => true, 'service' => 'brevo', 'error' => '']; }, null, $now);

    return $ricevuto === Outbox::arguments($message) && $result['sent'] === 1
        && $store->rows[1]['status'] === 'sent' && $store->rows[1]['attempts'] === 1 && $store->rows[1]['payload'] === null;
});

check('gli argomenti della coda sono quelli di sendMail(): credenziali null, poi le opzioni', static fn (): bool =>
    Outbox::arguments($message) === ['negozio@example.test', 'cliente@example.test', 'Ordine', $message['body'], ['/tmp/fattura.pdf'], 'basic', null, ['cc' => [], 'bcc' => []]]
);

check('CC e BCC accodati tornano al worker normalizzati', static function () use ($message, $now): bool {
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now));
    Outbox::queue($message + ['options' => ['cc' => 'azienda@example.test; Cliente@example.test', 'bcc' => ['archivio@example.test', 'azienda@example.test']]], $store, null, $now);
    $ricevuto = null;
    Outbox::process($store, static function (...$args) use (&$ricevuto): array { $ricevuto = $args; return ['sent' => true, 'service' => 'brevo', 'error' => '']; }, null, $now);

    return ($ricevuto[7] ?? null) === ['cc' => ['azienda@example.test'], 'bcc' => ['archivio@example.test']];
});

check('il fallback senza scheduler passa CC e BCC a sendMail()', static function () use ($message, $sync, &$spedite, $now): bool {
    $spedite = [];
    Outbox::queue($message + ['options' => ['cc' => 'azienda@example.test']], new MemoriaOutbox(null), $sync, $now);

    return count($spedite[0] ?? []) === 8 && $spedite[0][6] === null && ($spedite[0][7]['cc'] ?? null) === ['azienda@example.test'];
});

check('una riga accodata prima dei CC (payload senza options) parte con liste vuote', static function () use ($message, $now): bool {
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now));
    $store->rows[1] = ['status' => 'queued', 'attempts' => 0, 'next' => Outbox::time($now), 'payload' => json_encode($message), 'error' => '', 'service' => ''];
    $ricevuto = null;
    Outbox::process($store, static function (...$args) use (&$ricevuto): array { $ricevuto = $args; return ['sent' => true, 'service' => 'brevo', 'error' => '']; }, null, $now);

    return ($ricevuto[7] ?? null) === ['cc' => [], 'bcc' => []] && $store->rows[1]['status'] === 'sent';
});

check('queueMail con un\'opzione sconosciuta è un errore di chi chiama', static function () use ($message, $now): bool {
    try {
        Outbox::queue($message + ['options' => ['ccn' => 'a@example.test']], new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now)), null, $now);
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
});

check('un errore temporaneo riprova con attese crescenti fino a MAX_ATTEMPTS', static function () use ($message, $now, $errore): bool {
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now));
    Outbox::queue($message, $store, null, $now);
    $timeout = $errore('SMTP connect() failed.');
    $t = $now;
    $attese = [];

    for ($i = 1; $i < Outbox::MAX_ATTEMPTS; $i++) {
        Outbox::process($store, $timeout, null, $t);
        if ($store->rows[1]['status'] !== 'queued' || $store->rows[1]['attempts'] !== $i) {
            return false;
        }
        $next = strtotime($store->rows[1]['next'].' UTC');
        $attese[] = $next - $t;
        Outbox::process($store, static fn (): array => throw new LogicException('partita prima del tempo'), null, $next - 1);
        $t = $next;
    }
    Outbox::process($store, $timeout, null, $t);

    return $attese === Outbox::RETRY_DELAYS && $store->rows[1]['status'] === 'failed'
        && $store->rows[1]['attempts'] === Outbox::MAX_ATTEMPTS && $store->rows[1]['payload'] === null;
});

check('un errore permanente chiude subito al primo tentativo', static function () use ($message, $now, $errore): bool {
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now));
    Outbox::queue($message, $store, null, $now);
    Outbox::process($store, $errore('SMTP Error: 550 5.1.1 User unknown'), null, $now);

    return $store->rows[1]['status'] === 'failed' && $store->rows[1]['attempts'] === 1;
});

check('permanent(): indirizzi rifiutati e allegati spariti sì, autenticazione e rete no', static fn (): bool =>
    Outbox::permanent('Invalid address:  (to): x')
    && Outbox::permanent('SMTP Error: The following recipients failed: a@b.c')
    && Outbox::permanent('Could not access file: /tmp/x.pdf')
    && Outbox::permanent('Allegato non trovato: /tmp/x.pdf')
    && Outbox::permanent('554 5.7.1 Message rejected')
    && !Outbox::permanent('SMTP Error: Could not authenticate. 535 5.7.8')
    && !Outbox::permanent('SMTP connect() failed.')
    && !Outbox::permanent('Brevo HTTP 500')
    && !Outbox::permanent('')
);

check('un\'eccezione del trasporto conta come errore temporaneo', static function () use ($message, $now): bool {
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now));
    Outbox::queue($message, $store, null, $now);
    Outbox::process($store, static fn (): array => throw new RuntimeException('rete giù'), null, $now);

    return $store->rows[1]['status'] === 'queued' && $store->rows[1]['error'] === 'rete giù';
});

check('una riga rimasta in invio oltre CLAIM_TIMEOUT torna in coda e riparte', static function () use ($message, $now, $riuscita): bool {
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now));
    Outbox::queue($message, $store, null, $now);
    $store->claim(1, Outbox::time($now + Outbox::CLAIM_TIMEOUT));
    $presto = Outbox::process($store, $riuscita, null, $now + Outbox::CLAIM_TIMEOUT - 1);
    $tardi = Outbox::process($store, $riuscita, null, $now + Outbox::CLAIM_TIMEOUT);

    return $presto['released'] === 0 && $presto['sent'] === 0
        && $tardi['released'] === 1 && $tardi['sent'] === 1 && $store->rows[1]['status'] === 'sent';
});

check('una riga già presa da un altro giro non viene spedita due volte', static function () use ($message, $now): bool {
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now));
    Outbox::queue($message, $store, null, $now);
    $volte = 0;
    $deliver = static function () use (&$volte, $store): array {
        $volte++;
        // Il secondo giro parte mentre il primo sta spedendo.
        if ($volte === 1) {
            Outbox::process($store, static function () use (&$volte): array { $volte++; return ['sent' => true]; }, null, $GLOBALS['now']);
        }
        return ['sent' => true, 'service' => 'brevo'];
    };
    Outbox::process($store, $deliver, null, $now);

    return $volte === 1;
});

check('vicino alla scadenza del giro il resto aspetta il prossimo', static function () use ($message, $now, $riuscita): bool {
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now));
    Outbox::queue($message, $store, null, $now);
    Outbox::queue($message, $store, null, $now);
    $result = Outbox::process($store, $riuscita, new Context([], microtime(true) + Outbox::DEADLINE_MARGIN - 1), $now);

    return $result['sent'] === 0 && $store->rows[1]['status'] === 'queued' && $store->rows[2]['status'] === 'queued';
});

check('BATCH limita le mail spedite per giro', static function () use ($message, $now, $riuscita): bool {
    $store = new MemoriaOutbox(gmdate('Y-m-d H:i:s', $now));
    Outbox::queue($message, $store, null, $now);
    Outbox::queue($message, $store, null, $now);

    return Outbox::process($store, $riuscita, null, $now, 1)['sent'] === 1 && $store->rows[2]['status'] === 'queued';
});

check('home: la card della coda appare solo quando una mail è in ritardo oltre STUCK_AFTER', static fn (): bool =>
    HomeStatus::mailQueue(null, $now, '/m/') === null
    && HomeStatus::mailQueue(Outbox::time($now - Outbox::STUCK_AFTER), $now, '/m/') === null
    && HomeStatus::mailQueue(Outbox::time($now - Outbox::STUCK_AFTER - 1), $now, '/m/')?->getSchema('variant') === 'warning'
);

summary();
