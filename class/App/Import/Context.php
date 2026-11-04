<?php

namespace Wonder\App\Import;

/**
 * Quello che un tracciato sa del caricamento in corso: chi lo fa, quale
 * lavoro e una cache per non rileggere le stesse righe (marchi, categorie,
 * attributi) a ogni riga del file.
 */
final class Context
{
    /** @var array<string, mixed> */
    public array $cache = [];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        public readonly int $userId = 0,
        public readonly int $jobId = 0,
    ) {
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    /** Gli avvisi raccolti dall'ultima chiamata, svuotando l'elenco. */
    public function takeWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }

    public function remember(string $key, callable $load): mixed
    {
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $load();
        }

        return $this->cache[$key];
    }

    public function forget(string $key): void
    {
        unset($this->cache[$key]);
    }
}
