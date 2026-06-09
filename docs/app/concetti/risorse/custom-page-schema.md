---
icon: file-pen
---

# Pagine non-CRUD (CustomPageSchema)

## Cos'è

`CustomPageSchema` (`class/App/PageSchema/CustomPageSchema.php`) è la base per
pagine backend che **non** seguono lo schema CRUD di una Resource: login, cambio
password, profilo account, dashboard di upload, strumenti batch.

## A cosa serve

Permette di definire input e label di una pagina custom **rispettando la regola
dei form** (ogni input passa da `FormInput`/`FormField`), senza essere legati a
un singolo Model né alle 7 azioni CRUD.

## Quando usarla

| Caso | Usa |
|---|---|
| Pagina legata a un Model, dentro lo spazio URL della risorsa | **Resource** con `customBackendPages()` |
| Pagina trasversale (auth, impostazioni, batch) non legata a un Model | **CustomPageSchema** |

## Dove si trova nel codice

- Base: `class/App/PageSchema/CustomPageSchema.php`
- Esempi reali: `class/App/PageSchema/AccountPageSchema.php`,
  `class/App/PageSchema/CorporateDataPageSchema.php`

## Esempio completo (copiabile)

```php
<?php

namespace App\PageSchema;

use Wonder\App\PageSchema\CustomPageSchema;
use Wonder\App\ResourceSchema\FormInput;

final class ContactPageSchema extends CustomPageSchema
{
    public static function labelSchema(): array
    {
        return [
            'name'    => 'Nome',
            'email'   => 'Email',
            'message' => 'Messaggio',
        ];
    }

    public static function contactFormSchema(): array
    {
        return static::applyLabelSchema([
            'name'    => FormInput::key('name')->text()->required(),
            'email'   => FormInput::key('email')->email()->required(),
            'message' => FormInput::key('message')->textarea()->required(),
        ]);
    }
}
```

## Come si configura

- `labelSchema(): array` — etichette leggibili (opzionale ma consigliato).
- Uno o più metodi `*FormSchema()` che ritornano gli input. Il nome è una
  convenzione: una pagina può averne più d'uno (es. `loginFormSchema()`,
  `recoveryFormSchema()`).
- `applyLabelSchema(array $schema)` — metodo della base
  `CustomPageSchema`: applica la label da `labelSchema()` a ogni campo che non
  ne dichiara una propria.

## Collegamenti con il resto

Le **route** di una CustomPageSchema vanno registrate a mano nei file di route
del progetto (route backend + handler). Lo schema definisce **solo** input e
label: non registra route da solo. Gli input passano comunque per
`FormField::render($theme)` come ogni altro form (vedi [Form](../form/README.md)).

## Errori comuni

- **Dimenticare `use Wonder\App\PageSchema\CustomPageSchema;`** → la classe non
  estende nulla e i metodi base (`applyLabelSchema`) non esistono.
- **Aspettarsi che la pagina compaia da sola** → mancano le route: vanno
  dichiarate nel progetto.
- **Emettere `<input>` a mano** nella view → vietato: usa `FormInput`.

## Checklist

- [ ] estende `Wonder\App\PageSchema\CustomPageSchema`
- [ ] input dichiarati con `FormInput::key(...)`
- [ ] `labelSchema()` + `applyLabelSchema()` per le etichette
- [ ] route registrate manualmente nel progetto
