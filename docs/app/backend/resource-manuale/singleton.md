# Resource Singleton

Usa una resource singleton quando il modulo gestisce un solo record fisso, per esempio:

- impostazioni sito
- configurazioni tema
- moduli CSS come `default`, `input`, `modal`

## Quando usarla

Una resource singleton e' giusta se:

- non vuoi una vera lista di record
- il record da modificare e' sempre uno solo
- vuoi entrare subito nella pagina modifica

## Classe base

Per i singleton generici usa:

```php
use Wonder\App\Resources\Support\SingletonResource;
```

Per i singleton CSS usa:

```php
use Wonder\App\Resources\Support\CssSingleton;
```

`CssSingleton` estende `SingletonResource` e aggiunge solo il comportamento specifico dei moduli CSS.

## Cosa fa automaticamente

`SingletonResource` imposta gia':

- `singletonRecordId()` = `1`
- backend solo `list`, `edit`, `update`
- API solo `show`, `update`
- niente `create`, `store`, `view`, `delete`
- nessun bottone `Aggiungi`
- nessun titolo lista
- nessuna freccia indietro nel form

In backend il path root del modulo reindirizza automaticamente alla modifica del record `1`.

Esempio:

```text
/backend/app/settings/
```

diventa:

```text
/backend/app/settings/1/edit/
```

## Esempio minimo

```php
<?php

namespace Wonder\App\Resources;

use Wonder\App\Models\SiteSettings;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\Resources\Support\SingletonResource;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

final class SiteSettingsResource extends SingletonResource
{
    public static string $model = SiteSettings::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'impostazione',
            'plural_label' => 'impostazioni',
            'last' => 'ultime',
            'all' => 'tutte',
            'article' => 'le',
            'full' => 'pieno',
            'empty' => 'vuoto',
            'this' => 'questa',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'title' => 'Titolo',
            'email' => 'Email',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('title')->text()->required(),
            FormInput::key('email')->email()->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('title'),
                static::getInput('email'),
            ])->columns(1)->columnSpan(12),
        ])->columns(12);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('id')->text()->link('edit'),
        ];
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('Configurazione', 'config', 'bi-gear')
            ->title('Impostazioni');
    }
}
```

## Se vuoi cambiare il record fisso

Di default il singleton usa il record `1`.

Se ti serve un altro id:

```php
public static function singletonRecordId(): int|string|null
{
    return 5;
}
```

## Se vuoi aggiungere logica dopo il salvataggio

Puoi usare i normali hook della resource:

```php
public static function afterUpdate(int|string $id, object $result, array $values = []): void
{
    // logica custom
}
```

## Regola pratica

Ricorda:

- `SingletonResource` = base generica
- `CssSingleton` = base per i moduli CSS
- `formSchema()` = input
- `formLayoutSchema()` = layout
- `tableSchema()` = quasi sempre solo `id -> link('edit')`

Continua con:

- [Quick Start](quick-start.md)
- [Componenti](componenti.md)
- [Route e API](route-e-api.md)
