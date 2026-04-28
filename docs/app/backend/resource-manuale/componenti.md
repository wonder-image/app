# Componenti

Questa pagina mostra come usare i componenti principali dentro una resource.

## Distinzione base

Prima dei componenti, ricorda questa regola:

- `Model::tableSchema()` definisce la struttura SQL della tabella
- `Model::dataSchema()` definisce come trattare i dati prima del salvataggio
- `Resource::formSchema()` definisce gli input del backend

## `FormInput`

`FormInput` serve per configurare un singolo input del form.

Esempi:

```php
FormInput::key('name')->text()->required()
FormInput::key('email')->email()->required()
FormInput::key('phone')->tel()
FormInput::key('visible')->select([
    'true' => 'Visibile',
    'false' => 'Nascosto',
], 'old')->value('true')
```

Metodi utili:

- `text()`
- `email()`
- `number()`
- `tel()`
- `phone()`
- `url()`
- `textarea()`
- `select()`
- `selectSearch()`
- `checkbox()`
- `inputFile()`
- `inputFileDragDrop()`
- `repeater()`
- `required()`
- `label()`
- `value()`
- `options()`
- `columnSpan()`
- `error()`

## `UploadSchema` / `Field`

Nel `Model::dataSchema()` usi `Wonder\Data\UploadSchema as Field`.

Tipi disponibili:

- `text()`
- `number()`
- `date()`
- `email()`
- `json()`
- `password()`
- `file()`
- `image()`
- `tin()`
- `vat()`

Esempio semplice:

```php
Field::key('email')->email()->required()
Field::key('price')->number()->decimals(2)
Field::key('meta')->json()->sanitize(false)
```

Preset utili:

```php
Field::key('slug')->text()->slug()
Field::key('code')->text()->code()
Field::key('code')->text()->codeUpper()
Field::key('price')->number()
Field::key('meta')->json()
Field::key('published_at')->date()
```

Config runtime prodotti:

- `number()` -> `decimals: 2`
- `json()` -> `sanitize: false`, `json: true`
- `date()` -> `date: true`
- `text()->slug()` -> `sanitize: false`, `link_unique: true`, `lower: true`
- `text()->code()` -> `sanitize: false`, `unique: true`, `lower: true`
- `text()->codeUpper()` -> `sanitize: false`, `link_unique: true`, `upper: true`

Esempio upload:

```php
Field::key('main')->image()
    ->extensions(['png'])
    ->name('{slug}-logo-{rand}')
```

Se usi `image()`:

- `resize()` e' opzionale: se manca usa `RESPONSIVE_IMAGE_SIZES`
- `webp()` e' opzionale: se manca usa `RESPONSIVE_IMAGE_WEBP`
- `sanitize(false)`, `file()`, `maxFile(1)` e `maxSize(1)` sono gia' impostati dal field

Metodi utili lato `dataSchema()`:

- `required()`
- `unique()`
- `sanitize()`
- `sanitizeFirst()`
- `slug()`
- `lower()`
- `upper()`
- `ucwords()`
- `json()`
- `htmlToText()`
- `file()`
- `image()`
- `extensions([...])`
- `maxSize(1)`
- `maxFile(1)`
- `dir('/cartella/')`
- `name('{slug}-file-{rand}')`
- `reset()`
- `resize([...])`
- `webp()`

## `repeater()`

Per liste JSON semplici nel backend puoi usare un repeater nativo direttamente in `formSchema()`.

Esempio:

```php
FormInput::key('allowed_domains')
    ->repeater([
        [
            'name' => 'allowed_domains',
            'label' => 'Dominio',
            'helper' => 'text',
            'col' => 11,
        ],
    ])
    ->repeaterAddLabel('Aggiungi dominio')
    ->label('Domini')
```

Questo pattern e' pensato per:

- array JSON semplici
- liste di stringhe
- piccoli gruppi di righe ripetibili nel backend

Per casi piu' complessi puoi usare la modalita' nested:

```php
FormInput::key('variants')
    ->repeater([
        RepeaterColumn::key('variant_id')->hidden(),
        RepeaterColumn::key('name')->text()->label('Nome')->columnSpan(6),
        RepeaterColumn::key('image')->inputFileDragDrop('image')->label('Immagine')->columnSpan(5),
    ])
    ->nested()
    ->label('Varianti')
```

Nel controller puoi poi leggere le righe con:

```php
\Wonder\App\Support\Repeater::rowsFromRequest('variants', $_POST, $_FILES);
```

Questo supporta gia':

- piu' colonne per riga
- campi hidden
- upload per riga

Il bottone di aggiunta:

- e' sempre allineato a destra
- puo' essere customizzato con `->repeaterAddLabel('Aggiungi dominio')`
- puo' essere stilizzato con `->repeaterButtonClass('btn btn-dark')`
- puo' essere ordinabile con `->repeaterSortable()`
- la conferma eliminazione puo' essere customizzata con:
  - `->repeaterDeleteTitle('Elimina riga')`
  - `->repeaterDeleteText('Confermi l\\'eliminazione di questa riga?')`
  - `->repeaterDeleteCancelLabel('Annulla')`
  - `->repeaterDeleteConfirmLabel('Elimina')`
  - `->repeaterDeleteConfirmClass('btn btn-danger')`

Primo supporto relazionale 1:N:

