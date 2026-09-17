<?php

namespace Wonder\App\Module\Contracts;

use Wonder\App\Support\DefaultRows;

/**
 * Righe precaricate di un modulo, dichiarate in `module.json` con
 * `database.defaults`. Eseguite da `forge update` solo con `APP_ENV=local`,
 * in ordine di dipendenza. Non devono mai modificare righe esistenti:
 * usare sempre `DefaultRows`.
 */
interface ModuleDefaults
{
    public static function seed(DefaultRows $rows): void;
}
