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
            Column::key('name'),
            Column::key('surname'),
            Column::key('email'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('name')->text()->required()->sanitizeFirst(),
            Field::key('surname')->text()->required()->sanitizeFirst(),
            Field::key('email')->text()->required(),
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

- `Model::tableSchema()` = colonne SQL
- `Model::dataSchema()` = come trattare i dati
- `Resource::formSchema()` = input per campo
- `Resource::tableSchema()` = colonne per campo
- `Resource::formLayoutSchema()` = layout visuale del form
- `Resource::tableLayoutSchema()` = layout visuale della lista

Continua con:

- [Componenti](componenti.md)
- [Resource Singleton](singleton.md)
- [Route e API](route-e-api.md)
