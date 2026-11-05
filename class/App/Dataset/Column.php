<?php

namespace Wonder\App\Dataset;

use Closure;

/**
 * Una colonna di un tracciato, la stessa per l'import e per l'export: la
 * chiave interna, l'intestazione del file e gli altri nomi con cui la si
 * riconosce.
 *
 * Per l'export `value()` dice da dove viene il dato (un campo del record o
 * una funzione della riga) e `type()` come esce; senza tipo il valore passa
 * così com'è. Per l'import `required()` e `field()` guidano l'abbinamento.
 */
class Column
{
    /** @param list<string> $aliases */
    public function __construct(
        public readonly string $key,
        public readonly string $header,
        public readonly array $aliases = [],
        public readonly bool $required = false,
        public readonly ?string $field = null,
        public readonly string|Closure|null $value = null,
        public readonly ?Type $type = null,
        public readonly bool $sensitive = false,
    ) {
    }

    /** @param list<string> $aliases */
    public static function make(string $key, string $header, array $aliases = []): static
    {
        return new static($key, $header, $aliases);
    }

    public function required(bool $required = true): static
    {
        return $this->with(['required' => $required]);
    }

    /** Dove va il dato, scritto per chi carica il file («Prezzo di vendita del prodotto»). */
    public function field(?string $field): static
    {
        $field = $field === null ? null : trim($field);

        return $this->with(['field' => $field === '' ? null : $field]);
    }

    /** La stessa colonna con un'altra intestazione (quella di un tracciato dell'amministratore). */
    public function withHeader(string $header): static
    {
        $header = trim($header);

        if ($header === '' || $header === $this->header) {
            return $this;
        }

        return $this->with([
            'header' => $header,
            'aliases' => array_values(array_unique([...$this->aliases, $this->header])),
        ]);
    }

    /** Da dove viene il dato nell'export: il nome di un campo o `fn (array $row): mixed`. */
    public function value(string|callable|null $value): static
    {
        if (is_string($value)) {
            $value = trim($value) === '' ? null : trim($value);
        } elseif ($value !== null) {
            $value = Closure::fromCallable($value);
        }

        return $this->with(['value' => $value]);
    }

    public function type(Type|string|null $type): static
    {
        return $this->with(['type' => is_string($type) ? Type::from($type) : $type]);
    }

    /** Dato personale: un tracciato verso l'esterno lo esclude se non è scelto apposta. */
    public function sensitive(bool $sensitive = true): static
    {
        return $this->with(['sensitive' => $sensitive]);
    }

    /** Il valore grezzo della colonna in una riga. */
    public function read(array $row): mixed
    {
        if ($this->value instanceof Closure) {
            return ($this->value)($row);
        }

        return $row[$this->value ?? $this->key] ?? '';
    }

    /** Il valore come esce nel file: formattato dal tipo, se c'è. */
    public function export(array $row): mixed
    {
        $value = $this->read($row);

        return $this->type === null ? $value : $this->type->format($value);
    }

    /** Il valore di una cella letta da un file, convertito dal tipo se c'è. */
    public function parse(string $value): mixed
    {
        return $this->type === null ? trim($value) : $this->type->parse($value);
    }

    /** @param array<string, mixed> $changes */
    protected function with(array $changes): static
    {
        $values = [
            'key' => $this->key,
            'header' => $this->header,
            'aliases' => $this->aliases,
            'required' => $this->required,
            'field' => $this->field,
            'value' => $this->value,
            'type' => $this->type,
            'sensitive' => $this->sensitive,
            ...$changes,
        ];

        return new static(...$values);
    }
}
