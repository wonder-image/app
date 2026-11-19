<?php

namespace Wonder\App\Storage;

/**
 * I due storage del sito, costruiti da `ROOT`, `APP_URL` e dalle variabili
 * d'ambiente:
 *
 * - `public()`: i media serviti dal web (`ROOT/assets/upload`, indirizzo
 *   `APP_URL/assets/upload`). Solo qui avverrà la ricerca a due livelli.
 * - `private()`: file riservati (`ROOT/storage`), senza indirizzo pubblico e
 *   senza ricerca in altri ambienti.
 *
 * Variabili: `STORAGE_LAYOUT` (`flat`, predefinito, oppure `scoped`) e
 * `STORAGE_TENANT` (predefinito `default`; con `scoped` va impostato, perché
 * il tenant finisce nel percorso e non deve cambiare tra gli ambienti).
 * Con la configurazione predefinita percorsi e indirizzi sono quelli di
 * sempre.
 */
final class StorageManager
{
    private function __construct(
        private readonly Storage $public,
        private readonly Storage $private,
        private readonly PathResolver $publicResolver,
        private readonly PathResolver $privateResolver,
    ) {
    }

    /** @param array<string, mixed> $env di solito `$_ENV` (con `APP_ENV` valido) */
    public static function fromEnvironment(array $env, string $root, string $appUrl): self
    {
        $layout = self::value($env, 'STORAGE_LAYOUT') ?: PathResolver::FLAT;
        $tenant = self::value($env, 'STORAGE_TENANT');

        if ($layout === PathResolver::SCOPED && $tenant === '') {
            throw new StorageException('STORAGE_TENANT è obbligatorio con STORAGE_LAYOUT=scoped.');
        }

        $scope = new StorageScope($tenant !== '' ? $tenant : StorageScope::DEFAULT_TENANT, self::value($env, 'APP_ENV'));
        $root = rtrim($root, '/');

        return self::make(
            $layout,
            $scope,
            new LocalStorage($root.'/assets/upload', rtrim($appUrl, '/').'/assets/upload'),
            new LocalStorage($root.'/storage', null, 0750)
        );
    }

    public static function make(string $layout, StorageScope $scope, Storage $publicDriver, Storage $privateDriver): self
    {
        $public = new PathResolver($layout, $scope);
        $private = new PathResolver($layout, $scope);

        return new self($public->apply($publicDriver), $private->apply($privateDriver), $public, $private);
    }

    public function public(): Storage
    {
        return $this->public;
    }

    public function private(): Storage
    {
        return $this->private;
    }

    public function publicResolver(): PathResolver
    {
        return $this->publicResolver;
    }

    public function privateResolver(): PathResolver
    {
        return $this->privateResolver;
    }

    private static function value(array $env, string $key): string
    {
        $value = $env[$key] ?? '';

        return is_string($value) ? strtolower(trim($value)) : '';
    }
}
