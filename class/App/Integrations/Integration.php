<?php

namespace Wonder\App\Integrations;

/**
 * Un servizio esterno della pagina Credenziali: i campi di `security` che usa,
 * la categoria in cui compare e come se ne legge lo stato. Lo stato si calcola
 * solo dai valori salvati (o dal `.env`), senza chiamare il servizio.
 */
abstract class Integration
{
    abstract public function key(): string;

    abstract public function name(): string;

    /** Una delle chiavi di `Integrations::CATEGORIES`, o un'altra per «Altro». */
    abstract public function category(): string;

    /**
     * I campi del form nel modal, colonna => larghezza su 12.
     *
     * @return array<string, int>
     */
    abstract public function fields(): array;

    public function description(): string
    {
        return '';
    }

    /** Classe Bootstrap Icons. */
    public function icon(): string
    {
        return 'bi-plug';
    }

    public function variant(): string
    {
        return 'primary';
    }

    /**
     * Campi di modalità (ambiente, servizio d'invio): stanno nel modal ma non
     * contano per lo stato.
     *
     * @return list<string>
     */
    public function modes(): array
    {
        return [];
    }

    /**
     * I campi che servono per dirla connessa. Di norma tutti i campi tranne
     * le modalità.
     *
     * @param array<string, mixed> $values
     * @return list<string>
     */
    public function required(array $values): array
    {
        return array_values(array_diff(array_keys($this->fields()), $this->modes()));
    }

    /** Nessun campo modificabile dal backend. */
    public function readonly(): bool
    {
        return false;
    }

    /** Chiave dell'integrazione da cui dipende, o null. */
    public function requires(): ?string
    {
        return null;
    }

    public function docsUrl(): ?string
    {
        return null;
    }

    /** Testo di aiuto in cima al modal. */
    public function guide(): string
    {
        return '';
    }

    /** @param array<string, mixed> $values */
    public function testMode(array $values): bool
    {
        return false;
    }

    /**
     * Riga breve sotto la descrizione della card, per esempio «In uso».
     *
     * @param array<string, mixed> $values
     */
    public function note(array $values): ?string
    {
        return null;
    }

    public function order(): int
    {
        return 100;
    }

    /**
     * Contenuti propri dell'integrazione nel modal, dopo i campi: Element o
     * HTML fidato, come nei `components()` di un layout.
     *
     * @param array<string, mixed> $values I valori in uso, `.env` compreso
     * @return list<mixed>
     */
    public function panel(array $values, IntegrationStatus $status): array
    {
        return [];
    }

    /** @param array<string, mixed> $values */
    public function status(array $values): IntegrationStatus
    {
        $missing = array_values(array_filter(
            $this->required($values),
            static fn (string $column): bool => !self::filled($values[$column] ?? null)
        ));

        if ($missing === []) {
            $state = IntegrationStatus::CONNECTED;
        } else {
            $state = IntegrationStatus::DISCONNECTED;

            foreach (array_diff(array_keys($this->fields()), $this->modes()) as $column) {
                if (self::filled($values[$column] ?? null)) {
                    $state = IntegrationStatus::PARTIAL;
                    break;
                }
            }
        }

        return new IntegrationStatus($state, $missing, $this->testMode($values), $this->note($values));
    }

    public static function filled(mixed $value): bool
    {
        return trim((string) ($value ?? '')) !== '';
    }
}
