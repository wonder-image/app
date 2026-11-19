# Migrazioni versionate

Togliere o restringere qualcosa nel database si fa con una migrazione scritta a
mano; aggiungere resta automatico (`CreateTable`, dichiarazione nel Model).
Fuori da `local` l'aggiornamento dello schema non elimina più nulla da solo.

## Dove stanno

| Sorgente | Cartella |
|---|---|
| `app` | `database/migrations/` nel pacchetto |
| modulo | `database.migrations_dir` nel `module.json` |
| sito | `database/migrations/` del sito |

Ordine: `app`, moduli in ordine di dipendenza, sito; dentro una sorgente per
nome di file (`AAAAMMGGHHMMSS_descrizione.php`). L'identificativo è il nome del
file senza estensione e deve essere unico.

## Un file

```php
<?php

use Wonder\Sql\Migration;

return new class {
    public function destructive(): bool { return true; }

    public function up(Migration $db): void
    {
        $db->dropColumn('articoli', 'vecchia');
    }
};
```

Solo in avanti: niente `down()`. Si procede «prima aggiungi, poi rimuovi».
Una cosa per file, con operazioni idempotenti (il DDL di MariaDB non è
transazionale). `Migration` offre `addColumnIfMissing`, `addIndexIfMissing`,
`renameColumn`, `raw`, e `dropColumn`/`dropIndex` solo se `destructive()` è `true`.

## Comandi

- `forge migrate:make <nome> [--destructive]`: crea il file nel sito.
- `forge migrate [--pretend] [--force]`: applica quelle in attesa. Le
  distruttive, in `production` e `staging`, solo con `--force`.
- `forge migrate:status [--json]`: `applicata`, `in attesa`, `modificata`, `mancante`.
- `forge migrate --check`: per la CI; fallisce se un file è cambiato dopo
  l'applicazione o è sparito.

## Primo avvio e registro

La tabella `app_migrations` viene creata dal migratore. Al primo avvio le
migrazioni di `app` già presenti si registrano come applicate (`batch 0`) senza
eseguirle. Un solo migratore alla volta (`GET_LOCK('wonder_migrations')`):
chi non ottiene il lock esce con stato `busy`.

## Da codice

```php
$migrator = new \Wonder\App\Migrations\Migrator($mysqli);
$migrator->pending();                              // non scrive nulla
$migrator->runPending(['pretend' => true]);        // SQL a secco
$migrator->runPending(['with_schema' => true]);    // migrazioni, poi allineamento schema
```

`UpdateRunner::execute()` le applica dopo le `ModuleMigration` esistenti e prima
di `runTables()`.
