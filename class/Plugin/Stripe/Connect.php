<?php

namespace Wonder\Plugin\Stripe;

use RuntimeException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Wonder\App\Credentials;

/**
 * Collega un ambiente del conto Stripe al sito: endpoint del webhook e
 * dominio per i wallet. Lavora con le chiavi dell'ambiente scelto, che può
 * non essere quello attivo.
 */
final class Connect
{
    public const EVENTS = [
        'payment_intent.succeeded',
        'payment_intent.payment_failed',
        'payment_intent.canceled',
        'charge.refunded',
    ];

    private StripeClient $client;

    /** @var array{stripe_account: string} */
    private array $options;

    public function __construct(string $secretKey, string $accountId)
    {
        $this->client = new StripeClient($secretKey);
        $this->options = ['stripe_account' => $accountId];
    }

    /** Rifà l'endpoint per questo url: quello vecchio si cancella. Dà il segreto nuovo. */
    public function webhook(string $url): string
    {
        foreach ($this->client->webhookEndpoints->all(['limit' => 100], $this->options)->data as $endpoint) {
            if ((string) $endpoint->url === $url) {
                $this->client->webhookEndpoints->delete((string) $endpoint->id, [], $this->options);
            }
        }

        $endpoint = $this->client->webhookEndpoints->create(['url' => $url, 'enabled_events' => self::EVENTS], $this->options);

        return (string) $endpoint->secret;
    }

    /** Registra il dominio per Apple Pay e gli altri wallet. Già registrato va bene. */
    public function domain(string $domain): void
    {
        try {
            $this->client->paymentMethodDomains->create(['domain_name' => $domain], $this->options);
        } catch (InvalidRequestException $e) {
            if (!str_contains(strtolower($e->getMessage()), 'already')) {
                throw $e;
            }
        }
    }

    /** Collega l'ambiente `test` o `live` e dà il segreto del webhook da salvare. */
    public static function environment(string $env, string $webhookUrl, string $domain, ?object $api = null): string
    {
        $api ??= Credentials::api();
        [$key, $account] = $env === 'test'
            ? [(string) $api->stripe_test_key, (string) $api->stripe_test_account_id]
            : [(string) $api->stripe_private_key, (string) $api->stripe_account_id];

        if (trim($key) === '' || trim($account) === '') {
            throw new RuntimeException('manca la chiave o il conto collegato di '.($env === 'test' ? 'test' : 'produzione').'.');
        }

        $connect = new self($key, $account);
        $secret = $connect->webhook($webhookUrl);

        if ($domain !== '') {
            $connect->domain($domain);
        }

        return $secret;
    }

    public static function secretColumn(string $env): string
    {
        return $env === 'test' ? 'stripe_test_webhook_secret' : 'stripe_webhook_secret';
    }
}
