<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

/**
 * Client HTTP finto di stripe-php: risponde con le risposte in coda e tiene
 * le richieste per i controlli. Nessuna chiamata esce dalla macchina.
 */
final class FakeStripeHttp implements \Stripe\HttpClient\ClientInterface
{
    /** @var list<array{0: int, 1: array}> */
    private array $responses = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, params: array}> */
    public array $requests = [];

    public static function install(): self
    {
        $fake = new self();
        \Stripe\ApiRequestor::setHttpClient($fake);

        return $fake;
    }

    public function queue(int $code, array $body): void
    {
        $this->responses[] = [$code, $body];
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $named = [];

        foreach ($headers as $line) {
            [$name, $value] = array_map('trim', explode(':', (string) $line, 2)) + [1 => ''];
            $named[strtolower($name)] = $value;
        }

        $this->requests[] = ['method' => strtoupper((string) $method), 'url' => (string) $absUrl, 'headers' => $named, 'params' => (array) $params];
        [$code, $body] = array_shift($this->responses) ?? [500, ['error' => ['message' => 'Nessuna risposta in coda']]];

        return [json_encode($body), $code, []];
    }

    public function header(int $i, string $name): ?string
    {
        return $this->requests[$i]['headers'][strtolower($name)] ?? null;
    }

    public function path(int $i): string
    {
        return (string) parse_url($this->requests[$i]['url'], PHP_URL_PATH);
    }
}
