<?php

namespace Wonder\Localization\Machine;

use RuntimeException;

/**
 * Google Cloud Translation v2 con `format=html`, che rispetta
 * `translate="no"`. La chiave server va nell'header `X-Goog-Api-Key`, mai
 * nell'URL. Il trasporto è iniettabile: `fn(string $url, string $body,
 * list<string> $headers): string` restituisce il corpo JSON della risposta.
 */
class GoogleTranslator implements Translator
{
    public const ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';
    public const BATCH = 128;

    /** @var callable */
    private $transport;

    public function __construct(
        private readonly string $apiKey,
        ?callable $transport = null,
        private readonly int $timeout = 30
    ) {
        if (trim($apiKey) === '') {
            throw new RuntimeException('Chiave server Google (GCP_API_KEY) non configurata.');
        }

        $this->transport = $transport ?? $this->curl(...);
    }

    public function key(): string
    {
        return 'google';
    }

    public function translate(array $html, string $source, string $target): array
    {
        $result = [];

        foreach (array_chunk(array_values($html), static::BATCH) as $chunk) {
            $body = implode('&', array_map(static fn (string $text): string => 'q='.rawurlencode($text), $chunk))
                .'&'.http_build_query(['source' => $source, 'target' => $target, 'format' => 'html']);
            $response = ($this->transport)(static::ENDPOINT, $body, [
                'X-Goog-Api-Key: '.$this->apiKey,
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ]);
            $data = json_decode((string) $response, true);
            $translations = is_array($data) ? ($data['data']['translations'] ?? null) : null;

            if (!is_array($translations) || count($translations) !== count($chunk)) {
                throw new RuntimeException('Risposta di Google Translate non valida.');
            }

            foreach ($translations as $translation) {
                $result[] = (string) ($translation['translatedText'] ?? '');
            }
        }

        return $result;
    }

    private function curl(string $url, string $body, array $headers): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Estensione cURL non disponibile.');
        }

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        try {
            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

            if ($response === false) {
                throw new RuntimeException('Richiesta a Google fallita (cURL '.curl_errno($curl).').');
            }
        } finally {
            unset($curl);
        }

        if ($status !== 200) {
            $data = json_decode((string) $response, true);
            $reason = is_array($data) ? trim((string) ($data['error']['status'] ?? '')) : '';

            throw new RuntimeException('Google ha risposto HTTP '.$status.($reason !== '' ? ' ('.$reason.')' : '').'.');
        }

        return (string) $response;
    }
}
