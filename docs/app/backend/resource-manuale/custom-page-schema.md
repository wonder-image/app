# Resource e CustomPageSchema

Questa pagina spiega quando usare una `Resource` e quando usare una `CustomPageSchema`.

## Regola semplice

Usa una `Resource` quando stai creando un modulo backend vero.

Usa una `CustomPageSchema` quando stai creando una pagina backend speciale che non e' un CRUD standard.

## Quando usare una `Resource`

Usa una `Resource` se il modulo ha:

- un `Model` principale
- route backend standard come `list`, `create`, `edit`, `show`
- eventualmente API CRUD
- `formSchema()`
- `tableSchema()`
- `formLayoutSchema()`
- `tableLayoutSchema()`
- navigazione backend

Esempi:

- `CssFontResource`
- `DocumentResource`
- `MailLogResource`

## Quando usare una `CustomPageSchema`

Usa una `CustomPageSchema` se la pagina:

- non e' un modulo CRUD standard
- non ha una `tableSchema()` vera
- non ha API CRUD
- e' una pagina speciale
- usa piu' tabelle insieme
- ha solo bisogno di input backend coerenti e label centralizzate

Esempi:

- `account/login`
- `account/password-recovery`
- `account/password-restore`
- `account/password-set`
- `account/index`
- `config/sql-download`
- `media/upload-massive`
- `config/corporate-data`

## Obiettivo di `CustomPageSchema`

`CustomPageSchema` serve a evitare:

- input scritti a mano nella view
- label duplicate
- logica UI sparsa

In pratica porta anche le pagine custom a questo livello:

- `labelSchema()`
- schema dei campi
- render dalla view tramite schema

## Struttura base

Esempio:

```php
<?php

namespace Wonder\App\PageSchema;

use Wonder\App\ResourceSchema\FormInput;

final class AccountPageSchema extends CustomPageSchema
{
    public static function labelSchema(): array
    {
        return [
            'username' => 'Username',
            'password' => 'Password',
        ];
    }

    public static function loginFormSchema(): array
    {
        return static::applyLabelSchema([
            'username' => FormInput::key('username')->text()->required(),
            'password' => FormInput::key('password')->password()->required(),
        ]);
    }
}
```

## Come usarla nell'handler

Esempio:

```php
\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/account/login.php', [
    'FORM_SCHEMA' => AccountPageSchema::loginFormSchema(),
])->render();
```

## Come usarla nella view

Esempio:

```php
$renderInput = static function (array $schema, string $key): string {
    foreach ($schema as $item) {
        if (!is_object($item) || !property_exists($item, 'name') || $item->name !== $key) {
            continue;
        }

        return (clone $item)->render();
    }

    return '';
};
```

Poi:

```php
<?=$renderInput((array) ($FORM_SCHEMA ?? []), 'username')?>
<?=$renderInput((array) ($FORM_SCHEMA ?? []), 'password')?>
```

## Relazione con `FormInput`

`CustomPageSchema` non sostituisce `FormInput`.

La relazione corretta e':

- `FormInput` = definizione del singolo campo
- `CustomPageSchema` = contenitore dichiarativo dei campi di una pagina custom
- `Resource` = modulo backend completo

## Regola finale

Ricorda:

- `Resource` per moduli
- `CustomPageSchema` per pagine custom
- `FormInput` per i singoli campi

Continua con:

- [Quick Start](quick-start.md)
- [Componenti](componenti.md)
- [Route e API](route-e-api.md)
