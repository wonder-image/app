---
icon: key
---

# Segreti nel processo

Come passare i segreti (database, chiavi API, chiave di cifratura) al sito
dall'ambiente del processo invece che da un `.env` scritto sul server.

Il `.env` resta supportato e facoltativo. Questa pagina riguarda solo
**come arrivano i valori**: i nomi delle chiavi e il loro significato non
cambiano.

## Come funziona

All'avvio, prima di leggere il `.env`, il framework porta in `$_ENV` le
variabili d'ambiente reali del processo. Funziona anche quando `variables_order`
di PHP non contiene `E` o PHP-FPM ha `clear_env` attivo, e vale sia per il web
sia per la console (`forge`, scheduler).

La **precedenza** è questa:

1. variabile dell'ambiente del processo (se non vuota)
2. chiave del `.env`

Il `.env` viene comunque caricato per le chiavi che l'ambiente non ha. Una
variabile **vuota** nell'ambiente vale come non impostata: il `.env` può
valorizzare quella chiave.

## Come passarli al processo

### Con `bws run` (Bitwarden Secrets Manager)

`bws run` legge i secret del project e avvia il comando con quei valori
nell'ambiente. Il token di accesso (`BWS_ACCESS_TOKEN`) serve solo a `bws` e non
entra mai in `$_ENV` a runtime.

```bash
bws run --project-id "$BWS_PROJECT_ID" -- php forge schedule:run
```

Lo stesso vale per qualunque comando della console. Per il web il processo da
avviare con `bws run` è quello che lancia PHP (per esempio PHP-FPM): in questo
caso le variabili passano a tutti i worker.

### Con variabili d'ambiente del server

Senza `bws`, qualsiasi meccanismo che mette le variabili nel processo va bene.

```nginx
# nginx: fastcgi_param
fastcgi_param APP_ENV production;
fastcgi_param DB_HOST $db_host;
```

```ini
; PHP-FPM: pool.d/www.conf
env[APP_ENV] = production
```

Anche `export NOME=valore` nella shell che lancia `php forge` funziona. Metti
nei file di configurazione del server solo segnaposto o riferimenti: i valori
reali restano nel gestore dei segreti e non vanno committati.

## Quali nomi vengono letti

Sono ammessi solo i nomi di un elenco chiuso. Qualunque altra variabile
(per esempio `PATH`, `HTTP_*`, `PHP_AUTH_*`) non viene mai copiata in `$_ENV`.

**Nomi esatti:** `ASSETS_VERSION`, `API_KEY`, `GITHUB_API_TOKEN`,
`IMAGE_MISSING_LABEL`, `QUERY_COUNTER_TOP`, `SYNC_AUTO_EXPORT`, `SYNC_TABLES`,
`USER_USERNAME`, `USER_NAME`, `USER_SURNAME`, `USER_EMAIL`, `USER_PASSWORD`.

**Prefissi:** `APP_`, `DB_`, `MAIL_`, `BREVO_`, `GOOGLE_`, `APPLE_`,
`G_RECAPTCHA_`, `G_MAPS_`, `G_TAG_MANAGER_`, `GCP_`, `KLAVIYO_`, `IPINFO_`,
`STRIPE_`, `PAYPAL_`, `NEXI_`, `FATTURE_IN_CLOUD_`, `OAUTH_`, `MEMORY_PROFILE_`.

**Esclusi apposta:** `FTP_*`, `BWS_*`, `CLOUDFLARE_*` e `NPM_GITHUB_TOKEN` sono
credenziali di deploy e non entrano mai in `$_ENV` a runtime.

## Chiave di cifratura

`APP_ENCRYPTION_KEY` (64 caratteri esadecimali) segue le stesse regole: può
arrivare dal processo o dal `.env`.

**Per pubblicare serve la chiave:** il deploy la richiede e si ferma se
`APP_ENCRYPTION_KEY` manca o non è valida (64 caratteri esadecimali). Se a runtime manca o non è valida, fuori da
`local` e via web il framework scrive una riga nell'`error_log` (una volta per
processo, senza mai il valore). Non è un errore bloccante: senza la chiave i
segreti cifrati nel database (`enc:v1:`) non si leggono e quelli nuovi si
salvano in chiaro. Dettagli e rotazione in [Sicurezza](sicurezza.md).

## Attenzione

- Una variabile dell'host con uno dei nomi ammessi e un valore diverso da quello
  del `.env` **prevale** sul `.env`. Se un valore sembra ignorato, controlla
  prima l'ambiente del processo.
- Il deploy attuale continua a scrivere il `.env`: passare i segreti dal
  processo è un'alternativa, non sostituisce ancora il deploy. Vedi
  [Installazione e Deploy](installazione-e-deploy.md).
