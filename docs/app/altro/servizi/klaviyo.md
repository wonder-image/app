# Klaviyo

{% hint style="info" %}
Link di accesso: [https://www.klaviyo.com/](https://www.klaviyo.com/)
{% endhint %}

## Configurazione rapida

1. In Klaviyo apri `Settings` -> `API keys`.
2. Nella sezione `Private API Keys` clicca `Create Private API Key`.
3. Dai un nome alla chiave e abilita gli scope per le risorse che userai.
4. Copia subito la chiave `pk_...` in un posto sicuro: Klaviyo non la mostra di nuovo dopo la creazione.
5. Nel backend del sito apri la sezione credenziali.
6. Incolla la chiave nel campo `Klaviyo API Key`.

Nota importante:

- i wrapper `Wonder\Plugin\Klaviyo\*` lavorano sugli endpoint server-side `/api`
- per questi endpoint serve una `private API key`
- la `public API key` / `site ID` da 6 caratteri serve agli endpoint `/client` e non va usata con queste classi

Se devi usare solo una parte delle API, crea una chiave con scope minimi. In generale abilita lettura e/o scrittura solo sulle risorse che usi davvero.

## Plugin disponibili

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

Di default la chiave API viene letta automaticamente da `Credentials::api()->klaviyo_api_key`.

## Pattern di utilizzo

Tutte le classi Klaviyo usano lo stesso approccio:

- crei l'istanza
- imposti parametri query o body
- esegui la chiamata finale

I wrapper inoltrano tutte le funzioni esposte dal package `klaviyo/api`, quindi i nomi dei metodi seguono quelli ufficiali della SDK:

- `Profiles` usa metodi come `getProfiles()`, `getProfile()`, `createProfile()`, `updateProfile()`
- `Lists` usa metodi come `getLists()`, `getList()`, `createList()`, `addProfilesToList()`
- `Segments` usa metodi come `getSegments()`, `getSegment()`
- `Metrics` usa metodi come `getMetrics()`, `getMetric()`
- `Events` usa metodi come `getEvents()`, `createEvent()`
- `Templates` usa metodi come `getTemplates()`, `getTemplate()`
- `Flows` usa metodi come `getFlows()`, `getFlow()`, `createFlow()`
- `Campaigns` usa metodi come `getCampaigns()`, `getCampaign()`, `createCampaign()`
- `Forms` usa metodi come `getForms()`, `getForm()`, `createForm()`
- `Webhooks` usa metodi come `getWebhooks()`, `getWebhook()`

Esempio base:

```php
use Wonder\Plugin\Klaviyo\Profiles;

$response = (new Profiles())
    ->pageSize(10)
    ->getProfiles();
```

Se vuoi forzare una chiave API specifica:

```php
use Wonder\Plugin\Klaviyo\Profiles;

$profiles = Profiles::connect('pk_...');
```

In alternativa puoi usare `Profiles::apiKey('pk_...')`.

### Parametri query

Per filtri, sorting, include e paginazione puoi usare:

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

$response = (new Profiles())
    ->filter('equals(email,"utente@example.com")')
    ->additionalField('profile', 'predictive_analytics')
    ->fields('profile', ['email', 'first_name', 'last_name'])
    ->pageSize(10)
    ->getProfiles();
```

### Parametri body

Per le chiamate `create*()` / `update*()` hai due modi:

1. costruisci il body con `body()` / `addParams()`
2. passi il parametro ufficiale della SDK come named argument

Esempio con `body()`:

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

Esempio con named arguments:

```php
use Wonder\Plugin\Klaviyo\Profiles;

$response = (new Profiles())->createProfile(
    profile_create_query: [
        'data' => [
            'type' => 'profile',
            'attributes' => [
                'email' => 'utente@example.com'
            ]
        ]
    ]
);
```

## Dati necessari al funzionamento

Per usare bene le classi Klaviyo ti servono quasi sempre due cose:

1. una `private API key`
2. gli ID degli oggetti con cui vuoi lavorare

### Private API key

La private key:

- inizia normalmente con `pk_`
- abilita le chiamate agli endpoint `/api`
- puo leggere e scrivere dati in base agli scope assegnati
- non deve mai essere esposta nel frontend o in repository pubbliche

Ricorda che dopo la creazione Klaviyo non ti mostra piu il valore completo della chiave. Se la perdi, devi crearne una nuova.

### Come recuperare gli ID delle risorse

Regola pratica:

- il modo piu robusto e programmabile e recuperarli via API e usare `data[*].id`
- molti ID sono anche visibili nell'interfaccia Klaviyo

Klaviyo indica esplicitamente che ogni lista ha un ID dedicato e che lo stesso concetto vale anche per `user`, `flow`, `campaign`, `segment` e altri oggetti. Per le liste l'ID si trova in `Lists & Segments` -> lista -> `Settings` oppure nella URL della pagina.

Di seguito i dati che ti servono piu spesso e come ottenerli.

### `profile_id`

Lo ottieni da:

- risposta di `createProfile()`
- risultato di `getProfiles()`
- chiamata diretta `getProfile($id)` se gia lo conosci

Esempio:

```php
use Wonder\Plugin\Klaviyo\Profiles;

$profiles = (new Profiles())
    ->fields('profile', ['email', 'first_name'])
    ->pageSize(20)
    ->getProfiles();

$profileId = $profiles['data'][0]['id'] ?? null;
```

### `list_id`

Lo ottieni da:

- `Lists & Segments` -> lista -> `Settings`
- risultato di `getLists()`

```php
use Wonder\Plugin\Klaviyo\Lists;

$lists = (new Lists())->getLists(
    fields_list: ['name']
);

$listId = $lists['data'][0]['id'] ?? null;
```

### `segment_id`

Lo ottieni da:

- risultato di `getSegments()`
- interfaccia Klaviyo quando apri il segmento

```php
use Wonder\Plugin\Klaviyo\Segments;

$segments = (new Segments())->getSegments(
    fields_segment: ['name']
);

$segmentId = $segments['data'][0]['id'] ?? null;
```

### `metric_id`

Serve spesso per analytics, eventi e report.

Lo ottieni da:

- `getMetrics()`
- attività analytics di Klaviyo

```php
use Wonder\Plugin\Klaviyo\Metrics;

$metrics = (new Metrics())->getMetrics(
    fields_metric: ['name']
);

$metricId = $metrics['data'][0]['id'] ?? null;
```

### `template_id`

Lo ottieni da:

- `getTemplates()`
- editor template in Klaviyo

```php
use Wonder\Plugin\Klaviyo\Templates;

$templates = (new Templates())->getTemplates(
    fields_template: ['name']
);

$templateId = $templates['data'][0]['id'] ?? null;
```

### `flow_id`

Lo ottieni da:

- `getFlows()`
- interfaccia Flows in Klaviyo

```php
use Wonder\Plugin\Klaviyo\Flows;

$flows = (new Flows())->getFlows(
    fields_flow: ['name'],
    page_size: 20
);

$flowId = $flows['data'][0]['id'] ?? null;
```

### `campaign_id`

Lo ottieni da:

- `getCampaigns()`
- interfaccia Campaigns in Klaviyo

```php
use Wonder\Plugin\Klaviyo\Campaigns;

$campaigns = (new Campaigns())->getCampaigns(
    filter: 'equals(archived,false)',
    fields_campaign: ['name'],
);

$campaignId = $campaigns['data'][0]['id'] ?? null;
```

### `form_id`

Lo ottieni da:

- `getForms()`
- interfaccia Forms in Klaviyo

```php
use Wonder\Plugin\Klaviyo\Forms;

$forms = (new Forms())->getForms(
    fields_form: ['name'],
    page_size: 20
);

$formId = $forms['data'][0]['id'] ?? null;
```

### `webhook_id`

Lo ottieni da:

- `getWebhooks()`
- sezione Webhooks in Klaviyo

```php
use Wonder\Plugin\Klaviyo\Webhooks;

$webhooks = (new Webhooks())->getWebhooks();

$webhookId = $webhooks['data'][0]['id'] ?? null;
```

## Classi piu usate

### Profiles

La classe `Profiles` e quella che userai piu spesso per leggere, creare e aggiornare contatti/profili Klaviyo.

Metodi piu usati:

- `getProfiles()`
- `getProfile()`
- `createProfile()`
- `updateProfile()`
- `getProfileRelationshipsLists()`
- `getListsForProfile()`
- `getSegmentsForProfile()`

Aggiornamento profilo:

```php
use Wonder\Plugin\Klaviyo\Profiles;

(new Profiles())
    ->body([
        'data' => [
            'type' => 'profile',
            'id' => 'PROFILE_ID',
            'attributes' => [
                'first_name' => 'Mario',
                'last_name' => 'Rossi'
            ]
        ]
    ])
    ->updateProfile('PROFILE_ID');
```

### Lists e Segments

Le classi `Lists` e `Segments` servono per recuperare gruppi di utenti e relative relazioni.

Metodi piu usati:

- `getLists()`
- `getList()`
- `createList()`
- `addProfilesToList()`
- `getSegments()`
- `getSegment()`
- `getProfilesForList()`
- `getProfilesForSegment()`

### Metrics ed Events

Usa `Metrics` per recuperare metriche disponibili e relativi ID, e `Events` per leggere o inviare eventi.

Metodi piu usati:

- `getMetrics()`
- `getMetric()`
- `getEvents()`
- `createEvent()`

Lettura eventi filtrati per metrica:

```php
use Wonder\Plugin\Klaviyo\Events;

$events = (new Events())
    ->filter('equals(metric_id,"METRIC_ID")')
    ->sort('-datetime')
    ->pageCursor('https://a.klaviyo.com/api/events/?page%5Bcursor%5D=...')
    ->getEvents();
```

### Templates, Flows e Campaigns

Queste classi sono utili quando vuoi collegare automazioni e messaggi gia presenti in Klaviyo.

Metodi piu usati:

- `getTemplates()`
- `getTemplate()`
- `getFlows()`
- `getFlow()`
- `createFlow()`
- `getCampaigns()`
- `getCampaign()`
- `createCampaign()`

### Forms e Webhooks

Se devi lavorare con acquisizione lead o integrazioni server-server:

- `Forms` espone `getForms()`, `getForm()`, `createForm()`
- `Webhooks` espone `getWebhooks()`, `getWebhook()`

## Note operative

- I filtri Klaviyo usano la sintassi `filter=...`, per esempio `equals(email,"utente@example.com")`.
- Lo sorting usa `sort=campo` oppure `sort=-campo`.
- Le sparse fieldsets usano `fields[TIPO]`, per esempio `fields[profile]=email,first_name`.
- L'inclusione relazioni usa `include`, per esempio `include=lists`.
- La paginazione e cursor-based: puoi passare direttamente il link `next` ricevuto dalla risposta a `pageCursor()`.
- Le date devono essere in formato ISO 8601 / RFC 3339.

## Link utili

- API overview: [https://developers.klaviyo.com/en/reference/api_overview](https://developers.klaviyo.com/en/reference/api_overview)
- Authenticate API requests: [https://developers.klaviyo.com/en/docs/authenticate_](https://developers.klaviyo.com/en/docs/authenticate_)
- How to find a list ID: [https://help.klaviyo.com/hc/en-us/articles/115005078647](https://help.klaviyo.com/hc/en-us/articles/115005078647)
- SDK PHP: [https://github.com/klaviyo/klaviyo-api-php](https://github.com/klaviyo/klaviyo-api-php)
