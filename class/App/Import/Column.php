<?php

namespace Wonder\App\Import;

/**
 * Una colonna che un tracciato sa leggere: la chiave interna, l'intestazione
 * che scrive l'export e gli altri nomi con cui la si riconosce in un file.
 */
final class Column
{
    /** @param list<string> $aliases */
    public function __construct(
        public readonly string $key,
        public readonly string $header,
        public readonly array $aliases = [],
    ) {
    }

    /** @param list<string> $aliases */
    public static function make(string $key, string $header, array $aliases = []): self
    {
        return new self($key, $header, $aliases);
    }

    /** La stessa colonna con un'altra intestazione (quella di un tracciato dell'amministratore). */
    public function withHeader(string $header): self
    {
        $header = trim($header);

        return $header === '' ? $this : new self($this->key, $header, array_values(array_unique([...$this->aliases, $this->header])));
    }
}
