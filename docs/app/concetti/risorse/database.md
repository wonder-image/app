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

### Testo formattato (`richText()`)

Un testo scritto con l'editor (descrizioni, note formattate) si dichiara con
`richText()`:

```php
Field::key('description')->text()->richText();
```

In scrittura l'HTML passa da `Wonder\Support\Html\SafeHtml::clean()`, una
whitelist:

- restano solo `p`, `br`, `strong`, `b`, `em`, `i`, `u`, `s`, `strike`, `del`
  e `a`, sempre **senza attributi**;
- sui link resta solo `href`, e solo se inizia con `http:`, `https:`, `mailto:`
  o `tel:` (il controllo ignora maiuscole, entità, spazi e caratteri di
  controllo). Un link relativo, `javascript:`, `data:`, `vbscript:` o `//host`
  perde il tag e tiene il testo;
- gli altri tag si tolgono tenendo il testo; `script`, `style`, `iframe`,
  `object`, `template`, `noscript`, `svg` e `math` spariscono con tutto il
  contenuto; i commenti si tolgono. `embed` è vuoto come in HTML5: il tag
  sparisce e il testo che lo segue resta;
- l'UTF-8 resta com'è (`perché`, `€`, emoji), `&nbsp;` resta `&nbsp;`;
- un editor vuoto (`<p><br></p>`, solo spazi o `&nbsp;`) diventa `''`.

La pulizia gira su ogni strada di scrittura: `Model::create()`/`update()` (il
`RichTextFormatter` aggiunto al campo) e il salvataggio dal backend
(`formToArray()`, che legge `format['rich_text']`). Il campo è
`sanitize(false)` in scrittura e in lettura: l'HTML si salva senza slash né
entità e si rilegge senza `sanitizeEcho()`. Un `sanitize(true)` messo dopo non
lo riaccende e `htmlToText()` si spegne.

In stampa l'HTML si stampa così com'è, senza `htmlspecialchars()`: è già
pulito. `SafeHtml::clean($html)` si può chiamare anche da solo, per esempio su
un HTML che arriva da un import.

### Campi immagine

`Field::key('...')->image()` accetta un file e genera le misure accanto
all'originale (`nome-480.png`, `nome-960.png`, ...). Le opzioni principali:

| Metodo | Cosa fa |
| --- | --- |
| `->extensions([...])` | Estensioni accettate in ingresso |
| `->responsive()` | Misure responsive del sito + WebP, in un colpo solo |
| `->resize([...])` | Misure su misura, al posto di quelle responsive |
| `->webp(bool)` | Genera (o no) la copia `.webp` accanto a ogni misura |
| `->deferResize()` | Scrive solo l'originale: le misure le genera qualcun altro |
| `->convertTo('png')` | Riscrive il file nel formato indicato, qualunque sia quello caricato |

`convertTo()` serve quando un campo deve accettare più estensioni ma
conservarne una sola. L'icona app è il caso tipico: arriva quasi sempre in
JPG, ma sul sito deve restare un PNG, perché i `<link rel="apple-touch-icon">`
nel `<head>` e le misure generate accanto all'originale si aspettano
un'estensione sola.

```php
Field::key('app_icon')->image()
    ->extensions(['png', 'jpg', 'jpeg'])
    ->webp(false)
    ->convertTo('png')
    ->resize(RuntimeDefaults::appIconSizes());
```

La conversione avviene dentro `uploadFiles()`, subito dopo lo spostamento del
file e prima del ridimensionamento: a database finisce il nome col formato
finale e le misure nascono già nell'estensione giusta. Formati scrivibili:
`png`, `jpg`, `webp`; convertire verso `jpg` appiattisce la trasparenza.
Se la conversione fallisce l'upload si ferma con l'errore `926` e il file non
viene registrato.

Lato Resource il campo va aperto anche in input, altrimenti il browser filtra
il JPG prima ancora di inviarlo:

```php
FormField::key('app_icon')->fileDragDrop('image'); // image/png, image/jpeg
```

> Le misure dichiarate con `resize()` vanno lette da un helper, non da
> `$GLOBALS['DEFAULT']`: le prepare schema si costruiscono durante il boot,
> quando i globals legacy non sono ancora pubblicati, e un fallback vuoto fa
> ricadere il campo sulle misure responsive del sito.

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

## Transazioni e lock

```php
use Wonder\Sql\Transaction;

$order = Transaction::run(function () use ($orderId) {
    $sequence = DocumentSequence::findForUpdate(['document_type' => 'order', 'year' => 2026, 'month' => 9], 1);
    // ... scarico del magazzino, righe, numero ...
    return Order::findById($orderId);
});
```

- `Transaction::run(callable, ?string $database = null)`: conferma a fine callback,
  annulla e rilancia su qualunque eccezione. Le transazioni annidate usano
  `SAVEPOINT wi_sp_<n>`: un errore interno gestito annulla solo il proprio livello.
- Legacy: `sqlTransaction(fn () => ..., 'main')`.
- Letture con lock: `Model::findForUpdate()`, `Model::findByIdForUpdate()`,
  `Query::SelectForUpdate()`, `sqlSelectForUpdate()`. Fuori da una transazione
  lanciano `RuntimeException`.
- `sql*()` e Model condividono la connessione di `Connection::Connect()`, quindi
  finiscono nella stessa transazione.

### Lock nominali per i cron

```php
use Wonder\App\Support\NamedLock;

$result = NamedLock::run('gestionale:invoices', fn () => sendInvoices());

if ($result === NamedLock::NOT_ACQUIRED) {
    return; // un'altra esecuzione è in corso
}
```

Basati su `GET_LOCK` / `RELEASE_LOCK`; il lock viene rilasciato anche in caso di
eccezione. I nomi oltre 64 caratteri diventano un hash stabile.

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

Esiste però un hook di **lettura** puro: `Model::decorate(array $row): array`.
Serve per arricchire ogni riga ritornata da `all()`, `find()`, `findById()`
con campi derivati, URL calcolati o payload già normalizzati.

> **Normalizzazione automatica in lettura.** Prima di `decorate()`, il Model
> applica `sanitizeEcho()` alle colonne che in scrittura passano da `sanitize()`
> (cioè tutte tranne quelle `->sanitize(false)`, `->richText()`, JSON e file). La lettura è così
> l'inverso simmetrico della scrittura: lo slash di escape aggiunto da
> `addslashes()` viene rimosso e le entità decodificate, senza doverlo fare a
> mano in stampa. Non chiamare di nuovo `sanitizeEcho()`/`normalizeDB()` su
> valori già letti via Model, per non normalizzare due volte.

Esempio con una schema extension:

```php
use Wonder\App\Schema\Extensions\AddressExtension;

public static function decorate(array $row): array
{
    return AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->decorate($row);
}
```

In questo modo l'extension legge già `legal_street`, `legal_city`, ecc. e
aggiunge automaticamente chiavi derivate come `legal_address`,
`legal_prettyAddress`, `legal_prettyPDF`.

Le stesse schema extension possono anche esporre configurazioni riusabili del
form, per esempio:

```php
AddressExtension::simple(countryDefault: 'IT')
    ->allowedCountries(['IT', 'DE'])
    ->requiredFields(['country', 'province', 'city', 'street', 'number']);
```

Così il Model e il form backend restano coerenti senza dover ripetere gli
stessi vincoli a mano in più classi.

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
