# Avvio Locale (PHP 8.4)

Guida rapida per creare e avviare un progetto Wonder in locale con DB separato.

## 1) Procedura iniziale

Definisci una sola volta il nome del progetto nel terminale (Bash/Zsh): usa il dominio completo con i punti sostituiti da trattini. Per esempio, `wonderimage.it` diventa `wonderimage-it`. La variabile resta disponibile nella stessa sessione.

```bash
NOME_PROGETTO="wonderimage-it"
composer create-project wonder-image/new-site:dev-main "$NOME_PROGETTO"
cd "$NOME_PROGETTO"
composer update
git init
git remote add origin "https://github.com/wonder-image/${NOME_PROGETTO}.git"
php forge provision
php forge db:init
php forge update --local
php forge start
```

Dopo l’avvio, esegui dalla cartella del progetto (in un secondo terminale se il server PHP occupa il primo):

```bash
git add .
git commit -m "Initial commit"
git push -u origin HEAD
```

Apri **GitHub Desktop → Add → Add existing repository** e seleziona la cartella locale del progetto. Il repository è già collegato e pubblicato.

Configura `origin` prima di `provision`: così il comando crea (se necessario) e configura `wonder-image/${NOME_PROGETTO}`. Senza remote usa invece l’account personale autenticato. Se `origin` esiste già, controlla `git remote -v`; se punta al repository sbagliato, correggilo con `git remote set-url origin "https://github.com/wonder-image/${NOME_PROGETTO}.git"`.

