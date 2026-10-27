<?php

namespace Wonder\Cloudflare;

use RuntimeException;

/**
 * Client minimo per l'API v4 di Cloudflare. Il token va solo nell'header
 * `Authorization` e non compare mai negli errori.
 */
class Client
{
    public const ENDPOINT = 'https://api.cloudflare.com/client/v4';

    public function __construct(
        private readonly string $token,
        private readonly int $timeout = 20
    ) {
        if (trim($token) === '') {
            throw new RuntimeException('Token Cloudflare mancante.');
        }
    }

    public function get(string $path, array $query = []): mixed
    {
        return $this->request('GET', $path, null, $query);
    }

    public function patch(string $path, array $body): mixed
    {
        return $this->request('PATCH', $path, $body);
    }

    public function put(string $path, array $body): mixed
    {
        return $this->request('PUT', $path, $body);
    }

    public function post(string $path, array $body): mixed
    {
        return $this->request('POST', $path, $body);
    }

    /**
     * Zona che copre il dominio: prova il dominio e poi i genitori
     * (shop.esempio.it → esempio.it).
     *
     * @return array{id:string,name:string,account_id:string,plan:string,name_servers:array}|null
     */
    public function findZone(string $domain): ?array
    {
        $labels = explode('.', strtolower(trim($domain, '. ')));

        while (count($labels) >= 2) {
            $name = implode('.', $labels);
            $zones = $this->get('/zones', ['name' => $name]);

            if (is_array($zones) && isset($zones[0]['id'])) {
                $zone = $zones[0];

                return [
                    'id' => (string) $zone['id'],
                    'name' => (string) ($zone['name'] ?? $name),
                    'account_id' => (string) ($zone['account']['id'] ?? ''),
                    'plan' => (string) ($zone['plan']['legacy_id'] ?? $zone['plan']['name'] ?? ''),
                    'name_servers' => (array) ($zone['name_servers'] ?? []),
                ];
            }

            array_shift($labels);
        }

        return null;
    }

    /**
     * @throws CloudflareException con codice HTTP e messaggi di Cloudflare
     */
    public function request(string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Estensione cURL non disponibile.');
        }

        $url = self::ENDPOINT.$path.($query !== [] ? '?'.http_build_query($query) : '');
        $curl = curl_init($url);

        $headers = [
            'Authorization: Bearer '.trim($this->token),
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
        }

        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($curl, $options);

        try {
            $raw = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

            if ($raw === false) {
                throw new RuntimeException('Richiesta a Cloudflare fallita (cURL '.curl_errno($curl).').');
            }
        } finally {
            unset($curl);
        }

        $data = json_decode((string) $raw, true);

        if (!is_array($data) || ($data['success'] ?? false) !== true) {
            $errors = [];
            foreach ((array) ($data['errors'] ?? []) as $error) {
                $errors[] = trim(($error['code'] ?? '').' '.($error['message'] ?? ''));
            }

            throw new CloudflareException($status, $errors, $method.' '.$path);
        }

        return $data['result'] ?? null;
    }
}
