---
icon: '1'
---

# Resource Singleton

## Cos'è

Una **Resource singleton** gestisce esattamente **una riga** (es. la
configurazione del sito, i dati aziendali). Non ha senso una lista: c'è sempre e
solo un record da modificare.

## A cosa serve

Modellare risorse "uniche" mantenendo i vantaggi del sistema Resource (form,
validazione, permessi) ma senza pagina di lista e senza creazione/eliminazione.

## Dove si trova nel codice

- Pattern di riferimento: `class/App/Resources/Support/SingletonResource.php`
- Base: `class/App/Resource.php` (`singletonRecordId()`, `isSingleton()`)

## Come si configura

1. Imposta `singletonRecordId(): int|string|null` su un valore non vuoto:
   `isSingleton()` diventa automaticamente `true`.
2. Disattiva le pagine non pertinenti con `pageSchema()`.

```php
<?php

namespace App\Resources;

use Wonder\App\Resources\Support\SingletonResource;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\PageSchema;

final class SiteConfigResource extends SingletonResource
{
    public static string $model = \App\Models\SiteConfig::class;

    public static function path(): string { return 'site-config'; }

    public static function singletonRecordId(): int|string|null
    {
        return 1; // la riga unica
    }

    public static function pageSchema(): PageSchema
    {
        // niente lista, niente view, niente delete: solo edit/update
        return PageSchema::for(static::class)->disable(['list', 'view', 'delete']);
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('site_name')->text()->required(),
            FormInput::key('contact_email')->email(),
        ];
    }
}
```

## Perché disabilitare quelle pagine

Un singleton non ha una lista (`list`), non si crea/elimina come un record
normale (`create`/`delete`) e di solito si edita direttamente. Lasciare attive
quelle pagine produrrebbe route e link senza senso. Se il flusso di
creazione/modifica deve essere completamente custom, usa `customBackendPages()`
e registra le tue route.

## Collegamenti con il resto

- I default di `PageSchema` (quali pagine sono attive) sono descritti in
  [Route e API generate](route-e-api.md).
- Il form segue le stesse regole di [Form](../form/README.md).

## Errori comuni

- **Compare ancora la lista** → manca `->disable(['list', ...])` nel
  `pageSchema()`.
- **`singletonRecordId()` ritorna vuoto** → `isSingleton()` resta `false` e la
  Resource si comporta come CRUD normale.
- **Import errato** → usa
  `use Wonder\App\Resources\Support\SingletonResource;`.

## Checklist

- [ ] estende `SingletonResource` (o imposta `singletonRecordId()`)
- [ ] `singletonRecordId()` ritorna un id valido e stabile
- [ ] `pageSchema()->disable([...])` per le pagine non pertinenti
- [ ] esiste già la riga unica (seed/migrazione) o il flusso la crea
