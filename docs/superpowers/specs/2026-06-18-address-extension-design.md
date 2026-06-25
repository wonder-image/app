# Address Extension — frammenti first-class riusabili per Model e Resource

Data: 2026-06-18
Repo: `wonder-image/app` (framework)
Scope approvato: **design tecnico** di un'estensione riusabile che generi
schema dati, DDL, label e form per indirizzi semplici e indirizzi di
fatturazione, con supporto `prefix` e modalità `private|business`.

## Problema

L'architettura moderna del framework dichiara lo stesso concetto "indirizzo" in
più punti separati:

- `Model::dataSchema()` per i `Field`
- `Model::tableSchema()` per la DDL
- `Resource::labelSchema()` per le label
- `Resource::formSchema()` o `CustomPageSchema` per gli input

Oggi questo produce duplicazione concreta:

- [class/App/Models/Config/SocietyAddress.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Models/Config/SocietyAddress.php:21)
- [class/App/Models/Config/SocietyLegalAddress.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Models/Config/SocietyLegalAddress.php:21)
- [class/App/PageSchema/CorporateDataPageSchema.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/PageSchema/CorporateDataPageSchema.php:76)

Esiste anche un precedente legacy:

- [class/Plugin/Custom/Address/Address.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/Plugin/Custom/Address/Address.php:18)

Quella classe contiene buone intuizioni di dominio (`prefix`, `type`,
`private|business`, recapiti, rendering "pretty"), ma miscela anche persistenza
legacy, HTML procedurale e JS inline, quindi non e riusabile nel layer moderno.

## Obiettivi

1. Definire un blocco riusabile che generi schema indirizzo una sola volta.
2. Supportare `prefix` opzionale (`country` vs `legal_country`).
3. Supportare due famiglie:
   - **address semplice**
   - **billing address** con `type = private|business`
4. Riutilizzare i building block moderni gia esistenti:
   - `Field` (`UploadSchema`)
   - `FormField`
   - `sqlColumnsFromDataSchema()` dove coerente
   - `Tin`, `Vat`, `phonePrefix`, `country`, `states`
5. Non introdurre dipendenze verso HTML legacy, modali o funzioni procedural.
6. Mantenere il design estendibile per moduli esterni e consumer project.

## Vincoli letti dal codice

- `Model` non ha un registry di extension automatiche: espone solo metodi
  statici `tableSchema()` e `dataSchema()`
  ([class/App/Model.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Model.php:35)).
- `Resource` non ha hook automatici di composizione schema: espone
  `labelSchema()` e `formSchema()`
  ([class/App/Resource.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Resource.php:87)).
- `Resource::formSchema()` puo gia ricevere array annidati, che vengono appiattiti
  da `flattenFormItems()`
  ([class/App/Resource.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Resource.php:651)).
- Le label mancanti vengono gia risolte da `labelSchema()` via
  `normalizeFormField()`
  ([class/App/Resource.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Resource.php:667)).
- Il form system ha gia i campi geo e contatti:
  `country()`, `states()`, `phonePrefix()`, `url()`
  ([class/App/ResourceSchema/FormField.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/ResourceSchema/FormField.php:320)).
- Il renderer backend Bootstrap supporta `inputCountry`, `inputStates`,
  `inputPhonePrefix`
  ([class/App/Support/FormFieldElementFactory.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Support/FormFieldElementFactory.php:73)).
- Non esiste oggi un sistema first-class di visibilita condizionale dei campi
  nel DSL form (`private/business` -> show/hide gruppi). Questa parte va quindi
  trattata come step successivo.

## Decisione architetturale

**Non** introdurre un nuovo sistema globale di extension registration su `Model`
o `Resource`.

Introdurre invece una o piu classi valore/factory che producono **frammenti di
schema** da comporre nei metodi statici gia esistenti.

Questo e coerente con l'architettura attuale:

- il framework compone array di schema
- `Model` e `Resource` restano la sorgente finale di verita
- il blocco indirizzo diventa riusabile anche nei moduli esterni

## Architettura proposta

### 1. Namespace

Nuovo namespace consigliato:

`Wonder\App\Schema\Extensions`

File principale:

`class/App/Schema/Extensions/AddressExtension.php`

Motivazione:

- e uno strato di composizione di schema, non un helper di rendering
- non appartiene a `ResourceSchema` soltanto, perche serve anche ai `Model`
- non appartiene a `Support`, perche non e una utility generica stateless

