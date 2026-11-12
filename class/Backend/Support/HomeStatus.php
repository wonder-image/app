<?php

namespace Wonder\Backend\Support;

use Throwable;
use Wonder\App\Logger;
use Wonder\App\Media\ResizeQueue;
use Wonder\App\Module\ConfigRepository;
use Wonder\App\Support\Mail\Outbox;
use Wonder\Backend\Contracts\HomeStatusItem;
use Wonder\Backend\Home\ErrorStatus;
use Wonder\Backend\Home\MailQueueStatus;
use Wonder\Backend\Home\MediaResizeStatus;
use Wonder\Backend\Home\SchedulerStatus;
use Wonder\Elements\Components\StatusCard;

/**
 * Fascia di stato in cima alla home del backend. Le voci sono classi
 * `HomeStatusItem` indicizzate per chiave: il core registra `scheduler`,
 * `errors`, `mail_queue` e `media_resize`, i moduli abilitati aggiungono, sostituiscono o tolgono (`false`)
 * voci con `backend.home_status` nella propria configurazione e il sito ha
 * l'ultima parola con `HomeStatus::set()`. Ogni voce decide da sé chi la
 * vede; se una voce solleva un'eccezione sparisce, la home resta.
 */
final class HomeStatus
{
    /** Oltre questi secondi senza heartbeat lo scheduler è considerato fermo. */
    public const HEARTBEAT_TOLERANCE = 300;

    /** @var array<string, class-string<HomeStatusItem>> */
    public const CORE = [
        'scheduler' => SchedulerStatus::class,
        'errors' => ErrorStatus::class,
        'mail_queue' => MailQueueStatus::class,
        'media_resize' => MediaResizeStatus::class,
    ];

    /** @var array<string, class-string<HomeStatusItem>|false> */
    private static array $overrides = [];

    /** Sostituisce la voce `$key` con `$class`, o la toglie con `false`. */
    public static function set(string $key, string|false $class): void
    {
        self::$overrides[$key] = $class;
    }

    public static function reset(): void
    {
        self::$overrides = [];
    }

    /**
     * Classi delle voci attive per chiave: core, poi moduli, poi sito.
     *
     * @return array<string, class-string<HomeStatusItem>>
     */
    public static function sources(?array $configs = null): array
    {
        $sources = self::CORE;

        foreach ($configs ?? ConfigRepository::all() as $config) {
            foreach ((array) ($config['backend']['home_status'] ?? []) as $key => $class) {
                if (is_string($key)) {
                    $sources[$key] = $class;
                }
            }
        }

        $sources = array_merge($sources, self::$overrides);

        return array_filter(
            $sources,
            static fn (mixed $class): bool => is_string($class) && is_subclass_of($class, HomeStatusItem::class)
        );
    }

    /** @return list<StatusCard> */
    public static function items(array $authorities, string $backendUrl, ?array $configs = null): array
    {
        $items = [];

        foreach (self::sources($configs) as $key => $class) {
            try {
                $source = new $class();
                $card = $source->status($authorities, $backendUrl);
            } catch (Throwable $exception) {
                Logger::log($exception, 'backend', 'home_status', 'ERROR', 'error', ['key' => $key, 'class' => $class], false);
                continue;
            }

            if ($card instanceof StatusCard) {
                $items[] = [$source->order(), $card];
            }
        }

        usort($items, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_column($items, 1);
    }

    public static function scheduler(?string $heartbeat, int $failures, int $now, string $href): StatusCard
    {
        $seen = $heartbeat ? strtotime($heartbeat.' UTC') : false;

        if ($seen === false) {
            return self::card('bi-clock-history', 'Scheduler', 'Mai avviato', 'Configura il cron sul server', 'warning', $href);
        }

        if ($now - $seen > self::HEARTBEAT_TOLERANCE) {
            return self::card('bi-clock-history', 'Scheduler', 'Fermo', 'Ultimo contatto '.gmdate('d/m/Y H:i', $seen).' UTC', 'danger', $href);
        }

        $detail = $failures > 0
            ? ($failures === 1 ? '1 esecuzione fallita' : $failures.' esecuzioni fallite').' nelle ultime 24 ore'
            : 'Nessun errore nelle ultime 24 ore';

        return self::card('bi-clock-history', 'Scheduler', 'Attivo', $detail, $failures > 0 ? 'warning' : 'success', $href);
    }

    public static function errors(int $open, string $href): StatusCard
    {
        return $open > 0
            ? self::card('bi-bug', 'Errori', (string) $open, $open === 1 ? 'Errore da controllare' : 'Errori da controllare', 'danger', $href)
            : self::card('bi-bug', 'Errori', '0', 'Nessun errore aperto', 'success', $href);
    }

    /** Card solo quando la coda email è ferma (vedi `Outbox::stuck()`), altrimenti null. */
    public static function mailQueue(?string $oldestDue, int $now, string $href): ?StatusCard
    {
        if (!Outbox::stuck($oldestDue, $now)) {
            return null;
        }

        return self::card('bi-envelope-exclamation', 'Coda email', 'Ferma', 'Email in attesa dal '.gmdate('d/m/Y H:i', (int) strtotime($oldestDue.' UTC')).' UTC', 'warning', $href);
    }

    /**
     * Card solo se ci sono ridimensionamenti falliti o se la coda è ferma
     * (vedi `ResizeQueue::stuck()`), altrimenti null.
     */
    public static function mediaResize(?string $oldestPending, int $failed, int $now, string $href): ?StatusCard
    {
        if ($failed > 0) {
            return self::card('bi-images', 'Immagini', (string) $failed, ($failed === 1 ? 'Ridimensionamento non riuscito' : 'Ridimensionamenti non riusciti').' negli ultimi 7 giorni', 'warning', $href);
        }

        if (!ResizeQueue::stuck($oldestPending, $now)) {
            return null;
        }

        return self::card('bi-images', 'Immagini', 'In attesa', 'Ridimensionamenti in coda dal '.gmdate('d/m/Y H:i', (int) strtotime($oldestPending.' UTC')).' UTC', 'warning', $href);
    }

    private static function card(string $icon, string $title, string $value, string $detail, string $variant, string $href): StatusCard
    {
        return StatusCard::make($title, $value)->icon($icon)->detail($detail)->variant($variant)->href($href);
    }
}
