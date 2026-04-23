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
