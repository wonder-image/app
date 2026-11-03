<?php

namespace Wonder\Localization\Catalog;

use Wonder\App\LegacyGlobals;

/**
 * L'anteprima di un import fra il primo e il secondo POST: un file in
 * `storage/translations/`, legato alla sessione e valido un'ora. Dal client
 * torna solo il token.
 */
final class ImportStore
{
    public const TTL = 3600;

    public function __construct(
        private readonly ?string $directory = null,
        private readonly ?string $sessionId = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): string
    {
        $this->purge();

        $token = bin2hex(random_bytes(16));
        $dir = $this->directory();

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($this->path($token), json_encode([
            'session' => $this->sessionHash(),
            'expires' => time() + self::TTL,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $token;
    }

    /** @return array<string, mixed>|null */
    public function load(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token) || !is_file($this->path($token))) {
            return null;
        }

        $stored = json_decode((string) file_get_contents($this->path($token)), true);

        if (!is_array($stored) || ($stored['expires'] ?? 0) < time()
            || !hash_equals((string) ($stored['session'] ?? ''), $this->sessionHash())) {
            return null;
        }

        return is_array($stored['data'] ?? null) ? $stored['data'] : null;
    }

    public function delete(string $token): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) && is_file($this->path($token))) {
            unlink($this->path($token));
        }
    }

    private function purge(): void
    {
        foreach (glob($this->directory().'/*.json') ?: [] as $file) {
            if (filemtime($file) < time() - self::TTL) {
                @unlink($file);
            }
        }
    }

    private function path(string $token): string
    {
        return $this->directory().'/'.$token.'.json';
    }

    private function directory(): string
    {
        return rtrim($this->directory ?? rtrim((string) LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : ''), '/').'/storage/translations', '/');
    }

    private function sessionHash(): string
    {
        return hash('sha256', $this->sessionId ?? (session_status() === PHP_SESSION_ACTIVE ? session_id() : ''));
    }
}
