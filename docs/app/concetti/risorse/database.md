---
icon: database
---

# Model e Database

## Cos'è

Un **Model** descrive una tabella e la sua logica dati. Estende
`Wonder\App\Model` (`class/App/Model.php`) e ha due metodi obbligatori:

- **`dataSchema()`** — i **dati**: validazione, formattazione, persistenza.
- **`tableSchema()`** — la **struttura SQL** (DDL).

{% hint style="warning" %}
`dataSchema()` e `tableSchema()` **non sono la stessa cosa**. `dataSchema()`
descrive i dati (e come vanno validati/salvati); `tableSchema()` descrive le
colonne SQL. Per tenerli allineati, fai chiamare a `tableSchema()` il metodo
`sqlColumnsFromDataSchema()`.
{% endhint %}

## A cosa serve

Da un Model il framework deriva:

1. la **DDL** (creazione/aggiornamento tabella via `php forge update`)
2. la **validazione** dei valori in ingresso
3. la **formattazione** alla persistenza (sanitize, slug, upload file, JSON)
4. i **metodi di query** statici (`all`, `find`, `findById`, `create`, …)

## Dove si trova nel codice

- Base: `class/App/Model.php`
- Builder dati: `Wonder\Data\UploadSchema` (alias comodo `as Field`)
- Builder DDL: `Wonder\Sql\TableSchema` (alias comodo `as Column`)
- Extension riusabili: `class/App/Schema/Extensions/*`
- Esempi reali: `class/App/Models/*`

## Esempio completo (copiabile)

```php
<?php

namespace App\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Project extends Model
{
    public static string $table  = 'projects';
    public static string $folder = 'projects';      // sottocartella upload per i file
    public static string $icon   = 'bi bi-folder';  // icona default per la navigazione

    public static function tableSchema(): array
    {
        return [
            Column::key('position')->int(),                 // colonna non in dataSchema
            ...static::sqlColumnsFromDataSchema([           // derivata dai Field
                'slug', 'name', 'description', 'cover', 'visible',
            ]),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('slug')->text()->slug(),
            Field::key('name')->text()->sanitizeFirst()->required(),
            Field::key('description')->text(),
            Field::key('cover')->upload()->image(),   // file/immagine: path da $folder
            Field::key('visible')->text(),
        ];
    }
}
```

Dopo aver creato il file: `composer dump-autoload`, poi `php forge update` (o
`php forge update --local` in locale) per applicare la tabella.

## Come si configura

### Proprietà pubbliche del Model

| Proprietà | Default | Significato |
|---|---|---|
| `$table` | — | nome tabella SQL (in pratica obbligatorio) |
| `$folder` | — | sottocartella upload per i campi file/immagine |
| `$icon` | — | icona Bootstrap di default per la navigazione |
| `$defaultCondition` | `['deleted' => 'false']` | filtro WHERE di default (soft-delete). `null`/`[]` lo disattiva |
| `$dbHostname`, `$dbUsername`, `$dbPassword`, `$dbName` | da `Credentials` | override connessione per-Model |

### `tableSchema()` — la DDL

Ritorna un array di colonne `Wonder\Sql\TableSchema`. Due strade, combinabili:

- **derivare** dai `Field`: `...static::sqlColumnsFromDataSchema(['slug', 'name'])`
- **dichiarare a mano** colonne non presenti in `dataSchema()`:
  `Column::key('position')->int()`

Override opzionali: `tableOptions()` (es. `audit_columns`), `tablePseudos()`.

### `dataSchema()` — i dati

Ritorna un array di `Field` (`Wonder\Data\UploadSchema as Field`), ciascuno
con `Field::key('nome')` e i suoi vincoli:

```php
Field::key('email')->text()->email()->required();
Field::key('slug')->text()->slug();
Field::key('cover')->upload()->image();
Field::key('password')->password()->minLength(8);
```

Il Model usa il data schema per tre scopi: **SQL** (`sqlColumnsFromDataSchema`),
**validazione** (`validate()`), **persistenza** (`prepare()`).

### Schema extension riusabili

Quando un blocco di campi deve restare coerente tra piu Model/Resource/Page,
non duplicare lo schema a mano: estrailo in una **schema extension** sotto
`class/App/Schema/Extensions/*`.

Esempio:

```php
use Wonder\App\Schema\Extensions\AddressExtension;

public static function dataSchema(): array
{
    return [
        ...AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->dataSchema(),
    ];
}

public static function tableSchema(): array
{
    return [
        ...AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->tableSchema(),
    ];
}
```