### 2. Oggetto principale

Una classe immutabile-ish, configurata via named constructor / fluent API:

```php
AddressExtension::make()
AddressExtension::make(prefix: 'legal')
AddressExtension::make(prefix: 'billing')
```

Oppure, piu esplicita:

```php
AddressExtension::for('legal')
```

Configurazione supportata:

- `prefix: ?string`
- `mode: 'simple'|'billing'`
- `linkKey: string` default `'link'`
- `countryDefault: ?string` default `null`
- `withMore: bool` default `true`
- `withLink: bool` default `true`
- `withPhone: bool` default `false`
- `withContactName: bool` default `false`
- `withCompanyData: bool` derivata da `mode = billing`

API d'uso desiderata:

```php
$address = AddressExtension::make(
    prefix: 'legal',
    mode: 'simple',
    linkKey: 'gmaps',
    countryDefault: 'IT'
);
```

### 3. Metodi pubblici

La classe deve esporre almeno:

```php
labels(): array
dataSchema(): array
tableSchema(): array
formSchema(): array
```

Metodi opzionali ma utili:

```php
keys(): array
requiredKeys(): array
prettyKeys(): array
```

`keys()` e utile per consumer, export e table schema custom.

## Due profili di indirizzo

### A. `mode = simple`

Campi base:

- `country`
- `province`
- `city`
- `cap`
- `street`
- `number`
- `more` opzionale
- `<linkKey>` opzionale

Opzionali:

- `phone_prefix`
- `phone`
- `name`
- `surname`

Uso tipico:

- sede legale
- sede operativa
- indirizzo di contatto
- indirizzo di spedizione essenziale

### B. `mode = billing`

Aggiunge al blocco base:

- `type` (`private|business`)

Campi lato persona:

- `name`
- `surname`
- `cf`

Campi lato azienda:

- `business_name`
- `pi`
- `cf`
- `sdi`
- `pec`

Campi contatto opzionali:

- `phone_prefix`
- `phone`

Questo deriva direttamente dal dominio gia presente nel legacy
([Address.php:290](</Users/andreamarinoni/Desktop/PROGETTI/template/app/class/Plugin/Custom/Address/Address.php:290>)).

## Convenzione sui nomi

Regola:

- senza prefix: `country`, `province`, `city`
- con prefix `legal`: `legal_country`, `legal_province`, `legal_city`

Helper interno:

```php
private function key(string $name): string
```

che produce:

- `country` se `prefix === ''`
- `legal_country` se `prefix === 'legal'`

Stessa regola per ogni chiave, incluso `type`, `cf`, `pi`, `phone_prefix`.

## Design dei `Field` dati

### Base semplice

Proposta minima:

```php
Field::key('country')->text()
Field::key('province')->text()
Field::key('city')->text()
Field::key('cap')->text()
Field::key('street')->text()
Field::key('number')->text()
Field::key('more')->text()
Field::key('gmaps')->text()
```

Decisioni:

- `cap` come **text**, non `number`:
  - evita perdita di zeri iniziali
  - piu corretto fuori dall'Italia
- `link/gmaps` come `text`:
  - il renderer form puo essere `url()`
  - il dato persiste come stringa semplice

### Campi billing

```php
Field::key('type')->text()
Field::key('name')->text()
Field::key('surname')->text()
Field::key('business_name')->text()
Field::key('cf')->tin()->countryField('country')->type('all')
Field::key('pi')->vat()->countryField('country')
Field::key('sdi')->text()
Field::key('pec')->email()
Field::key('phone_prefix')->text()
Field::key('phone')->text()
```

Spunti utili dal codice esistente:

- `Tin` supporta gia `countryField()` e `type()`
  ([class/Data/Fields/Tin.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/Data/Fields/Tin.php:32))
- `Vat` supporta gia `countryField()`
  ([class/Data/Fields/Vat.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/Data/Fields/Vat.php:32))

### Requiredness

Prima iterazione:

- pochi `required()` hardcoded nel blocco base
- niente validazione condizionale first-class

Seconda iterazione possibile:

- validator condizionale riusabile nel layer `Data`
- es. `requiredWhen('type', 'business')`

Questo e **fuori scope** per la prima implementazione.

## Design della DDL

`tableSchema()` dell'extension deve derivare il piu possibile dai `Field`,
usando `Model::sqlColumnFromField()` invece di duplicare la definizione.

Dato che `AddressExtension` non estende `Model`, ci sono due strade:

