# Dev-shared: chiavi dev condivise tra progetti

## Cos'è

Le chiavi di servizi esterni (reCAPTCHA, Google Tag Manager, Google Maps,
Klaviyo, SMTP di test, Stripe test, …) hanno valori diversi fra **locale** e
**produzione**: in prod sono chiavi reali registrate sul dominio del cliente;
in locale ti basta una set di chiavi "dev" personali che riusi su ogni
progetto.

`forge config` e `forge provision` supportano un **progetto Bitwarden
condiviso** chiamato `dev-shared`: viene scoperto automaticamente per nome
dal tuo `BWS_ACCESS_TOKEN` e i suoi secret vengono copiati nel `.env` locale
del progetto in modalità **fill-missing** (se la chiave è già impostata
localmente, non la sovrascrive).

In produzione `dev-shared` non viene MAI letto: il `.env` di prod è generato
dal workflow CI partendo solo dal project Bitwarden specifico del sito.

## Setup (una tantum)

### 1. Crea il progetto Bitwarden

Da [Bitwarden Secrets Manager](https://vault.bitwarden.com/#/sm) crea un
**nuovo project** chiamato esattamente `dev-shared` (nome case-insensitive,
ma usa il kebab-case per chiarezza).

### 2. Popola le chiavi

Per ogni servizio per cui vuoi un default dev personale, aggiungi un secret
al project `dev-shared`. La **key del secret** deve combaciare con l'env
var che il framework legge:

| Servizio | Env key in Bitwarden `dev-shared` |
|---|---|
| reCAPTCHA | `G_RECAPTCHA_SITE_KEY` |
| Google Maps Place | `G_MAPS_PLACE_ID` |
| Google Maps Map ID | `G_MAPS_MAP_ID` |
| Google Cloud client API key | `GCP_CLIENT_API_KEY` |
| Klaviyo | `KLAVIYO_API_KEY` |
| IPInfo | `IPINFO_API_KEY` |
| Stripe (test) | `STRIPE_TEST=true`, `STRIPE_TEST_KEY`, `STRIPE_TEST_ACCOUNT_ID` |
| Fatture in Cloud | `FATTURE_IN_CLOUD_*` |
| Google OAuth | `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET`, `GOOGLE_OAUTH_REDIRECT_URI` |
| Apple OAuth | `APPLE_OAUTH_CLIENT_ID`, `APPLE_OAUTH_TEAM_ID`, … |
| SMTP test | `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SERVICE` |
| Brevo | `BREVO_API_KEY` |

> Le **chiavi escluse** dal merge dev-shared sono quelle che devono
> essere project-specific anche localmente (`BWS_*`, `APP_KEY`, `APP_DOMAIN`,
> `DB_*`, `FTP_*`, `USER_*`, `APP_DEPLOY_TOKEN`, `ASSETS_VERSION`). Se per
> errore le metti in `dev-shared` vengono semplicemente ignorate, con un
> warning a console.

### 3. Esegui forge config

Su un qualsiasi progetto wonder-image con `BWS_ACCESS_TOKEN` impostato in
`.env`:

```bash
php forge config
```

Output atteso:

```
🔄 dev-shared → .env locale: G_RECAPTCHA_SITE_KEY, KLAVIYO_API_KEY, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, BREVO_API_KEY
```

Le chiavi presenti nel `.env` ma vuote (`KEY=`) vengono riempite. Quelle
già valorizzate vengono lasciate intatte (per-project override vince).

## Auto-discovery vs override esplicito

Di default il framework cerca un project Bitwarden chiamato `dev-shared`
nella lista ritornata da `bws project list`. Se ne hai più di uno (es. uno
per cliente) puoi specificare l'UUID nel `.env` del progetto:

```dotenv
BWS_DEV_SHARED_PROJECT_ID=12345678-aaaa-bbbb-cccc-1234567890ab
```

L'override esplicito vince sempre sull'auto-discovery.

## Come `Credentials` legge questi valori

`Wonder\App\Credentials::api()` e `Wonder\App\Credentials::mail()` usano una
**cascade a tre livelli**:

```
1. $_ENV[$envKey]               ← popolato da dev-shared in locale
                                  (o ad-hoc dal dev per testing)
2. $row[$rowKey]                ← tabella security del DB
                                  (single source of truth in prod, valori
                                  inseriti dal backend admin)
3. apiDefaults() / mailDefaults()  ← hardcoded vuoti
```

In produzione il `.env` non contiene queste chiavi (i dev-shared non sono
sincronizzati lato deploy), quindi vince il DB. In locale, dev-shared
popola il `.env` e vince sul DB di sviluppo senza bisogno di replicare le
chiavi nel backend admin di ogni sito.

Una stringa vuota nel `.env` viene considerata "non impostata" (così una
riga `KEY=` non shadow-a un valore valido del DB).

## Recovery se cambi laptop

1. Installi `bws` CLI
2. Generi un nuovo `BWS_ACCESS_TOKEN` su Bitwarden web
3. Cloni i progetti, imposti il token in ogni `.env` locale
4. Esegui `php forge config` o `php forge provision`
5. Auto-discovery trova `dev-shared` e riempie il `.env` automaticamente

Nessuna chiave persa, nessun file di backup da mantenere.

## Comandi utili

```bash
# Lista i project Bitwarden visibili dal tuo token
bws project list

# Lista i secret del project dev-shared
bws secret list <UUID_DEV_SHARED>

# Aggiungi un nuovo secret a dev-shared
bws secret create G_RECAPTCHA_SITE_KEY "6LeIxAcT...test_key" <UUID_DEV_SHARED>

# Forza un re-merge dev-shared → .env locale (idempotente)
php forge config
```

## Cosa NON deve finire in dev-shared

- **Chiavi di produzione di un cliente specifico** → vanno nel project
  Bitwarden del sito (es. `wonder-image/new-site`).
- **Credenziali database** (`DB_*`) e FTP (`FTP_*`) → comunque ignorate
  dal merge, ma per chiarezza non metterle.
- **APP_KEY** → diversa per ambiente, mai shared.
- **Token deploy** (`APP_DEPLOY_TOKEN`) → vive solo nei GitHub Secrets della
  repo.
