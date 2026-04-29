# Avvio Locale (PHP 8.4)

Guida rapida per creare e avviare un progetto Wonder in locale con DB separato.

## 1) Flusso rapido reale

Dopo:

```bash
composer create-project wonder-image/new-site project-name
cd project-name
```

il flusso pratico consigliato è questo:

```bash
php forge config
php forge provision
php forge db:init --admin-host=127.0.0.1 --admin-port=3306 --admin-username=root --admin-password=secret
php forge update --local
php forge start
```

Significato rapido:

- `php forge config` prepara il progetto locale
- `php forge provision` configura l’ambiente di progetto lato GitHub e Bitwarden, utile per il flusso di deploy/produzione
- `php forge db:init` inizializza `.env` e crea database, utente e grant locali
- `php forge update --local` genera i file e i task locali necessari
- `php forge start` avvia il server locale

Ordine consigliato per il locale:

- prima `php forge db:init`
- poi `php forge update --local`
- poi `php forge start`

`php forge start` non crea il database. Se il DB locale non esiste ancora, il comando ti segnala di usare `php forge db:init`.

## 2) Prerequisiti

```bash
php -v
composer -V
mysql --version
```

## 3) Crea progetto test parallelo

```bash
cd /Users/andreamarinoni/Desktop/PROGETTI/template
cp -R new-site new-site-php84-sf8
cd new-site-php84-sf8
```

## 4) Collega il pacchetto `app` locale (worktree lab)

```bash
composer config repositories.wonder-image-app path ../app-php84-sf8
composer require wonder-image/app:"dev-codex/php84-sf8-lab as 1.5.x-dev" --with-all-dependencies
```

## 5) Inizializza `.env` e database locale

Usa `php forge db:init` per:

- creare `.env` se manca
- completare solo i valori mancanti
- derivare `DB_DATABASE` da `APP_DOMAIN`
- creare database, utente applicativo e grant MySQL

Esempio reale:

```bash
php forge db:init \
  --admin-host=127.0.0.1 \
  --admin-port=3306 \
  --admin-username=root \
  --admin-password=secret \
  --app-db-username=new_site_user \
  --app-db-password=secret123
```

Con:

```env
APP_DOMAIN=new-site
```

il comando scrive:

```env
DB_DATABASE=main:new_site
```

Le credenziali admin MySQL servono solo per il provisioning e non vengono salvate nel file `.env`.

## 6) Differenza tra `db:init` e `start`

- `php forge db:init` prepara `.env` e fa il provisioning esplicito del database locale
- `php forge start` completa solo i valori locali non critici, verifica la connessione DB e usa Laravel Herd se disponibile, altrimenti fa fallback al server PHP integrato
- se il DB manca o l’accesso viene negato, `php forge start` suggerisce di eseguire `php forge db:init`

## 7) Avvio semplice con Forge

### Requisiti Herd

Secondo la documentazione ufficiale di Laravel Herd:

- Herd richiede macOS 12.0 o superiore
- l'onboarding installa un servizio di background che richiede permessi admin
- Herd include PHP, nginx, dnsmasq e Node.js
- dopo l'installazione devono essere disponibili da terminale: `herd`, `php`, `composer`, `laravel`, `node`

Per questo progetto conviene avere anche:

- MySQL o MariaDB locale se usi `php forge db:init`
- accesso al certificato locale `.test` se vuoi usare HTTPS con webhook, login OAuth o callback esterne

### Installare Herd

1. Scarica Herd da [herd.laravel.com](https://herd.laravel.com/docs/1/getting-started/installation)
2. Apri il file `.dmg`
3. Trascina Herd in `Applications`
4. Avvia Herd e completa l'onboarding
5. Verifica da terminale:

```bash
herd --version
php --version
composer --version
node --version
```

Se usi una shell diversa o il binario non è nel `PATH`, verifica che esista:

```bash
~/Library/Application\ Support/Herd/bin/herd
```

Dal root del progetto (`new-site-php84-sf8`):

```bash
php forge start
```

Il comando:
- usa Herd su `https://APP_DOMAIN.test` se il comando `herd` e' disponibile
- esegue `herd link APP_DOMAIN`
- esegue `herd secure APP_DOMAIN`
- esegue `herd isolate {PHP_VERSION}` usando `--php-version` se passato, altrimenti la major.minor del PHP corrente
- sincronizza automaticamente `WonderValetDriver.php` nella configurazione globale di Herd, cosi' le route dinamiche vengono inoltrate a `handler/index.php`
- in fallback avvia il server PHP locale su `http://127.0.0.1:8088`
- gestisce route directory (`/backend/`)
- abilita `/update/` anche in sviluppo locale
- sincronizza `APP_DOMAIN` dalla cartella progetto in formato kebab-case senza punti
- sincronizza `APP_URL` con `https://APP_DOMAIN.test` su Herd oppure con host/porta locali in fallback
- completa automaticamente `.env` per gli altri valori locali non DB critici (`APP_KEY`, `USER_PASSWORD`)
- fa un check DB iniziale

Puoi forzare il driver o la versione PHP:

```bash
php forge start --driver=herd --php-version=8.4
php forge start --driver=php
```

## 8) URL utili

- Home: `https://new-site.test/` con Herd, altrimenti `http://127.0.0.1:8088/`
- Backend: `https://new-site.test/backend/` con Herd, altrimenti `http://127.0.0.1:8088/backend/`
- Login backend: `https://new-site.test/backend/account/login/` con Herd, altrimenti `http://127.0.0.1:8088/backend/account/login/`
- Update (safe, senza side-effect): `https://new-site.test/update/` con Herd, altrimenti `http://127.0.0.1:8088/update/`
- Esegui update: `https://new-site.test/update/run/` con Herd, altrimenti `http://127.0.0.1:8088/update/run/`

## 9) Routing con Herd

Con Herd il progetto non passa dal router temporaneo di `php -S`, quindi le route nuove devono entrare da:

```text
handler/index.php
```

Per questo Wonder genera automaticamente nel root del progetto:

```text
~/Library/Application Support/Herd/config/valet/Drivers/WonderValetDriver.php
```

Quel driver:

- lascia invariati file statici e pagine fisiche
- inoltra le route dinamiche (`/backend/...`, `/api/...`, pagine router) a `handler/index.php`

Se il routing sotto Herd smette di funzionare, il primo controllo da fare e':

```bash
ls ~/Library/Application\\ Support/Herd/config/valet/Drivers/WonderValetDriver.php
php forge start
```

## 10) Vedere il DB in modo chiaro (CLI)

Connessione:

```bash
mysql -h 127.0.0.1 -P 3306 -u new_site_user -p new_site
```

Comandi utili:

```sql
SHOW TABLES;
DESCRIBE security;
SELECT id, mail_host, stripe_test FROM security LIMIT 20;
```

## 11) Nota su `/update/`

Se avvii con `php -S` senza router custom, `/update/` potrebbe non funzionare.
Con `php forge start` la route viene gestita automaticamente.

In piu', durante l'avvio locale vengono normalizzate anche le URL legacy del backend come `/backend/.../index.php`, cosi' i redirect storici continuano a funzionare con il router nuovo.
