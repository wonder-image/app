<?php

namespace Wonder\Backend\Table;

use Closure;
use InvalidArgumentException;

/**
 * Un segnale di riga: una condizione SQL che, se vera, accende il pallino
 * nella prima colonna della lista. `warning` è giallo (dato mancante),
 * `danger` rosso (dato da verificare).
 *
 * La condizione è SQL scritto dallo sviluppatore, mai input dell'utente:
 * `{table}` diventa la tabella della lista. Niente `AS` dentro la
 * condizione: il renderer legge ogni `AS` come alias di colonna.
 */
final class RowSignal
{
    public const WARNING = 'warning';

    public const DANGER = 'danger';

    private ?Closure $when = null;

    private function __construct(
        public readonly string $code,
        public readonly string $level,
        public readonly string $condition,
        public readonly string $message,
    ) {
        if (preg_match('/^[a-z0-9_]+$/', $code) !== 1) {
            throw new InvalidArgumentException("Codice del segnale non valido: «{$code}».");
        }

        if (trim($condition) === '') {
            throw new InvalidArgumentException("Il segnale «{$code}» non ha una condizione.");
        }
    }

    public static function warning(string $code, string $condition, string $message): self
    {
        return new self($code, self::WARNING, $condition, $message);
    }

    public static function danger(string $code, string $condition, string $message): self
    {
        return new self($code, self::DANGER, $condition, $message);
    }

    /** Il segnale vale solo quando la closure risponde true (una funzionalità accesa). */
    public function when(Closure $when): self
    {
        $copy = clone $this;
        $copy->when = $when;

        return $copy;
    }

    public function active(): bool
    {
        return $this->when === null || (bool) ($this->when)();
    }

    public function sql(string $table): string
    {
        return str_replace('{table}', $table, $this->condition);
    }
}
