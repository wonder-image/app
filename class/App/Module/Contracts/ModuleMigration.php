<?php

namespace Wonder\App\Module\Contracts;

use mysqli;

/**
 * Migrazione strutturale di un modulo, dichiarata in `module.json` con
 * `database.migrations`. `forge update` la esegue a ogni passaggio, in ordine
 * di dipendenza dei moduli e prima di creare o aggiornare le tabelle: deve
 * quindi essere idempotente, non distruttiva e fermarsi sui conflitti.
 * Ritorna un riepilogo di ciò che ha fatto (vuoto se non c'era nulla da fare).
 */
interface ModuleMigration
{
    public static function run(mysqli $connection): array;
}
