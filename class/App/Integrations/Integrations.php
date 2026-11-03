<?php

namespace Wonder\App\Integrations;

use Throwable;
use Wonder\App\Integrations\Core\Apple;
use Wonder\App\Integrations\Core\BrevoMail;
use Wonder\App\Integrations\Core\BrevoMarketing;
use Wonder\App\Integrations\Core\FattureInCloud;
use Wonder\App\Integrations\Core\GoogleCloud;
use Wonder\App\Integrations\Core\GoogleMaps;
use Wonder\App\Integrations\Core\GoogleSignIn;
use Wonder\App\Integrations\Core\Klaviyo;
use Wonder\App\Integrations\Core\Nexi;
use Wonder\App\Integrations\Core\PayPal;
use Wonder\App\Integrations\Core\RecaptchaV2;
use Wonder\App\Integrations\Core\RecaptchaV3;
use Wonder\App\Integrations\Core\Smtp;
use Wonder\App\Integrations\Core\Stripe;
use Wonder\App\Integrations\Core\WonderImage;
use Wonder\App\Logger;
use Wonder\App\Module\ConfigRepository;

/**
 * Le integrazioni della pagina Credenziali, indicizzate per chiave: il core
 * registra le sue, i moduli abilitati aggiungono, sostituiscono o tolgono
 * (`false`) voci con `backend.integrations` nella propria configurazione e il
 * sito ha l'ultima parola con `Integrations::set()`. Un'integrazione che
 * solleva un'eccezione viene registrata nel log e saltata, la pagina resta.
 */
final class Integrations
{
    /** @var array<string, string> */
    public const CATEGORIES = [
        'wonder' => 'Wonder Image',
        'google' => 'Google',
        'access' => 'Accesso',
        'payments' => 'Pagamenti',
        'email' => 'Email',
        'marketing' => 'Marketing',
        'invoicing' => 'Fatturazione',
    ];

    public const OTHER = 'Altro';

    /** @var array<string, class-string<Integration>> */
    public const CORE = [
        'wonder-image' => WonderImage::class,
        'gcp' => GoogleCloud::class,
        'recaptcha-v2' => RecaptchaV2::class,
        'recaptcha-v3' => RecaptchaV3::class,
        'google-maps' => GoogleMaps::class,
        'google-sign-in' => GoogleSignIn::class,
        'apple' => Apple::class,
        'stripe' => Stripe::class,
        'paypal' => PayPal::class,
        'nexi' => Nexi::class,
        'smtp' => Smtp::class,
        'brevo-mail' => BrevoMail::class,
        'brevo' => BrevoMarketing::class,
        'klaviyo' => Klaviyo::class,
        'fatture-in-cloud' => FattureInCloud::class,
    ];

    /** @var array<string, class-string<Integration>|false> */
    private static array $overrides = [];

    /** Sostituisce l'integrazione `$key` con `$class`, o la toglie con `false`. */
    public static function set(string $key, string|false $class): void
    {
        self::$overrides[$key] = $class;
    }

    public static function reset(): void
    {
        self::$overrides = [];
    }

    /**
     * Classi delle integrazioni attive per chiave: core, poi moduli, poi sito.
     *
     * @return array<string, class-string<Integration>>
     */
    public static function sources(?array $configs = null): array
    {
        $sources = self::CORE;

        foreach ($configs ?? ConfigRepository::all() as $config) {
            foreach ((array) ($config['backend']['integrations'] ?? []) as $key => $class) {
                if (is_string($key)) {
                    $sources[$key] = $class;
                }
            }
        }

        $sources = array_merge($sources, self::$overrides);

        return array_filter(
            $sources,
            static fn (mixed $class): bool => is_string($class) && is_subclass_of($class, Integration::class)
        );
    }

    /**
     * Le integrazioni attive, in ordine.
     *
     * @return array<string, Integration>
     */
    public static function all(?array $configs = null): array
    {
        $items = [];

        foreach (self::sources($configs) as $key => $class) {
            try {
                $items[$key] = new $class();
            } catch (Throwable $exception) {
                self::log($exception, $key, $class);
            }
        }

        uasort($items, static fn (Integration $a, Integration $b): int => $a->order() <=> $b->order());

        return $items;
    }

    public static function find(string $key, ?array $configs = null): ?Integration
    {
        return self::all($configs)[$key] ?? null;
    }

    /**
     * Le integrazioni per categoria, nell'ordine di `CATEGORIES`; le
     * categorie sconosciute finiscono in fondo sotto «Altro».
     *
     * @param array<string, Integration> $items
     * @return array<string, array{title: string, items: array<string, Integration>}>
     */
    public static function grouped(array $items): array
    {
        $groups = [];

        foreach (self::CATEGORIES as $category => $title) {
            $groups[$category] = ['title' => $title, 'items' => []];
        }

        foreach ($items as $key => $integration) {
            $category = $integration->category();

            if (!isset($groups[$category])) {
                $category = 'other';
                $groups[$category] ??= ['title' => self::OTHER, 'items' => []];
            }

            $groups[$category]['items'][$key] = $integration;
        }

        return array_filter($groups, static fn (array $group): bool => $group['items'] !== []);
    }

    /** Il campo arriva dal `.env` (stesso nome in maiuscolo, valore non vuoto). */
    public static function env(string $column): bool
    {
        return trim((string) ($_ENV[strtoupper($column)] ?? '')) !== '';
    }

    /**
     * I valori in uso: il `.env` vince sulla riga di `security`, come in
     * `Credentials`.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function effectiveValues(array $row, array $columns = []): array
    {
        $values = $row;

        foreach (array_unique([...array_keys($row), ...$columns]) as $column) {
            if (is_string($column) && self::env($column)) {
                $values[$column] = trim((string) $_ENV[strtoupper($column)]);
            }
        }

        return $values;
    }

    /**
     * Lo stato di ogni integrazione. Una che dipende da un'altra registrata
     * ma non connessa (per esempio da Google Cloud) resta al massimo Parziale.
     *
     * @param array<string, Integration> $items
     * @param array<string, mixed> $row
     * @return array<string, IntegrationStatus>
     */
    public static function statuses(array $items, array $row): array
    {
        $columns = [];

        foreach ($items as $integration) {
            $columns = [...$columns, ...array_keys($integration->fields())];
        }

        $values = self::effectiveValues($row, $columns);
        $statuses = [];

        foreach ($items as $key => $integration) {
            try {
                $env = array_values(array_filter(array_keys($integration->fields()), [self::class, 'env']));
                $statuses[$key] = $integration->status($values)->with(env: $env);
            } catch (Throwable $exception) {
                self::log($exception, $key, $integration::class);
            }
        }

        foreach ($statuses as $key => $status) {
            $dependency = $items[$key]->requires();

            if ($dependency === null || !isset($items[$dependency]) || ($statuses[$dependency] ?? null)?->connected()) {
                continue;
            }

            $statuses[$key] = $status->with(
                state: $status->connected() ? IntegrationStatus::PARTIAL : null,
                blockedBy: $items[$dependency]->name(),
            );
        }

        return $statuses;
    }

    private static function log(Throwable $exception, string $key, string $class): void
    {
        Logger::log($exception, 'backend', 'integrations', 'ERROR', 'error', ['key' => $key, 'class' => $class], false);
    }
}
