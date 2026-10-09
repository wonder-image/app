<?php

namespace Wonder\Plugin\Stripe;

use Stripe\Customer;
use Stripe\StripeClient;

/**
 * Intenti di pagamento, Customer e rimborsi sul conto collegato, con le
 * chiavi che passa chi chiama.
 *
 * Non estende `Stripe`: quella prende sempre le chiavi dell'ambiente attivo,
 * mentre il webhook e il riallineamento lavorano sull'ambiente del pagamento.
 */
final class PaymentIntent
{
    private StripeClient $client;

    /** @var array{stripe_account: string} */
    private array $options;

    public function __construct(string $secretKey, string $accountId)
    {
        $this->client = new StripeClient([ 'api_key' => $secretKey, 'stripe_version' => Stripe::API_VERSION ]);
        $this->options = ['stripe_account' => $accountId];
    }

    public function create(array $params, string $idempotencyKey): \Stripe\PaymentIntent
    {
        return $this->client->paymentIntents->create($params, $this->options + ['idempotency_key' => $idempotencyKey]);
    }

    public function get(string $id): \Stripe\PaymentIntent
    {
        return $this->client->paymentIntents->retrieve($id, [], $this->options);
    }

    public function update(string $id, array $params): \Stripe\PaymentIntent
    {
        return $this->client->paymentIntents->update($id, $params, $this->options);
    }

    public function cancel(string $id): \Stripe\PaymentIntent
    {
        return $this->client->paymentIntents->cancel($id, [], $this->options);
    }

    public function createCustomer(array $params, string $idempotencyKey): Customer
    {
        return $this->client->customers->create($params, $this->options + ['idempotency_key' => $idempotencyKey]);
    }

    /** @return list<array{id: string, amount: int, status: string}> */
    public function refundsOf(string $chargeId): array
    {
        $refunds = [];

        foreach ($this->client->refunds->all(['charge' => $chargeId, 'limit' => 100], $this->options)->autoPagingIterator() as $refund) {
            $refunds[] = ['id' => (string) $refund->id, 'amount' => (int) $refund->amount, 'status' => (string) $refund->status];
        }

        return $refunds;
    }
}
