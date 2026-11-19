<?php

namespace Wonder\App\Support;

use RuntimeException;

/**
 * Legge da un dump SQL (mysqldump o dump PHP di `DbBackup`) solo i NOMI delle
 * tabelle, riga per riga e senza caricare il file in memoria. Non esegue né
 * interpreta i dati: serve alla prova a secco per confrontare il contenuto del
 * dump con la classificazione.
 */
final class SyncDump
{
    /** @return list<string> nomi di tabella, in ordine alfabetico, senza doppioni */
    public static function tables(string $file): array
    {
        $handle = is_file($file) && is_readable($file) ? @fopen($file, 'rb') : false;

        if ($handle === false) {
            throw new RuntimeException('Dump non leggibile: '.$file);
        }

        $tables = [];

        while (($line = fgets($handle)) !== false) {
            if (!str_starts_with($line, 'CREATE TABLE') && !str_starts_with($line, 'INSERT INTO') && !str_starts_with($line, 'REPLACE INTO')) {
                continue;
            }

            if (preg_match('/^(?:CREATE TABLE(?: IF NOT EXISTS)?|INSERT INTO|REPLACE INTO)\s+`?([A-Za-z0-9_$]+)`?/', $line, $m) === 1) {
                $tables[$m[1]] = true;
            }
        }

        fclose($handle);

        $names = array_keys($tables);
        sort($names);

        return $names;
    }
}
