<?php

namespace Wonder\App\Dataset;

/**
 * Un insieme di dati che un tracciato descrive con le sue colonne: una
 * Resource da esportare, un tracciato di caricamento, una fonte di un modulo.
 */
interface Source
{
    /** La chiave con cui la si registra e con cui i tracciati salvati la indicano. */
    public function id(): string;

    public function label(): string;

    /** @return list<Column> */
    public function columns(): array;

    /** Se l'utente (null = CLI, scheduler) può fare l'azione: `export` o `import`. */
    public function allows(?object $user, string $action): bool;
}
