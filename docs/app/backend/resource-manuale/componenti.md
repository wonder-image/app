# Componenti

Questa pagina mostra come usare i componenti principali dentro una resource.

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

Continua con:

- [Quick Start](quick-start.md)
- [Route e API](route-e-api.md)