Dettagli e convenzioni: [Schema extension](schema-extensions.md).

## Query API (statica)

Tutti i metodi di lettura applicano già `$defaultCondition` (escludono le righe
soft-deleted):

| Metodo | Cosa fa |
|---|---|
| `query()` | builder SQL di basso livello |
| `all($columns = '*')` | tutte le righe non cancellate |
| `find($condition?, $limit?, $order?, $direction?, $columns?)` | fetch filtrato |
| `findById($id)` | singola riga per id |
| `getAll`, `get`, `getById` | alias dei precedenti |
| `safeAll`, `safeFind`, `safeFindById` | come sopra, normalizzati per output API (bool, JSON, URL file) |
| `create($values)` | valida, prepara, inserisce |
| `update($values, $id)` | valida, prepara, aggiorna |
| `createUpdate($values, $id = null)` | crea se `$id` vuoto, altrimenti aggiorna |
| `delete($id)` | hard delete per id |

{% hint style="info" %}
Usa sempre le varianti `safe*` quando il risultato va verso un client (API o
qualsiasi cosa serializzata in JSON).
{% endhint %}

## Soft-delete

Non esiste un metodo `softDelete()`. Il soft-delete è una `update()` che imposta
`deleted = 'true'`. Le letture lo escludono automaticamente grazie a
`$defaultCondition = ['deleted' => 'false']`. Per **vedere** anche le righe
cancellate, passa una condition esplicita che includa `deleted`.

## Hook del ciclo di vita

Sul **Model non ci sono** `beforeSave`/`afterSave`. Il pre/post processing vive
sulla **Resource** (`mutateRequestValues`, `afterStore`, `afterUpdate`,
`afterDelete`), così lo stesso Model può essere usato da Resource diverse con
comportamenti diversi. Vedi [Definire una Resource](resource.md).

## Migrazioni e aggiornamento tabelle

- In locale: `php forge update --local`
- In CI/server: `php forge update`
- Via HTTP (storico): visitare `dominio.it/update/`

`update` applica le tabelle definite in `tableSchema()` ed esegue i file in
`app/build/row` e `app/build/update`. Dettagli in
[Installazione e Deploy](../../piattaforma/installazione-e-deploy.md).

Convenzione architetturale:

- `app/build/row` contiene seed/bootstrap idempotenti che scrivono o
  riallineano righe applicative.
- I payload di default per questi seed vivono in `Wonder\App\SeedDefaults`.
- `Wonder\App\RuntimeDefaults` resta riservato ai fallback runtime
  (rendering, config in memoria, asset/style defaults letti a runtime).

## Multi-database

`DB_DATABASE` accetta più database con chiavi:

```dotenv
DB_DATABASE=main: nome_db_principale, log: nome_db_log
```

La `key` (`main`, `log`, …) identifica poi il database associato a una tabella.
I nomi env hanno due varianti storiche (`DB_HOSTNAME`/`DB_HOST`,
`DB_USERNAME`/`DB_USER`, `DB_DATABASE`/`DB_NAME`): `Wonder\App\EnvCompat` le
allinea automaticamente, quindi puoi usare indifferentemente uno dei due set.

## Errori comuni

- **Tabella non aggiornata** → hai modificato `tableSchema()` ma non lanciato
  `php forge update`.
- **Colonna mancante** → l'hai messa in `dataSchema()` ma non in `tableSchema()`
  (o viceversa). Usa `sqlColumnsFromDataSchema()` per tenerli in sync.
- **Le righe cancellate non spariscono / non si vedono** → ricorda
  `$defaultCondition`: escludi/includi `deleted` esplicitamente.
- **Il Model non viene scoperto** → manca `composer dump-autoload` o
  namespace/cartella non coincidono col PSR-4.
- **File caricato ma URL sbagliato** → manca `static::$folder` sul Model, oppure
  non usi le letture `safe*` che espandono i filename in URL.

## Checklist

- [ ] `$table` impostato
- [ ] `dataSchema()` e `tableSchema()` coerenti (usa `sqlColumnsFromDataSchema`)
- [ ] `$folder` impostato se ci sono campi file/immagine
- [ ] `composer dump-autoload` eseguito
- [ ] `php forge update --local` eseguito e tabella presente
- [ ] letture verso client usano `safe*`
