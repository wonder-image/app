<?php

namespace Wonder\App\Import;

/**
 * Una colonna che un tracciato sa leggere: la chiave interna, l'intestazione
 * che scrive l'export e gli altri nomi con cui la si riconosce in un file.
 *
 * `required()` blocca il controllo delle righe finché nessuna intestazione è
 * abbinata alla colonna; `field()` dice a parole dove finisce il dato.
 */
final class Column
{
    /** @param list<string> $aliases */
    public function __construct(
        public readonly string $key,
        public readonly string $header,
        public readonly array $aliases = [],
        public readonly bool $required = false,
        public readonly ?string $field = null,
    ) {
    }

    /** @param list<string> $aliases */
    public static function make(string $key, string $header, array $aliases = []): self
    {
        return new self($key, $header, $aliases);
    }

    public function required(bool $required = true): self
    {
        return new self($this->key, $this->header, $this->aliases, $required, $this->field);
    }

    /** Dove va il dato, scritto per chi carica il file («Prezzo di vendita del prodotto»). */
    public function field(?string $field): self
    {
        $field = $field === null ? null : trim($field);

        return new self($this->key, $this->header, $this->aliases, $this->required, $field === '' ? null : $field);
    }

    /** La stessa colonna con un'altra intestazione (quella di un tracciato dell'amministratore). */
    public function withHeader(string $header): self
    {
        $header = trim($header);

        return $header === '' ? $this : new self($this->key, $header, array_values(array_unique([...$this->aliases, $this->header])), $this->required, $this->field);
    }
}