### Opzione A — indipendente dal Model

L'extension costruisce direttamente `TableSchema`:

```php
Column::key($this->key('country'))
Column::key($this->key('province'))
...
```

Vantaggi:

- semplice
- nessuna dipendenza circolare

Svantaggi:

- lieve duplicazione con `dataSchema()`

### Opzione B — helper condiviso nel core

Introdurre un helper statico riusabile, es.:

```php
ModelSchema::sqlColumnsFromFields(array $fields, array|string|null $only = null): array
```

e far delegare `Model::sqlColumnsFromDataSchema()` a quel helper.

Vantaggi:

- elimina la duplicazione vera
- apre la strada ad altre extension future

Svantaggi:

- tocca il core architetturale

### Decisione consigliata

Per la **prima implementazione**, usare **Opzione A** per tenere bassa la
superficie del cambiamento.

Per i campi speciali:

- `cap` -> `VARCHAR`, non `INT`
- `type` -> `ENUM('private', 'business')` se il builder resta semplice, altrimenti `VARCHAR`
- `cf`, `pi`, `sdi`, `pec`, `phone_prefix`, `phone` -> `VARCHAR`

Scelta pragmatica:

- prima iterazione: `VARCHAR` per tutti i text-like field
- `ENUM` solo se non complica troppo export/update

## Design del form schema

### Address semplice

```php
[
    FormField::key('country')->country('province'),
    FormField::key('province')->states($country),
    FormField::key('city')->text(),
    FormField::key('cap')->text(),
    FormField::key('street')->text(),
    FormField::key('number')->text(),
    FormField::key('more')->text(),
    FormField::key('gmaps')->url(),
]
```

Questo e gia il pattern usato in
[CorporateDataPageSchema.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/PageSchema/CorporateDataPageSchema.php:90).

### Billing

Proposta:

```php
[
    FormField::key('type')->select([
        'private' => 'Privato',
        'business' => 'Societa',
    ])->value('private'),

    FormField::key('name')->text(),
    FormField::key('surname')->text(),
    FormField::key('business_name')->text(),
    FormField::key('cf')->text(),
    FormField::key('pi')->text(),
    FormField::key('sdi')->text(),
    FormField::key('pec')->email(),
    FormField::key('country')->country('province'),
    FormField::key('province')->states($country),
    FormField::key('city')->text(),
    FormField::key('cap')->text(),
    FormField::key('street')->text(),
    FormField::key('number')->text(),
    FormField::key('more')->text(),
    FormField::key('phone_prefix')->phonePrefix(),
    FormField::key('phone')->tel(),
    FormField::key('gmaps')->url(),
]
```

### Visibilita `private/business`

Il legacy mostra gruppi diversi a seconda di `type`
([Address.php:321](</Users/andreamarinoni/Desktop/PROGETTI/template/app/class/Plugin/Custom/Address/Address.php:321>)).

Nel layer moderno, oggi non c'e un sistema equivalente nel DSL. Quindi:

- **prima implementazione**: tutti i campi esistono nello schema; eventuali
  campi non pertinenti restano semplicemente vuoti
- **seconda implementazione**: introdurre un piccolo sistema di
  `context('visible_when', ...)` + renderer/js backend

Questa seconda parte e fuori scope nel primo rilascio.

## Label schema

La classe deve esporre `labels()` con naming coerente e localizzabile dal
consumer. Esempi:

```php
[
    'country' => 'Paese',
    'province' => 'Provincia',
    'city' => 'Citta',
    'cap' => 'Cap',
    'street' => 'Via',
    'number' => 'Civico',
    'more' => 'Altro',
    'gmaps' => 'Link gmaps',
]
```

Con prefix `legal`:

```php
[
    'legal_country' => 'Paese',
    ...
]
```

Per il billing:

```php
[
    'type' => 'Tipologia',
    'name' => 'Nome',
    'surname' => 'Cognome',
    'business_name' => 'Ragione sociale',
    'cf' => 'C.Fiscale',
    'pi' => 'P.Iva',
    'sdi' => 'SDI',
    'pec' => 'PEC',
]
```

## API dettagliata proposta

