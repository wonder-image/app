<?php

namespace Wonder\App\Import;

use Wonder\App\Dataset\Column;
use Wonder\App\Dataset\Importable;

/**
 * Un tracciato di caricamento: le colonne che conosce, come controlla le righe
 * del file e come le scrive. Il motore (`Importer`) non sa niente delle
 * tabelle: le tocca solo il tracciato.
 */
abstract class Layout implements Importable
{
    abstract public static function key(): string;

    public function id(): string
    {
        return static::key();
    }

    abstract public function label(): string;

    /** Chi può caricare con questo tracciato. */
    abstract public function permits(object $user): bool;

    /** Un tracciato di caricamento serve solo all'import, e mai senza utente. */
    public function allows(?object $user, string $action): bool
    {
        return $action === 'import' && $user !== null && $this->permits($user);
    }

    /** @return list<Column> */
    abstract public function columns(): array;

    /**
     * Le colonne del file di esempio, nell'ordine in cui escono: di base
     * tutte quelle del tracciato.
     *
     * @return list<Column>
     */
    public function sampleColumns(): array
    {
        return $this->columns();
    }

    /**
     * Le righe del file di esempio, per chiave di colonna; le chiavi che
     * mancano escono vuote.
     *
     * @return list<array<string, string>>
     */
    public function sampleRows(): array
    {
        return [];
    }

    /** La pagina a cui torna il caricamento (la lista da cui si parte). */
    public function backUrl(): ?string
    {
        return null;
    }

    /**
     * Errori sull'abbinamento delle colonne, prima di leggere le righe.
     *
     * @param array<int, string|null> $mapping
     * @param list<string> $headers
     * @return list<string>
     */
    public function mappingErrors(array $mapping, array $headers): array
    {
        return [];
    }

    /**
     * Il controllo di tutte le righe, nell'ordine del file. Per ognuna
     * l'azione prevista (`create`, `update`, `unchanged`), gli errori (che
     * fermano il caricamento) e gli avvisi.
     *
     * @param list<array{line: int, data: array<string, string>}> $rows
     * @return list<array{action: string, errors: list<string>, warnings: list<string>}>
     */
    abstract public function validate(array $rows, Context $context): array;

    /**
     * Scrive una riga già controllata.
     *
     * @param array<string, string> $data
     * @return array{action: string, changes: list<Change>}
     */
    abstract public function apply(array $data, Context $context): array;

    /** Annulla una modifica fatta da `apply()`. */
    abstract public function undo(Change $change, Context $context): void;

    /**
     * Il messaggio da mostrare per un errore di `apply()` che non è un
     * `ImportError`; null = il messaggio generico (il resto va nel log).
     */
    public function message(\Throwable $exception): ?string
    {
        return null;
    }
}
