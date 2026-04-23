# Quick Start

Questa e' la procedura minima per creare un modulo nuovo.

## 1. Crea il model

Da terminale:

```bash
php forge make:model Contact
```

File generato:

- `class/App/Models/Contact.php`

## 2. Crea la resource

Da terminale:

```bash
php forge make:resource Contact
```

File generato:

- `class/App/Resources/ContactResource.php`

## 3. Compila il model

Esempio:

```php
<?php

namespace Wonder\App\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Contact extends Model
{
    public static string $table = 'contact';
    public static string $folder = 'app/contact';
    public static string $icon = 'bi bi-person';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'name',
                'surname',
                'email',
            ]),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('name')->text()->required()->sanitizeFirst(),
            Field::key('surname')->text()->required()->sanitizeFirst(),
            Field::key('email')->email()->required(),
        ];
    }
}
```

## 4. Compila la resource

Esempio:

```php
<?php

namespace Wonder\App\Resources;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;
use Wonder\App\Models\Contact;

final class ContactResource extends Resource
{
    public static string $model = Contact::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'contatto',
            'plural_label' => 'contatti',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'i',
            'full' => 'pieno',
            'empty' => 'vuoto',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'name' => 'Nome',
            'surname' => 'Cognome',
            'email' => 'Email',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('name')->text()->required(),
            FormInput::key('surname')->text()->required(),
            FormInput::key('email')->email()->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('name')->columnSpan(1),
                static::getInput('surname')->columnSpan(1),
                static::getInput('email')->columnSpan(2),
            ])->columns(2)->columnSpan(2),
        ])->columns(2);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')->text()->link('edit'),
            TableColumn::key('surname')->text(),
            TableColumn::key('email')->text(),
            TableColumn::key('actions')->button()->actions(['edit', 'delete']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Lista '.static::pluralLabel())
            ->buttonAdd('Aggiungi '.static::label())
            ->filters();
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin'])
            ->apiCrud(['admin']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class);
    }
}
```

## 5. Apri il backend

Se il model ha:

```php
public static string $folder = 'app/contact';
```

la lista backend e':

```text
/backend/app/contact/
```

## 6. Usa subito le API

Lo slug interno diventa:

```text
app-contact
```

quindi la base API e':

```text
/api/resource/app-contact/
```

## 7. Regole rapide da ricordare

- `Model::tableSchema()` = struttura SQL della tabella
- `Model::dataSchema()` = come trattare i dati prima del salvataggio
- `Resource::formSchema()` = input del backend per campo
- `Resource::tableSchema()` = colonne per campo
- `Resource::formLayoutSchema()` = layout visuale del form
- `Resource::tableLayoutSchema()` = layout visuale della lista

Formula breve:

- `tableSchema()` = come e' fatta la tabella
- `dataSchema()` = come si preparano i dati
- `formSchema()` = come si inseriscono i dati nel backend

Nota:

- `tableSchema()` resta la fonte esplicita della struttura SQL
- se vuoi evitare duplicazione, puoi usare `static::sqlColumnsFromDataSchema([...])`

## 8. Upload in `dataSchema()`

Per i campi upload la logica va nel `Model::dataSchema()`.

Esempio:

```php
Field::key('profile_picture')->image()
    ->extensions(['png', 'jpg', 'jpeg'])
    ->maxSize(1)
    ->maxFile(1)
    ->dir('/profile-picture/')
    ->name('{slug}-avatar-{rand}')
    ->reset()
```

Metodi utili per upload:

- `file()`
- `image()`
- `extensions([...])`
- `maxSize(1)`
- `maxFile(1)`
- `dir('/cartella/')`
- `name('{slug}-file-{rand}')`
- `reset()`
- se ometti `resize()` su `image()`, usa `RESPONSIVE_IMAGE_SIZES`
- se ometti `webp()` su `image()`, usa `RESPONSIVE_IMAGE_WEBP`

## 9. Preset utili in `dataSchema()`

Esempi:

```php
Field::key('slug')->text()->slug()
Field::key('code')->text()->code()
Field::key('code')->text()->codeUpper()
Field::key('price')->number()
Field::key('meta')->json()
Field::key('published_at')->date()
```

Comportamento:

- `number()` usa di default `decimals(2)`
- `json()` produce `sanitize: false` e `json: true`
- `date()` produce `date: true`
- `slug()` produce `sanitize: false`, `link_unique: true`, `lower: true`
- `code()` produce `sanitize: false`, `unique: true`, `lower: true`
- `codeUpper()` produce `sanitize: false`, `link_unique: true`, `upper: true`
- `resize([...])`
- `webp()`

Se il campo e' un upload immagine e non imposti `resize()`, il runtime usa automaticamente `RESPONSIVE_IMAGE_SIZES`.

Se il campo e' un upload immagine e non imposti `webp()`, il runtime usa automaticamente `RESPONSIVE_IMAGE_WEBP`.

Continua con:

- [Componenti](componenti.md)
- [Resource e CustomPageSchema](custom-page-schema.md)
- [Resource Singleton](singleton.md)
- [Route e API](route-e-api.md)