**Scorciatoie alternative:** dopo il commit, se il repository remoto non esiste ancora, [GitHub Desktop](https://docs.github.com/en/desktop/adding-and-cloning-repositories/adding-an-existing-project-to-github-using-github-desktop) permette **Publish repository → Organization: wonder-image**. Con [GitHub CLI](https://cli.github.com/manual/gh_repo_create), se non esistono ancora né il repository remoto né `origin`, puoi usare `gh repo create "wonder-image/${NOME_PROGETTO}" --private --source=. --remote=origin --push`. Nel flusso sopra `provision` crea già il repository: basta `git push -u origin HEAD`, oppure **Publish branch** in Desktop se il primo push non è ancora stato eseguito.

Lo scaffold include `composer.lock`: `create-project` installa le versioni bloccate, mentre il successivo `composer update` aggiorna le dipendenze consentite da `composer.json`, incluso `wonder-image/app`. `:dev-main` seleziona il branch dello scaffold, non aggiorna le dipendenze bloccate. Entrambi i passaggi eseguono `php forge config` tramite gli script Composer: non occorre aggiungerlo alla sequenza iniziale. `provision` configura GitHub e Bitwarden e recupera i default locali `dev-shared`; `db:init` deve precedere `update --local`, che genera handler e tabelle. `db:init` chiede i dati mancanti: usa le credenziali del tuo MySQL locale.

Prerequisiti: PHP 8.2+, Composer, Node 20+, MySQL/MariaDB locale avviato, Git, GitHub CLI (`gh`) autenticata e accesso a Bitwarden Secrets Manager (`bws`). Herd è opzionale. Senza un remote, `provision` usa l’account GitHub autenticato e il nome della cartella.

## Dominio e URL locale

Per la cartella `wonderimage-it`, con Herd:

```dotenv
APP_DOMAIN=wonderimage.it
APP_URL=https://wonderimage.test
DB_DATABASE=main:wonderimage_it
```

`APP_DOMAIN` identifica il dominio completo; l’indirizzo locale si configura in `APP_URL`. Senza Herd, il valore predefinito di `APP_URL` è `http://127.0.0.1:8088`. `provision` non riscrive queste chiavi locali con i valori di produzione.

Il codice attuale di `forge config`, richiamato anche da `composer update`, conserva un `APP_URL` già valorizzato. Se un progetto ha ancora l’URL errato prodotto da una versione precedente, esegui `php forge start` per riallinearlo al driver locale. Con Herd il backend è su `https://wonderimage.test/backend/`.

## Dipendenze npm

`forge config` esegue `npm install wonder-image` e poi `npm install`: può aggiornare il pacchetto `wonder-image`, `package.json` e `package-lock.json`. Non esegue un aggiornamento esplicito del programma npm; se Node/npm mancano, il setup può installare Node tramite Homebrew, includendo npm.

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
APP_DOMAIN=wonderimage.it
```

il comando scrive:

```env
DB_DATABASE=main:wonderimage_it
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
- usa Herd su `https://wonderimage.test` se il comando `herd` e' disponibile
- esegue `herd link wonderimage`
- esegue `herd secure wonderimage`
- esegue `herd isolate {PHP_VERSION}` usando `--php-version` se passato, altrimenti la major.minor del PHP corrente
- sincronizza automaticamente `WonderValetDriver.php` nella configurazione globale di Herd, cosi' le route dinamiche vengono inoltrate a `handler/index.php`
- in fallback avvia il server PHP locale su `http://127.0.0.1:8088`
- gestisce route directory (`/backend/`)
- abilita `/update/` anche in sviluppo locale
- sincronizza `APP_DOMAIN` dalla cartella progetto ricostruendo il dominio completo (`wonderimage-it` → `wonderimage.it`)
- sincronizza `APP_URL` con `https://wonderimage.test` su Herd oppure con host/porta locali in fallback
- completa automaticamente `.env` per gli altri valori locali non DB critici (`APP_KEY`, `USER_PASSWORD`)
- fa un check DB iniziale

Puoi forzare il driver o la versione PHP:

```bash
php forge start --driver=herd --php-version=8.4
php forge start --driver=php
```

## 8) Proxy media da produzione

In locale i media uploadati in produzione non esistono sul filesystem. Per evitare immagini rotte, il `WonderValetDriver` supporta un proxy fallback.

Nel `.env` del progetto:

```dotenv
MEDIA_FALLBACK_URL=https://www.example.it
```

Con questa variabile valorizzata, Herd fa un redirect 302 per ogni file mancante sotto `assets/upload/` verso la URL di produzione corrispondente.

Funziona solo con Herd/Valet, non con il fallback `php -S`.

## 9) Sincronizzare i dati condivisi (CSS, SEO, dati aziendali)

Se il progetto ha un file `shared/sync-data.json` committato (generato con `forge export` dalla produzione), i dati vengono importati automaticamente durante `forge update --local`.

Per importare manualmente:

```bash
php forge import
```

Per esportare i dati correnti (utile dopo modifiche locali):

```bash
php forge export
```

Vedi la documentazione completa in [Multi-ambiente](multi-ambiente.md).

## 10) .htaccess e robots.txt

In locale `.htaccess` non e' tracciato in git. Viene generato automaticamente da `forge update --local` (o `forge build`) usando il template `Build::htaccessTemplate()`.

Se vuoi forzare la rigenerazione:

```bash
php forge build --force
```

`robots.txt` viene creato solo se manca, con dominio e prefisso www derivati dalla configurazione locale.

## 11) URL utili

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
- inoltra le route dinamiche (`/`, `/backend/...`, `/api/...`, pagine router) a `handler/index.php`
- se `MEDIA_FALLBACK_URL` e' valorizzato nel `.env`, fa redirect 302 per media mancanti sotto `assets/upload/`

Se il routing sotto Herd smette di funzionare, il primo controllo da fare e':

```bash
ls ~/Library/Application\\ Support/Herd/config/valet/Drivers/WonderValetDriver.php
php forge start
```

Se Herd mostra `could not find a valid PHP file to serve`, i controlli prioritari sono:

- esiste `ROOT/handler/index.php`
- `WonderValetDriver.php` e' stato risincronizzato dopo l'ultimo update del framework
- la homepage `/` viene inoltrata al driver Wonder e non richiede un `index.php` fisico nel root del progetto

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

## 12) Nota su `/update/`

Se avvii con `php -S` senza router custom, `/update/` potrebbe non funzionare.
Con `php forge start` la route viene gestita automaticamente.

In piu', durante l'avvio locale vengono normalizzate anche le URL legacy del backend come `/backend/.../index.php`, cosi' i redirect storici continuano a funzionare con il router nuovo.
