<?php

namespace Wonder\App\Import;

/**
 * Un tracciato di caricamento: le colonne che conosce, come controlla le righe
 * del file e come le scrive. Il motore (`Importer`) non sa niente delle
 * tabelle: le tocca solo il tracciato.
 */
abstract class Layout
{
    abstract public static function key(): string;

    abstract public function label(): string;

    /** Chi può caricare con questo tracciato. */
    abstract public function permits(object $user): bool;

    /** @return list<Column> */
    abstract public function columns(): array;

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
