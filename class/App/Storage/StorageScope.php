<?php

namespace Wonder\App\Storage;

use Wonder\App\Environment;

/**
 * Di chi sono i file e in quale ambiente: il tenant (per un sito singolo, il
 * sito stesso; con il multi-tenant, il tenant della richiesta) e l'ambiente
 * (`local`, `staging`, `production`, gli stessi valori di `APP_ENV`).
 */
final class StorageScope
{
    public const DEFAULT_TENANT = 'default';

    public function __construct(public readonly string $tenant, public readonly string $environment)
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,62}$/', $tenant) !== 1 || str_contains($tenant, '..')) {
            throw new StorageException('Tenant non valido: minuscole, cifre, punto, trattino e trattino basso.');
        }

        if (!in_array($environment, Environment::ALLOWED, true)) {
            throw new StorageException('Ambiente non valido: '.implode(', ', Environment::ALLOWED).'.');
        }
    }

    public function inEnvironment(string $environment): self
    {
        return new self($this->tenant, $environment);
    }

    /** `{tenant}/{ambiente}`. */
    public function prefix(): string
    {
        return $this->tenant.'/'.$this->environment;
    }
}