```php
use Wonder\App\ResourceSchema\RepeaterRelation;

FormInput::key('variants')
    ->repeater([
        RepeaterColumn::key('id')->hidden(),
        RepeaterColumn::key('name')->text()->label('Nome')->columnSpan(6),
        RepeaterColumn::key('image')->inputFileDragDrop('image')->label('Immagine')->columnSpan(5),
    ])
    ->nested()
    ->repeaterSortable()
    ->relation(
        RepeaterRelation::make('products', 'variant_id')
            ->positionKey('position')
    )
    ->repeaterAddLabel('Aggiungi variante')
    ->label('Varianti');
```

Nel controller puoi poi sincronizzare le righe con:

```php
use Wonder\App\Support\Repeater;

$rows = Repeater::rowsFromRequest('variants', $_POST, $_FILES);

Repeater::syncRelatedRows(
    RepeaterRelation::make('products', 'variant_id')->positionKey('position'),
    $variantId,
    $rows
);
```

Se il repeater e' dichiarato dentro una `Resource` con `->relation(...)`, il CRUD backend/API standard lo sincronizza automaticamente dopo `store` e `update`.

In piu', durante il render del form:

- se la request corrente contiene il repeater, le righe vengono ricostruite automaticamente da `$_POST` / `$_FILES`
- se il repeater ha `->relation(...)` ed e' in `edit`, le righe figlie vengono caricate automaticamente dalla tabella relazionata
- nelle API standard `show` e `index`, se la resource usa `->relation(...)`, le righe figlie vengono aggiunte automaticamente al payload

Per far usare automaticamente al figlio il suo `dataSchema()` / `prepareSchema()` puoi dichiarare anche:

```php
RepeaterRelation::make('products', 'variant_id')
    ->model(\Wonder\App\Models\Ecommerce\Product::class)
```

oppure, se il figlio ha gia' una resource dedicata:

```php
RepeaterRelation::make('products', 'variant_id')
    ->resource(\Wonder\App\Resources\Ecommerce\ProductResource::class)
```

Questo serve soprattutto quando la riga figlia contiene:

- upload file o image
- normalizzazioni `slug/code/json/date`
- regole prepare custom del figlio

Primo uso reale nel progetto:

- `corporate-data` usa gia' il nuovo repeater per gli orari (`timetable`), senza passare da `SortableInput`
- `corporate-data` e' anche il primo caso reale predisposto per una relazione 1:N vera (`society_timetable`), con fallback al JSON legacy finche' la tabella non viene creata

Il passo successivo resta la parte relazionale automatica, cioe' il salvataggio 1:N guidato dallo schema.

Bridge SQL opzionale:

Se vuoi riusare i preset SQL dei field dentro `Model::tableSchema()`, puoi usare:

```php
public static function tableSchema(): array
{
    return [
        ...static::sqlColumnsFromDataSchema([
            'slug',
            'name',
            'email',
        ]),
    ];
}
```

Questo non sostituisce `tableSchema()`: ti aiuta solo a comporla con meno duplicazione.

## `TableColumn`

`TableColumn` serve per configurare una singola colonna della lista backend.

Esempi:

```php
TableColumn::key('name')->text()->link('edit')
TableColumn::key('email')->text()
TableColumn::key('visible')->badge()
TableColumn::key('actions')->button()->actions(['edit', 'delete'])
```

Metodi utili:

- `text()`
- `date()`
- `phone()`
- `price()`
- `badge()`
- `icon()`
- `image()`
- `button()`
- `link('edit')`
- `action('edit')`
- `actions(['edit', 'delete'])`
- `function(...)`
- `value(...)`
- `size(...)`

## `Form`

`Form` e' il contenitore principale del layout backend del form.

Esempio:

```php
(new Form)->components([
    // componenti
])->columns(3)
```

Metodi utili:

- `components([...])`
- `columns(3)`

## `Card`

`Card` serve per creare blocchi visivi dentro il `Form`.

Esempio:

```php
(new Card)->components([
    static::getInput('name'),
    static::getInput('surname'),
])->columns(2)->columnSpan(2)
```

Metodi utili:

- `components([...])`
- `columns(2)`
- `columnSpan(2)`

## Esempio completo di `formLayoutSchema()`

```php
public static function formLayoutSchema(): ?Form
{
    return (new Form)->components([

        (new Card)->components([
            static::getInput('name')->columnSpan(1),
            static::getInput('surname')->columnSpan(1),
        ])->columns(2)->columnSpan(2),

        (new Card)->components([
            static::getInput('phone'),
        ])->columns(1)->columnSpan(1),

    ])->columns(3);
}
```

## Esempio completo di `tableSchema()`

```php
public static function tableSchema(): array
{
    return [
        TableColumn::key('name')->text()->link('edit'),
        TableColumn::key('surname')->text(),
        TableColumn::key('phone')->text(),
        TableColumn::key('actions')->button()->actions(['edit', 'delete']),
    ];
}
```

## Regola pratica

Ricorda questa distinzione:

- `formSchema()` = definizione input
- `formLayoutSchema()` = composizione visuale
- `tableSchema()` = definizione colonne
- `tableLayoutSchema()` = composizione visuale lista
- `CustomPageSchema` = schema input per una pagina backend speciale non CRUD

Continua con:

- [Quick Start](quick-start.md)
- [Resource e CustomPageSchema](custom-page-schema.md)
- [Resource Singleton](singleton.md)
- [Route e API](route-e-api.md)
