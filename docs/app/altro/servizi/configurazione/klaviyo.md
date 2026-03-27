# Klaviyo

{% hint style="info" %}
Link di accesso: [https://www.klaviyo.com/](https://www.klaviyo.com/)
{% endhint %}

## Configurazione rapida

1. Accedi a Klaviyo e apri `Settings` -> `API keys`.
2. Crea una `Private API Key`.
3. Abilita gli scope necessari per le risorse che userai.
4. Nel backend del sito apri la sezione credenziali.
5. Incolla la chiave nel campo `Klaviyo API Key`.

{% hint style="warning" %}
Per i plugin `Wonder\Plugin\Klaviyo\*` serve una `Private API Key`.

La `Public API Key` o `Site ID` non va usata con queste classi.
{% endhint %}

Scope minimi consigliati:

- `profiles:read` e `profiles:write` per leggere e modificare profili
- `lists:read` e `lists:write` per lavorare con le liste
- `subscriptions:write` per iscrivere un profilo a email o SMS marketing
- scope aggiuntivi per `events`, `metrics`, `campaigns`, `forms`, `webhooks` se usi anche quelle API

Di default la chiave API viene letta automaticamente da `Credentials::api()->klaviyo_api_key`.

## Classi disponibili

Il framework include queste classi:

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

## Pattern di utilizzo

Tutte le classi Klaviyo usano lo stesso approccio:

- crei l'istanza
- imposti i parametri con metodi fluent
- esegui la chiamata finale

Esempio base:

```php
use Wonder\Plugin\Klaviyo\Profiles;

$profiles = (new Profiles())
    ->pageSize(10)
    ->all();
```

Se vuoi forzare una chiave API specifica:

```php
use Wonder\Plugin\Klaviyo\Profiles;

$profiles = Profiles::connect('pk_...');
```

In alternativa puoi usare `Profiles::apiKey('pk_...')`.

I metodi query piu usati sono:

- `filter()`
- `sort()`
- `includes()`
- `pageCursor()`
- `pageSize()`
- `fields()`
- `additionalFields()`

Esempio:

```php
use Wonder\Plugin\Klaviyo\Profiles;

$profiles = (new Profiles())
    ->filter('equals(email,"utente@example.com")')
    ->fields('profile', ['email', 'first_name', 'last_name'])
    ->pageSize(10)
    ->all();
```

## Profiles

La classe `Profiles` e quella che userai piu spesso per:

- creare profili
- aggiornare profili
- iscrivere contatti a liste
- gestire il consenso marketing email e SMS
- leggere le sottoscrizioni

### Creazione o aggiornamento profilo

Se devi solo salvare i dati anagrafici o proprieta custom del profilo:

```php
use Wonder\Plugin\Klaviyo\Profiles;

$response = (new Profiles())
    ->email('utente@example.com')
    ->phone('+39 333 1234567')
    ->firstName('Mario')
    ->lastName('Rossi')
    ->property('source', 'checkout')
    ->createOrUpdate();
```

Metodi piu usati per il profilo:

- `email()`
- `phone()` o `phoneNumber()`
- `firstName()`
- `lastName()`
- `externalId()`
- `organization()`
- `locale()`
- `title()`
- `image()`
- `property()`
- `properties()`
- `location()`

### Iscrizione a lista con email marketing

Se devi creare il profilo se non esiste, iscriverlo alla lista e dare il consenso email marketing:

```php
use Wonder\Plugin\Klaviyo\Profiles;

$response = (new Profiles())
    ->email('utente@example.com')
    ->listId('LIST_ID')
    ->emailMarketing()
    ->subscribe();
```

### Iscrizione a lista con email marketing e SMS marketing

```php
use Wonder\Plugin\Klaviyo\Profiles;

$response = (new Profiles())
    ->email('utente@example.com')
    ->phone('+39 333 1234567')
    ->listId('LIST_ID')
    ->emailMarketing()
    ->smsMarketing()
    ->subscribe();
```

Nota pratica:

- `phone()` normalizza il numero, ma per l'SMS Klaviyo richiede comunque un numero finale in formato `E.164`
- un valore corretto e ad esempio `+393331234567`

### Iscrizione con solo SMS transazionale

Usa questo flusso solo se ti serve consenso `transactional-only`.

```php
use Wonder\Plugin\Klaviyo\Profiles;

$response = (new Profiles())
    ->phone('+39 333 1234567')
    ->listId('LIST_ID')
    ->smsTransactional()
    ->subscribe();
```

Nota pratica:

- se un profilo ha gia `smsMarketing()`, puo ricevere anche SMS transazionali
- `smsTransactional()` serve solo quando vuoi registrare consenso SMS transazionale senza marketing
- gli SMS transazionali in Klaviyo richiedono comunque configurazione account corretta e, in pratica, vengono usati in flow post-purchase o casi approvati come transactional

### Flusso corretto quando hai anche proprieta custom

{% hint style="warning" %}
Klaviyo non permette di iscrivere un profilo e aggiornare le custom properties nello stesso request body di `subscribe`.

Se devi fare entrambe le cose, usa due chiamate:
1. `createOrUpdate()`
2. `subscribe()`
{% endhint %}

Esempio:

```php
use Wonder\Plugin\Klaviyo\Profiles;

$profile = (new Profiles())
    ->email('utente@example.com')
    ->phone('+39 333 1234567')
    ->firstName('Mario')
    ->lastName('Rossi')
    ->property('source', 'form-newsletter');

$profile->createOrUpdate();

$profile
    ->listId('LIST_ID')
    ->emailMarketing()
    ->smsMarketing()
    ->subscribe();
```

### Aggiungere un profilo a una lista senza cambiare il consenso marketing

Se hai gia il `profile_id` e vuoi solo collegarlo a una lista:

```php
use Wonder\Plugin\Klaviyo\Profiles;

(new Profiles())
    ->profileId('PROFILE_ID')
    ->listId('LIST_ID')
    ->addToList();
```

### Leggere il consenso marketing di un profilo

Klaviyo non restituisce i dati `subscriptions` di default. Devi richiederli esplicitamente:

```php
use Wonder\Plugin\Klaviyo\Profiles;

$profile = (new Profiles())
    ->withSubscriptions()
    ->get('PROFILE_ID');
```

Metodi piu usati di `Profiles`:

- `all()`
- `get()`
- `create()`
- `createOrUpdate()`
- `update()`
- `subscribe()`
- `addToList()`
- `withSubscriptions()`
- `listId()`
- `emailMarketing()`
- `smsMarketing()`
- `smsTransactional()`
- `ageGatedDateOfBirth()`
- `historicalImport()`

## Lists

La classe `Lists` serve per leggere, creare e modificare liste Klaviyo.

### Lettura liste

```php
use Wonder\Plugin\Klaviyo\Lists;

$lists = (new Lists())
    ->fields('list', ['name'])
    ->all();
```

### Creazione lista

```php
use Wonder\Plugin\Klaviyo\Lists;

$response = (new Lists())
    ->name('Newsletter Italia')
    ->create();
```

### Aggiunta profilo a lista da `Lists`

```php
use Wonder\Plugin\Klaviyo\Lists;

(new Lists())->addProfile('LIST_ID', 'PROFILE_ID');
```

Metodi piu usati di `Lists`:

- `all()`
- `get()`
- `create()`
- `update()`
- `delete()`
- `addProfile()`
- `addProfiles()`
- `removeProfile()`
- `removeProfiles()`

## Events

La classe `Events` serve per inviare eventi custom a Klaviyo.

### Creazione evento

```php
use Wonder\Plugin\Klaviyo\Events;

$response = (new Events())
    ->metricName('Placed Order')
    ->email('utente@example.com')
    ->property('order_id', '100001')
    ->property('value', 149.90)
    ->time(date(DATE_ATOM))
    ->create();
```

Metodi piu usati di `Events`:

- `all()`
- `get()`
- `create()`
- `metricName()`
- `email()`
- `phone()`
- `property()`
- `properties()`
- `time()`
- `value()`
- `valueCurrency()`

## Metrics

La classe `Metrics` serve per leggere le metriche disponibili e recuperare i relativi ID.

### Lettura metriche

```php
use Wonder\Plugin\Klaviyo\Metrics;

$metrics = (new Metrics())
    ->fields('metric', ['name'])
    ->all();
```

### Recupero `metric_id`

```php
use Wonder\Plugin\Klaviyo\Metrics;

$metrics = (new Metrics())
    ->fields('metric', ['name'])
    ->all();

$metricId = $metrics['data'][0]['id'] ?? null;
```

## Dati necessari al funzionamento

Per usare bene le classi Klaviyo ti servono quasi sempre:

1. una `Private API Key`
2. gli ID degli oggetti con cui lavori

### `list_id`

Lo ottieni da:

- interfaccia Klaviyo nella pagina della lista
- risposta di `Lists::all()`

```php
use Wonder\Plugin\Klaviyo\Lists;

$lists = (new Lists())
    ->fields('list', ['name'])
    ->all();

$listId = $lists['data'][0]['id'] ?? null;
```

### `profile_id`

Lo ottieni da:

- risposta di `Profiles::create()`
- risposta di `Profiles::createOrUpdate()`
- ricerca profili con `Profiles::all()`

```php
use Wonder\Plugin\Klaviyo\Profiles;

$profiles = (new Profiles())
    ->filter('equals(email,"utente@example.com")')
    ->fields('profile', ['email'])
    ->all();

$profileId = $profiles['data'][0]['id'] ?? null;
```

### `metric_id`

Lo ottieni da:

- risposta di `Metrics::all()`

```php
use Wonder\Plugin\Klaviyo\Metrics;

$metrics = (new Metrics())
    ->fields('metric', ['name'])
    ->all();

$metricId = $metrics['data'][0]['id'] ?? null;
```

## Errori comuni

### `400 Bad Request` su `subscribe()`

I casi piu comuni sono:

- `listId()` mancante o non valido
- chiave API senza scope `subscriptions:write`
- `phone_number` non in formato E.164
- numero valido ma regione SMS non supportata dal tuo account Klaviyo
- uso di `consented_at` senza `historicalImport(true)`

### Numero SMS non supportato dalla regione dell'account

Se ricevi un errore simile a:

```text
Phone number is valid but is not in a supported region for this account.
Please configure a sending number for this region.
```

significa che:

- il numero e corretto
- ma il tuo account Klaviyo non puo ancora inviare o gestire SMS per quella regione

In quel caso puoi:

- usare solo `emailMarketing()`
- salvare comunque il telefono sul profilo con `createOrUpdate()`
- configurare in Klaviyo un sending number compatibile con quella regione

### SMS marketing vs SMS transazionale

Regola pratica:

- `smsMarketing()` e il caso standard
- `smsMarketing()` copre anche l'invio di SMS transazionali
- `smsTransactional()` serve solo per profili `transactional-only`

Esempio email only:

```php
use Wonder\Plugin\Klaviyo\Profiles;

(new Profiles())
    ->email('utente@example.com')
    ->listId('LIST_ID')
    ->emailMarketing()
    ->subscribe();
```

### SMS age gating

Se il tuo account richiede age gating per l'SMS, devi passare anche:

```php
->ageGatedDateOfBirth('1990-01-01')
```

### Lettura subscription vuota

Se recuperi un profilo e non vedi `subscriptions`, quasi sempre manca:

```php
->withSubscriptions()
```

## Note pratiche

- `subscribe()` crea il profilo se non esiste gia
- `addToList()` aggiunge a una lista senza cambiare lo stato marketing
- `createOrUpdate()` e il metodo giusto per salvare anagrafica e proprieta custom
- `phone()` normalizza spazi e simboli, ma per l'SMS il numero deve comunque risultare E.164
- la paginazione Klaviyo e cursor-based
- le date devono essere in formato ISO 8601 / RFC 3339

## Link utili

- API overview: [https://developers.klaviyo.com/en/reference/api_overview](https://developers.klaviyo.com/en/reference/api_overview)
- Profiles API overview: [https://developers.klaviyo.com/en/reference/profiles_api_overview](https://developers.klaviyo.com/en/reference/profiles_api_overview)
- Subscribe Profiles: [https://developers.klaviyo.com/en/reference/subscribe_profiles](https://developers.klaviyo.com/en/reference/subscribe_profiles)
- Create or Update Profile: [https://developers.klaviyo.com/en/reference/create_or_update_profile](https://developers.klaviyo.com/en/reference/create_or_update_profile)
- SDK PHP: [https://github.com/klaviyo/klaviyo-api-php](https://github.com/klaviyo/klaviyo-api-php)
