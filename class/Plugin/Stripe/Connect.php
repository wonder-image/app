<?php

namespace Wonder\Plugin\Stripe;

use InvalidArgumentException;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
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

    /** @var array<int, string> eventi aggiunti dai moduli, oltre a EVENTS */
    private static array $extraEvents = [];

    private StripeClient $client;

    /** @var array{stripe_account: string} */
    private array $options;

    public function __construct(string $secretKey, string $accountId)
    {
        $this->client = new StripeClient([ 'api_key' => $secretKey, 'stripe_version' => Stripe::API_VERSION ]);
        $this->options = ['stripe_account' => $accountId];
    }

    /**
     * Rifà l'endpoint per questo url sul conto della piattaforma, come
     * webhook Connect: Stripe non lascia creare endpoint sui conti collegati.
     * Arrivano gli eventi di tutti i conti collegati, ognuno con `account`:
     * chi li riceve scarta quelli degli altri. Dà il segreto nuovo.
     * Senza `$events` registra EVENTS più quelli aggiunti con addEvents().
     *
     * @param array<int, string>|null $events
     */
    public function webhook(string $url, ?array $events = null): string
    {
        $events = $events === null ? self::events() : self::normalizeEvents($events);

        if ($events === []) {
            throw new InvalidArgumentException('nessun evento da registrare sul webhook.');
        }

        foreach ($this->client->webhookEndpoints->all(['limit' => 100])->autoPagingIterator() as $endpoint) {
            if ((string) $endpoint->url === $url) {
                $this->client->webhookEndpoints->delete((string) $endpoint->id);
            }
        }

        $endpoint = $this->client->webhookEndpoints->create(['url' => $url, 'enabled_events' => $events, 'connect' => true]);

        return (string) $endpoint->secret;
    }

    /**
     * Aggiunge eventi all'elenco del webhook: un modulo li registra al suo
     * bootstrap. Il core non conosce gli eventi dei moduli.
     *
     * @param array<int, string> $types
     */
    public static function addEvents(array $types): void
    {
        self::$extraEvents = self::normalizeEvents([...self::$extraEvents, ...$types]);
    }

    /**
     * EVENTS più gli eventi registrati, senza doppioni.
     *
     * @return array<int, string>
     */
    public static function events(): array
    {
        return self::normalizeEvents([...self::EVENTS, ...self::$extraEvents]);
    }

    /**
     * @param array<int, mixed> $types
     * @return array<int, string>
     */
    private static function normalizeEvents(array $types): array
    {
        $events = [];

        foreach ($types as $type) {
            $type = is_string($type) ? trim($type) : '';

            if ($type === '') {
                throw new InvalidArgumentException('evento Stripe non valido.');
            }

            $events[$type] = true;
        }

        return array_keys($events);
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
            try {
                $connect->domain($domain);
            } catch (ApiErrorException) {
                // Il dominio serve solo ai wallet: il segreto va salvato comunque.
            }
        }

        return $secret;
    }

    public static function secretColumn(string $env): string
    {
        return $env === 'test' ? 'stripe_test_webhook_secret' : 'stripe_webhook_secret';
    }
}