```php
namespace Wonder\App\Schema\Extensions;

final class AddressExtension
{
    public static function make(
        ?string $prefix = null,
        string $mode = 'simple',
        string $linkKey = 'link',
        ?string $countryDefault = null
    ): self;

    public function withMore(bool $enabled = true): self;
    public function withLink(bool $enabled = true): self;
    public function withPhone(bool $enabled = true): self;
    public function withContactName(bool $enabled = true): self;
    public function withType(bool $enabled = true): self;

    public function labels(): array;
    public function dataSchema(): array;
    public function tableSchema(): array;
    public function formSchema(?string $country = null): array;
    public function keys(): array;
}
```

Convenience named constructors opzionali:

```php
public static function simple(...): self;
public static function billing(...): self;
```

Uso ideale:

```php
$address = AddressExtension::simple(
    prefix: 'legal',
    linkKey: 'gmaps',
    countryDefault: 'IT',
);

$billing = AddressExtension::billing(
    prefix: 'billing',
    linkKey: 'gmaps',
    countryDefault: 'IT',
);
```

## Esempi di integrazione

### Model

```php
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

### Resource

```php
public static function labelSchema(): array
{
    return [
        ...AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->labels(),
    ];
}

public static function formSchema(): array
{
    return [
        ...AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->formSchema(),
    ];
}
```

### CustomPageSchema

```php
public static function legalAddressFormSchema(string $country = 'IT'): array
{
    return static::applyLabelSchema([
        ...AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->formSchema($country),
    ]);
}
```

## Rendering "pretty address"

La classe legacy incorpora anche formattazione display/PDF in `getById()`
([Address.php:77](</Users/andreamarinoni/Desktop/PROGETTI/template/app/class/Plugin/Custom/Address/Address.php:77>)).

Nel layer moderno questa parte **non** deve vivere in `AddressExtension`, ma in
un servizio separato o in helper gia esistenti:

- [class/Support/Prettify/Address.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/Support/Prettify/Address.php:5)
- [class/Support/Text/Address.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/Support/Text/Address.php:5)

Quindi:

- `AddressExtension` = schema
- `Prettify\Address` = presentazione
- eventuale parser testo -> `Text\Address`

Se in futuro serviranno campi derivati (`pretty_address`, `pretty_pdf`), vanno
modellati come pseudo/runtime fields, non come colonne dell'extension.

## Compatibilita e migrazione

### Prima adozione consigliata

1. `SocietyAddress`
2. `SocietyLegalAddress`
3. `CorporateDataPageSchema`

Questo riduce subito la duplicazione e valida:

- prefix
- `linkKey = gmaps`
- form semplice geo

### Seconda adozione

Consumer project o moduli che oggi usano la classe legacy `Address` per billing.

## File previsti per la futura implementazione

- `class/App/Schema/Extensions/AddressExtension.php` — nuova classe
- `class/App/Models/Config/SocietyAddress.php` — refactor
- `class/App/Models/Config/SocietyLegalAddress.php` — refactor
- `class/App/PageSchema/CorporateDataPageSchema.php` — refactor
- `docs/app/concetti/risorse/database.md` — documentare composizione schema
- `docs/app/concetti/form/form-field.md` o nuova pagina dedicata — documentare extension
- `AGENTS.md` — aggiornare convenzione se l'extension diventa pattern ufficiale
- skill/fork rilevante Wonder — aggiornare se il pattern viene promosso come convenzione architetturale

## Testing previsto

Non c'e PHPUnit nel repo. Validazione minima per l'implementazione:

1. `php -l` su ogni file toccato
2. `composer dumpautoload`
3. script/manual check che confronti:
   - `AddressExtension::simple(prefix: 'legal')->labels()`
   - schema attuale di `CorporateDataPageSchema`
4. `php forge update --local` in consumer project, per verificare che la DDL non
   diverga in modo inatteso

## Out of scope esplicito

- registrazione automatica di extension su tutti i `Model`/`Resource`
- renderer backend specifico per `googleAddress()`
- visibilita condizionale first-class `private|business`
- validator condizionali `requiredWhen(...)`
- refactor della classe legacy `Wonder\Plugin\Custom\Address\Address`
- persistenza di campi derivati tipo `prettyAddress` / `prettyPDF`

## Decisione finale

Il pattern corretto non e "una super-classe Address che fa tutto", ma:

1. una **schema extension** riusabile e composable
2. separazione netta tra:
   - schema dati
   - schema form
   - formattazione display
   - eventuale logica condizionale UI

Questa scelta e coerente con l'architettura moderna del framework e permette di
portare nel nuovo sistema i concetti buoni del legacy (`prefix`, `billing`,
`private|business`) senza trascinarsi il resto.
