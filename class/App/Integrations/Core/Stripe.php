<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;
use Wonder\App\Integrations\IntegrationStatus;
use Wonder\App\Path;
use Wonder\Elements\Components\Badge;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\Text;

/**
 * Stripe Connect: un account collegato per Produzione e uno per Test. La card
 * segue l'ambiente attivo; nel modal ogni ambiente ha il suo stato e i
 * bottoni per collegare account e webhook.
 */
final class Stripe extends Integration
{
    /** Parametro `account` => [etichetta, chiave della piattaforma, campi dell'account]. */
    private const ACCOUNTS = [
        'production' => ['Produzione', 'stripe_private_key', ['stripe_account_id', 'stripe_public_key', 'stripe_webhook_secret']],
        'test' => ['Test', 'stripe_test_key', ['stripe_test_account_id', 'stripe_test_public_key', 'stripe_test_webhook_secret']],
    ];

    public function key(): string
    {
        return 'stripe';
    }

    public function name(): string
    {
        return 'Stripe';
    }

    public function category(): string
    {
        return 'payments';
    }

    public function icon(): string
    {
        return 'bi-stripe';
    }

    public function description(): string
    {
        return 'Pagamenti con carta tramite Stripe Connect.';
    }

    public function guide(): string
    {
        return 'Qui scegli l\'ambiente attivo e colleghi gli account: chiavi e segreti arrivano da Stripe con «Collega» e «Collega webhook».';
    }

    public function docsUrl(): ?string
    {
        return 'https://docs.stripe.com/connect';
    }

    public function fields(): array
    {
        return [
            'stripe_test' => 12,
            'stripe_account_id' => 12,
            'stripe_public_key' => 6,
            'stripe_webhook_secret' => 6,
            'stripe_test_account_id' => 12,
            'stripe_test_public_key' => 6,
            'stripe_test_webhook_secret' => 6,
        ];
    }

    public function modes(): array
    {
        return ['stripe_test'];
    }

    public function order(): int
    {
        return 80;
    }

    public function required(array $values): array
    {
        return self::ACCOUNTS[$this->testMode($values) ? 'test' : 'production'][2];
    }

    public function testMode(array $values): bool
    {
        return filter_var($values['stripe_test'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function panel(array $values, IntegrationStatus $status): array
    {
        $active = $this->testMode($values) ? 'test' : 'production';
        $rows = [];

        foreach (self::ACCOUNTS as $account => [$label, $platformKey, $columns]) {
            $rows[] = $this->accountRow($account, $label, $account === $active, self::filled($values[$platformKey] ?? null), $columns, $values);
        }

        return [(new Container())->columns(1)->gap(2)->columnSpan(12)->components($rows)];
    }

    /**
     * @param list<string> $columns
     * @param array<string, mixed> $values
     */
    private function accountRow(string $account, string $label, bool $active, bool $platform, array $columns, array $values): Card
    {
        $filled = count(array_filter($columns, static fn (string $column): bool => self::filled($values[$column] ?? null)));
        $state = match (true) {
            $filled === count($columns) => IntegrationStatus::CONNECTED,
            $filled > 0 => IntegrationStatus::PARTIAL,
            default => IntegrationStatus::DISCONNECTED,
        };
        $status = new IntegrationStatus($state);
        $api = (new Path)->appApi.'/service/stripe';
        $badges = [Badge::make($status->label())->variant($status->variant())->pill()];

        if ($active) {
            $badges[] = Badge::make('Attivo')->variant('primary')->outline()->pill();
        }

        $buttons = [
            Button::to($api.'/onboarding/?account='.$account, 'Collega')->size('sm')->disabled(!$platform),
            Button::to($api.'/connect/?account='.$account, 'Collega webhook')->size('sm')->variant('secondary')->outline()->disabled(!$platform),
        ];

        $components = [
            '<div class="d-flex flex-wrap align-items-center gap-2">'
                .'<span class="fw-semibold me-auto">'.e($label).'</span>'
                .implode('', array_map(static fn (Badge $badge): string => (string) $badge, $badges))
                .'</div>',
        ];

        if (!$platform) {
            $components[] = Text::make('Chiave piattaforma mancante: senza la chiave segreta della piattaforma l\'account non si collega.')->small()->color('warning-emphasis')->tag('div');
        }

        $components[] = '<div class="d-flex flex-wrap gap-2">'
            .implode('', array_map(static fn (Button $button): string => (string) $button, $buttons))
            .'</div>';

        return (new Card())->components($components);
    }
}
