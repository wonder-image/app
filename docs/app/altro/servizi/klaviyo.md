# Klaviyo

{% hint style="info" %}
Link di accesso: [https://www.klaviyo.com/](https://www.klaviyo.com/)
{% endhint %}

## Configurazione rapida

1. Crea una API Key privata dal pannello Klaviyo.
2. Nel backend del sito apri la sezione credenziali.
3. Incolla la chiave nel campo `Klaviyo API Key`.

## Plugin disponibili

Il framework include i wrapper:

- `Wonder\Plugin\Klaviyo\Accounts`
- `Wonder\Plugin\Klaviyo\Campaigns`
- `Wonder\Plugin\Klaviyo\Catalogs`
- `Wonder\Plugin\Klaviyo\Coupons`
- `Wonder\Plugin\Klaviyo\CustomObjects`
- `Wonder\Plugin\Klaviyo\DataPrivacy`
- `Wonder\Plugin\Klaviyo\Events`
- `Wonder\Plugin\Klaviyo\Flows`
- `Wonder\Plugin\Klaviyo\Forms`
- `Wonder\Plugin\Klaviyo\Images`
- `Wonder\Plugin\Klaviyo\Lists`
- `Wonder\Plugin\Klaviyo\Metrics`
- `Wonder\Plugin\Klaviyo\Profiles`
- `Wonder\Plugin\Klaviyo\Reporting`
- `Wonder\Plugin\Klaviyo\Reviews`
- `Wonder\Plugin\Klaviyo\Segments`
- `Wonder\Plugin\Klaviyo\Tags`
- `Wonder\Plugin\Klaviyo\Templates`
- `Wonder\Plugin\Klaviyo\TrackingSettings`
- `Wonder\Plugin\Klaviyo\WebFeeds`
- `Wonder\Plugin\Klaviyo\Webhooks`

La chiave API viene letta automaticamente da `Credentials::api()->klaviyo_api_key`.

## Note d'uso

- I wrapper inoltrano tutte le funzioni esposte dal package `klaviyo/api`.
- Puoi passare i parametri direttamente come named arguments oppure costruirli con `addParams()`, `pushParams()` e `body()`.
- Per query comuni sono disponibili helper come `filter()`, `sort()`, `pageCursor()`, `pageSize()`, `fields()` e `additionalFields()`.

## Esempi di utilizzo

### 1) Lettura profili con filtro

```php
use Wonder\Plugin\Klaviyo\Profiles;

$response = (new Profiles())
    ->filter('equals(email,"utente@example.com")')
    ->pageSize(10)
    ->getProfiles();
```

### 2) Creazione profilo

```php
use Wonder\Plugin\Klaviyo\Profiles;

$response = (new Profiles())
    ->body([
        'data' => [
            'type' => 'profile',
            'attributes' => [
                'email' => 'utente@example.com',
                'first_name' => 'Mario',
                'last_name' => 'Rossi'
            ]
        ]
    ])
    ->createProfile();
```

### 3) Chiamata diretta con named arguments

```php
use Wonder\Plugin\Klaviyo\Lists;

$response = (new Lists())->getLists(
    fields_list: ['name'],
    page_size: 20
);
```

## Link utili

- API overview: [https://developers.klaviyo.com/en/reference/api_overview](https://developers.klaviyo.com/en/reference/api_overview)
- SDK PHP: [https://github.com/klaviyo/klaviyo-api-php](https://github.com/klaviyo/klaviyo-api-php)
