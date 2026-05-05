---
icon: wrench
---

# Installazione e Deploy

Questa è la procedura reale oggi per usare `wonder-image/app` insieme a `wonder-image/new-site`.

I comandi importanti sono cinque:

- `php forge config`
- `php forge provision`
- `php forge update`
- `php forge db:init`
- `php forge start`

## Cosa fa ogni comando

### `php forge config`

Serve per la configurazione del progetto.

Fa questo:

- completa `.env`
- normalizza `APP_DOMAIN`, `APP_URL`, `ASSETS_VERSION`
- aggiorna il `name` in `composer.json`
- crea `package.json` se manca
- in locale esegue `npm install wonder-image`
- in CI **non** esegue `npm install wonder-image`

Non fa più:

- provisioning GitHub
- provisioning Bitwarden

### `php forge provision`

Serve solo in locale.

Fa questo:

- configura Bitwarden
- crea o recupera `BWS_PROJECT_ID`
- crea o verifica la repository GitHub
- sincronizza secrets e variables GitHub
- sincronizza i secrets Bitwarden del progetto

In CI viene saltato.

### `php forge update`

Serve per l’update applicativo.

Fa questo:

- applica tabelle
- esegue i file in `build/row`
- esegue i file in `build/update`

Con `--local` esegue anche:

- i file in `build/cli`

Quindi:

- `php forge update` va bene anche in CI
- `php forge update --local` va usato in locale

### `php forge db:init`

Serve per inizializzare `.env` e il database locale.

Fa questo:

- crea `.env` se manca
- completa solo i valori mancanti
- deriva `DB_DATABASE` da `APP_DOMAIN`
- crea database, utente applicativo e grant MySQL
- non salva le credenziali admin MySQL nel file `.env`

Esempio:

```bash
php forge db:init --admin-host=127.0.0.1 --admin-port=3306 --admin-username=root --admin-password=secret --app-db-username=new_site_user --app-db-password=secret123
```

Con `APP_DOMAIN=new-site` il valore scritto è `DB_DATABASE=main:new_site`.

### `php forge start`

Serve per l’avvio locale rapido.

Fa questo:

- completa automaticamente `.env` solo per i valori locali non DB critici
- controlla la connessione DB
- usa Laravel Herd se disponibile e pubblica il sito su `https://APP_DOMAIN.test`
- esegue `herd link`, `herd secure` e `herd isolate`
- sincronizza `WonderValetDriver.php` nella configurazione globale di Herd per fare routing corretto sotto Herd
- in fallback avvia il server PHP integrato
- espone i path compatibili con il progetto

Se il DB manca o l’utente applicativo non ha accesso, suggerisce di eseguire `php forge db:init`.

Uso tipico:

```bash
php forge start
```

Quando il progetto nasce da una cartella come `new.site` o `New Site`, il bootstrap locale normalizza automaticamente `APP_DOMAIN` in `new-site`.

### Routing locale con Herd

Herd usa il proprio driver PHP locale. Per questo i progetti Wonder sincronizzano un driver globale in:

```text
~/Library/Application Support/Herd/config/valet/Drivers/WonderValetDriver.php
```

Il file viene generato automaticamente da:

- `php forge start`
- `php forge update --local`

Il driver inoltra le route dinamiche al front controller:

```text
handler/index.php
```

cosi' `/backend/...`, `/api/...` e le pagine router funzionano anche fuori dal router temporaneo di `php -S`.

### Requisiti ambiente locale consigliati

- macOS 12.0 o superiore per usare Herd
- Herd installato e avviato almeno una volta
- permessi admin concessi durante l'onboarding di Herd per i suoi servizi locali
- MySQL o MariaDB locale se il progetto usa `php forge db:init`
- supporto HTTPS locale `.test` se il progetto usa callback, webhook o endpoint che richiedono richieste sicure

### Installare Herd

Riferimento ufficiale: [Laravel Herd Installation](https://herd.laravel.com/docs/1/getting-started/installation)

1. Scarica Herd
2. Apri il `.dmg`
3. Sposta l'app in `Applications`
4. Avvia Herd e completa l'onboarding
5. Verifica da terminale:

```bash
herd --version
php --version
composer --version
node --version
```

## Struttura file aggiornata

### Update condiviso

- `app/build/update/configuration_file.php`
- `app/build/update/css.php`
- `app/build/update/sitemap.php`

Questi file vengono eseguiti sia da update locale che da update lato deploy/server.

### Task solo CLI locale

- `app/build/cli/update.php`

Questo file:

- crea `ROOT/handler/index.php`
- pulisce path legacy che non devono più esistere

Va eseguito solo da CLI locale con:

```bash
php forge update --local
```

## Prima installazione locale

### 1. Crea il progetto

```bash
composer create-project wonder-image/new-site project-name
cd project-name
```

### 2. Configura il progetto

```bash
php forge config
```

### 3. Configura GitHub e Bitwarden

```bash
php forge provision
```

### 4. Genera handler e file locali

```bash
php forge update --local
```

### 5. Inizializza database locale

```bash
php forge db:init --admin-host=127.0.0.1 --admin-port=3306 --admin-username=root --admin-password=secret
```

### 6. Avvia o pubblica il progetto

Da questo punto hai:

- `.env` pronto
- `handler/index.php` generato
- `.htaccess` aggiornato
- route e layout attivi

## Import progetto legacy già esistente

Questa procedura serve quando hai già un progetto vecchio sul server e vuoi:

- creare la repository GitHub
- salvare le credenziali su Bitwarden Secrets Manager
- aggiungere il workflow GitHub Actions
- fare il primo push verso `main`

### Prerequisiti

Servono questi comandi già installati e configurati:

- `git`
- `gh`
- `bws`
- `jq`

Login richiesti:

```bash
gh auth login
```

Il token di Bitwarden Secrets Manager lo puoi esportare così:

```bash
export BWS_ACCESS_TOKEN="inserisci-qui-il-token"
```

### 1. Entra nella cartella del progetto

```bash
cd /percorso/progetto
```

### 2. Inizializza Git e prepara `main`

```bash
git init
git branch -M main
```

### 3. Crea il workflow di deploy

```bash
mkdir -p .github/workflows
```

```bash
cat > .github/workflows/deploy.yml <<'YAML'
name: 🚀 Deploy

on:
  push:
    branches:
      - main

jobs:
  web-deploy:
    name: 🎯 Deploy
    runs-on: ubuntu-latest
    env:
      BWS_ACCESS_TOKEN: ${{ secrets.BWS_ACCESS_TOKEN }}
      BWS_PROJECT_ID: ${{ secrets.BWS_PROJECT_ID }}
    steps:

      - name: 🚚 Get latest code
        uses: actions/checkout@v4

      - name: 📦 Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '18'

      - name: 📦 Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          tools: composer

      - name: 📦 Install dependencies
        run: |
          sudo apt-get update
          sudo apt-get install -y jq unzip

      - name: 📦 Install Bitwarden Secrets Manager CLI
        run: |
          curl -Lso bws.zip https://github.com/bitwarden/sdk-sm/releases/download/bws-v2.0.0/bws-x86_64-unknown-linux-gnu-2.0.0.zip
          unzip -o bws.zip
          chmod +x bws
          sudo mv bws /usr/local/bin/bws
          bws --version

      - name: 🔐 Load secrets + generate .env
        shell: bash
        run: |
          set -euo pipefail

          : > .env

          bws secret list "$BWS_PROJECT_ID" --output json \
            | jq -c '.[] | select(.key != null and .value != null)' \
            | while read -r item; do
                key="$(echo "$item" | jq -r '.key')"
                value="$(echo "$item" | jq -r '.value')"

                echo "::add-mask::$value"

                printf '%s=%s\n' "$key" "$value" >> .env
                printf '%s=%s\n' "$key" "$value" >> "$GITHUB_ENV"
              done

          cat >> .env <<EOF

          APP_DEBUG=false
          APP_DOMAIN=${{ vars.APP_DOMAIN }}
          APP_URL=https://${{ vars.APP_DOMAIN }}

          ASSETS_VERSION=${{ vars.ASSETS_VERSION }}

          DB_CONNECTION_LOG=false

          EOF

      - name: Normalize FTP path
        shell: bash
        run: |
          set -euo pipefail
          path="${FTP_REMOTE_PATH:-}"
          [ -n "$path" ] || { echo "Missing FTP_REMOTE_PATH"; exit 1; }
          [[ "$path" != */ ]] && path="${path}/"
          echo "FTP_REMOTE_PATH=$path" >> "$GITHUB_ENV"

      - name: 📦 Installa Pacchetti
        run: composer install --no-dev --optimize-autoloader --no-scripts

      - name: 📂 Sincronizza file FTP
        uses: SamKirkland/FTP-Deploy-Action@v4.3.4
        with:
          server: ${{ env.FTP_HOST }}
          username: ${{ env.FTP_USER }}
          password: ${{ env.FTP_PASSWORD }}
          port: ${{ env.FTP_PORT }}
          server-dir: ${{ env.FTP_REMOTE_PATH }}
          local-dir: ./
          exclude: |
            **/.git*
            **/.git*/**
            **/.github*
            **/.github/**
            **/.vscode/**
            **/composer.json
            **/composer.lock
            **/package.json
            **/package-lock.json
            *.md

      - name: 🗄️ Update App
        shell: bash
        run: |
          set -euo pipefail

          curl -fsS --retry 3 --retry-all-errors \
            -X POST "https://${{ vars.APP_DOMAIN }}/api/app/update/" \
            -H "Authorization: Bearer $GITHUB_API_TOKEN" \
            -H "Content-Type: application/json" \
            -d "{\"release_id\":\"$GITHUB_SHA\",\"source\":\"github\"}"
YAML
```

### 4. Imposta le variabili locali del progetto

Esempio:

```bash
export REPO_NAME="nome-repository"
export APP_DOMAIN="example.com"
export ASSETS_VERSION="0.0"
```

### 5. Crea o recupera il project Bitwarden

```bash
export BWS_PROJECT_ID="$(
  bws project list --output json \
    | jq -r --arg name "$APP_DOMAIN" 'first(.[] | select(.name == $name) | .id) // empty'
)"
```

Se non esiste ancora:

```bash
export BWS_PROJECT_ID="$(
  bws project create "$APP_DOMAIN" --output json \
    | jq -r '.id'
)"
```

Verifica:

```bash
printf '%s\n' "$BWS_PROJECT_ID"
```

### 6. Inserisci i secrets del progetto su Bitwarden

In genere non serve crearli manualmente: `php forge provision` chiede i valori mancanti al primo avvio (DB, FTP, utente admin) e li sincronizza in automatico nel project Bitwarden.

Comandi manuali equivalenti, per riferimento:

```bash
bws secret create DB_HOST "" "$BWS_PROJECT_ID" --output json
bws secret create DB_USER "" "$BWS_PROJECT_ID" --output json
bws secret create DB_PASSWORD "" "$BWS_PROJECT_ID" --output json
bws secret create DB_NAME "" "$BWS_PROJECT_ID" --output json
bws secret create FTP_HOST "" "$BWS_PROJECT_ID" --output json
bws secret create FTP_USER "" "$BWS_PROJECT_ID" --output json
bws secret create FTP_PASSWORD "" "$BWS_PROJECT_ID" --output json
bws secret create FTP_PORT "21" "$BWS_PROJECT_ID" --output json
bws secret create FTP_REMOTE_PATH "/public_html/" "$BWS_PROJECT_ID" --output json
bws secret create USER_USERNAME "admin" "$BWS_PROJECT_ID" --output json
bws secret create USER_PASSWORD "admin" "$BWS_PROJECT_ID" --output json
```

Se un secret esiste già e vuoi aggiornarlo:

```bash
export SECRET_ID="$(
  bws secret list "$BWS_PROJECT_ID" --output json \
    | jq -r '.[] | select(.key == "FTP_PASSWORD") | .id'
)"

bws secret edit --value "nuova-password" "$SECRET_ID" --output json
```

Esempio specifico per aggiornare `USER_PASSWORD`:

```bash
export SECRET_ID="$(
  bws secret list "$BWS_PROJECT_ID" --output json \
    | jq -r '.[] | select(.key == "USER_PASSWORD") | .id'
)"

bws secret edit --value "nuova-password-admin" "$SECRET_ID" --output json
```

Nota:

- il workflow GitHub Actions carica tutti i secret presenti nel project Bitwarden dentro `.env` e `$GITHUB_ENV` (i nomi `FTP_*` e `BWS_*` sono comunque mascherati e non finiscono nel `.env` deployato)
- `php forge provision` sincronizza automaticamente: `APP_KEY`, `DB_HOSTNAME`/`DB_HOST`, `DB_USERNAME`/`DB_USER`, `DB_PASSWORD`, `DB_DATABASE`/`DB_NAME`, `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`, `FTP_PORT`, `FTP_REMOTE_PATH`, `USER_USERNAME`, `USER_PASSWORD`
- secret extra (es. `GITHUB_API_TOKEN`) vanno aggiunti manualmente con `bws secret create` o `gh secret set`

### 7. Crea la repository GitHub

Repository privata:

```bash
gh repo create "$REPO_NAME" --private --source=. --remote=origin
```

Repository pubblica:

```bash
gh repo create "$REPO_NAME" --public --source=. --remote=origin
```

### 8. Salva secrets e variables su GitHub

Ricava prima il nome completo della repo:

```bash
export REPO_FULL_NAME="$(gh repo view "$REPO_NAME" --json nameWithOwner --jq '.nameWithOwner')"
```

Secrets richiesti dal workflow:

| Secret | Origine | Cosa è |
|---|---|---|
| `BWS_ACCESS_TOKEN` | Bitwarden Secrets Manager | Access token per leggere il project Bitwarden |
| `BWS_PROJECT_ID` | Bitwarden Secrets Manager | UUID del project Bitwarden del sito |
| `GITHUB_API_TOKEN` | App target (vedi sotto) | **Bearer del sito**, non un PAT GitHub. Usato da `Authorization: Bearer ...` nello step `🗄️ Update App` per chiamare `/api/app/update/` sull'host di produzione. Deve coincidere con il valore restituito da `Wonder\App\Credentials::appToken()` sull'app target. |

```bash
gh secret set BWS_ACCESS_TOKEN --repo "$REPO_FULL_NAME" --body "$BWS_ACCESS_TOKEN"
gh secret set BWS_PROJECT_ID --repo "$REPO_FULL_NAME" --body "$BWS_PROJECT_ID"
gh secret set GITHUB_API_TOKEN --repo "$REPO_FULL_NAME" --body "<token-applicativo-del-sito>"
```

{% hint style="warning" %}
Il nome `GITHUB_API_TOKEN` è storico ed è fuorviante: **non è** un GitHub Personal Access Token. È il bearer applicativo che il workflow invia al tuo sito su `${APP_DOMAIN}/api/app/update/` per autorizzare la chiamata di update post-deploy. Se manca, lo step `🗄️ Update App` fallisce con `GITHUB_API_TOKEN: unbound variable`.
{% endhint %}

Variables:

```bash
gh variable set APP_DOMAIN --repo "$REPO_FULL_NAME" --body "$APP_DOMAIN"
gh variable set ASSETS_VERSION --repo "$REPO_FULL_NAME" --body "$ASSETS_VERSION"
```

### 9. Primo upload verso GitHub

```bash
git add .
git commit -m "Initial import"
git push -u origin main
```

Da questo momento ogni push su `main` attiva il workflow di deploy.

### 10. Verifica con un dry run prima del primo deploy reale

Se vuoi controllare prima cosa toccherà il deploy FTP, nel workflow puoi aggiungere temporaneamente:

```yaml
dry-run: true
```

e poi fare un push di test.

### Cartella `/images/` già presente sul server

Sul primo deploy, con `SamKirkland/FTP-Deploy-Action@v4.3.4` e configurazione standard:

- non viene fatta una pulizia totale del server
- `dangerous-clean-slate` di default è `false`
- i file già presenti sul server in genere restano dov’è
- se però il repository contiene file con lo stesso path e nome, quei file possono essere sovrascritti

Quindi:

- se `/images/` contiene upload o file gestiti solo dal server, conviene escluderla dal deploy
- se `/images/` deve essere sincronizzata da Git, allora non va esclusa

Esempio per lasciare intatta `/images/` sul server:

```yaml
exclude: |
  **/.git*
  **/.git*/**
  **/.github*
  **/.github/**
  **/.vscode/**
  **/composer.json
  **/composer.lock
  **/package.json
  **/package-lock.json
  images/**
  *.md
```

Attenzione: se escludi `images/**`, i file dentro `images/` non verranno più aggiornati dal deploy GitHub Actions.

## `composer.json` consigliato per `wonder-image/new-site`

Questo è il formato consigliato.

```json
{
    "name": "wonder-image/new-site",
    "type": "project",
    "description": "The skeleton application for the Wonder Image framework.",
    "keywords": [
        "wonder-image",
        "framework"
    ],
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "wonder-image/app": "dev-main"
    },
    "minimum-stability": "dev",
    "prefer-stable": true,
    "autoload": {
        "psr-4": {
            "App\\": "custom/class/"
        }
    },
    "scripts": {
        "post-install-cmd": [
            "@composer dump-autoload",
            "php forge config",
            "php forge update --local"
        ],
        "post-update-cmd": [
            "@composer dump-autoload",
            "php forge config",
            "php forge update --local"
        ]
    }
}
```

## Perché così

In locale va bene:

- `php forge config`
- `php forge update --local`

perché vuoi materializzare:

- `handler/index.php`
- pulizia path legacy
- file locali di bootstrap

`php forge provision` invece lo tieni manuale.

È meglio così perché:

- non lo lanci a ogni `composer install`
- non dipendi da `gh` e `bws` sempre disponibili

## Deploy GitHub Actions consigliato

Su GitHub Actions il punto corretto è questo:

- `composer install` con `--no-scripts`
- `php forge update`
- FTP deploy
- chiamata HTTP finale a `/api/app/update/`

### Workflow

```yaml
name: 🚀 Deploy

on:
  push:
    branches:
      - main

jobs:
  web-deploy:
    name: 🎯 Deploy
    runs-on: ubuntu-latest
    env:
      BWS_ACCESS_TOKEN: ${{ secrets.BWS_ACCESS_TOKEN }}
      BWS_PROJECT_ID: ${{ secrets.BWS_PROJECT_ID }}
      GITHUB_API_TOKEN: ${{ secrets.GITHUB_API_TOKEN }}
    steps:
      - name: 🚚 Get latest code
        uses: actions/checkout@v4

      - name: 📦 Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          tools: composer

      - name: 📦 Install dependencies
        run: |
          sudo apt-get update
          sudo apt-get install -y jq unzip

      - name: 📦 Install Bitwarden Secrets Manager CLI
        run: |
          curl -Lso bws.zip https://github.com/bitwarden/sdk-sm/releases/download/bws-v2.0.0/bws-x86_64-unknown-linux-gnu-2.0.0.zip
          unzip -o bws.zip
          chmod +x bws
          sudo mv bws /usr/local/bin/bws
          bws --version

      - name: 🔐 Load secrets + generate .env
        shell: bash
        run: |
          set -euo pipefail

          : > .env

          bws secret list "$BWS_PROJECT_ID" \
            | jq -c '.[] | select(.key != null and .value != null)' \
            | while read -r item; do
                key="$(echo "$item" | jq -r '.key')"
                value="$(echo "$item" | jq -r '.value')"

                echo "::add-mask::$value"

                printf '%s=%s\n' "$key" "$value" >> .env
                printf '%s=%s\n' "$key" "$value" >> "$GITHUB_ENV"
              done

          cat >> .env <<EOF

          APP_DEBUG=false
          APP_DOMAIN=${{ vars.APP_DOMAIN }}
          APP_URL=https://${{ vars.APP_DOMAIN }}

          ASSETS_VERSION=${{ vars.ASSETS_VERSION }}

          DB_CONNECTION_LOG=false

          EOF

      - name: Normalize FTP path
        shell: bash
        run: |
          set -euo pipefail
          path="${FTP_REMOTE_PATH:-}"
          [ -n "$path" ] || { echo "Missing FTP_REMOTE_PATH"; exit 1; }
          [[ "$path" != */ ]] && path="${path}/"
          echo "FTP_REMOTE_PATH=$path" >> "$GITHUB_ENV"

      - name: 📦 Composer install
        run: composer install --no-dev --optimize-autoloader --no-scripts

      - name: 🧱 Forge update
        run: php forge update

      - name: 📂 Sincronizza file FTP
        uses: SamKirkland/FTP-Deploy-Action@v4.3.4
        with:
          server: ${{ env.FTP_HOST }}
          username: ${{ env.FTP_USER }}
          password: ${{ env.FTP_PASSWORD }}
          port: ${{ env.FTP_PORT }}
          server-dir: ${{ env.FTP_REMOTE_PATH }}
          local-dir: ./
          exclude: |
            **/.git*
            **/.git*/**
            **/.github*
            **/.github/**
            **/.vscode/**
            **/composer.json
            **/composer.lock
            **/package.json
            **/package-lock.json
            *.md

      - name: 🗄️ Update App
        shell: bash
        run: |
          set -euo pipefail

          curl -fsS --retry 3 --retry-all-errors \
            -X POST "https://${{ vars.APP_DOMAIN }}/api/app/update/" \
            -H "Authorization: Bearer $GITHUB_API_TOKEN" \
            -H "Content-Type: application/json" \
            -d "{\"release_id\":\"$GITHUB_SHA\",\"source\":\"github\"}"
```

## Perché `--no-scripts`

In GitHub Actions non vuoi che Composer lanci:

- `php forge update --local`

perché:

- `config` in CI non serve
- `update --local` non deve girare in CI

Quindi:

```bash
composer install --no-dev --optimize-autoloader --no-scripts
```

poi chiami tu i comandi giusti.

## Punto da tenere presente

`php forge config` in CI non va proprio eseguito.

È voluto, perché in GitHub Actions:

- `.env` viene già generato dal workflow
- `composer.json` è già corretto
- `package.json` è già presente
- il provisioning GitHub/Bitwarden non serve
- `npm install wonder-image` non deve partire

## Nota importante sugli asset

Se il progetto continua a servire asset da:

```text
/node_modules/wonder-image/
```

e in GitHub Actions non esegui `npm install wonder-image`, quella cartella nel deploy **non esisterà**.

Quindi hai solo tre strade:

1. tieni `npm install wonder-image` nel deploy
2. committi o pubblichi gli asset in altro modo
3. smetti di leggere asset runtime da `node_modules`

Ad oggi il codice del package usa ancora `node_modules/wonder-image`, quindi questo punto va tenuto sotto controllo.

## Errori comuni

### `php forge config` non configura GitHub o Bitwarden

È corretto.

Adesso quella parte sta in:

```bash
php forge provision
```

### In GitHub Actions stai ancora lanciando `php forge config`

Non farlo.

In CI devi lanciare solo:

```bash
php forge update
```

### In locale manca `handler/index.php`

Hai eseguito solo:

```bash
php forge config
```

Ti manca:

```bash
php forge update --local
```

### In GitHub Actions partono i comandi Composer locali

Stai usando `composer install` senza:

```bash
--no-scripts
```

### L’update HTTP va, ma route e handler non funzionano come in locale

Il deploy server-side esegue:

```bash
php forge update
```

ma non:

```bash
php forge update --local
```

Questa differenza è intenzionale.

### Utente `@github` non viene creato automaticamente

A partire dalla v2.1.x, `app/build/row/user.php` crea solo due utenti di sistema:

- l'admin (definito da `USER_USERNAME` / `USER_PASSWORD` del `.env`)
- `@system` con authority `api_internal_user` — id=1 in `api_users`, è il token che `Wonder\App\Credentials::appToken()` restituisce e che il deploy GitHub Action invia come `Authorization: Bearer ...`

L'utente `@github` non viene più creato a ogni `forge update` perché:

- girava anche in CI (paradosso: l'azione che esegue il deploy creava se stessa l'utente che la autorizza)
- il suo token non era mai allineato con il `GITHUB_API_TOKEN` salvato nei GitHub Secrets

Se vuoi un utente API dedicato per il deploy CI:

1. Backend → Utenti API → crea utente con username `@github`, authority `api_public_access`, area `api`, allowed_domains = il tuo dominio
2. Copia il token generato dal record
3. `gh secret set GITHUB_API_TOKEN --repo "$REPO_FULL_NAME" --body "<token>"`

Se NON crei `@github`, il deploy continua a funzionare usando il token di `@system` (l'endpoint `/api/app/update/` accetta sia `api_internal_user` sia `api_public_access`).

### Variabili DB con due nomi storici

Il framework storicamente legge i nomi "Laravel-style":

- `DB_HOSTNAME`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_DATABASE`

I `.env` più recenti generati da Bitwarden / dal deploy Action / da convenzioni di hosting più diffuse usano invece:

- `DB_HOST`
- `DB_USER`
- `DB_PASSWORD`
- `DB_NAME`

**Da `wonder-image/app` v2.1.x non serve scegliere**: la classe `Wonder\App\EnvCompat` viene chiamata in automatico subito dopo `Dotenv::safeLoad()` (sia nel flow web sia nei comandi `forge`) e copia i valori nei due set in entrambe le direzioni. Comportamento:

- se sono presenti solo i nomi nuovi → vengono propagati nei nomi vecchi
- se sono presenti solo i nomi vecchi → vengono propagati nei nomi nuovi
- se sono presenti entrambi e divergenti → **vince il nuovo**, sovrascrive il vecchio

`DB_PASSWORD` ha lo stesso nome in entrambi i set, nessun alias necessario.

Quindi nel `.env` puoi scrivere indifferentemente uno dei due set; il framework si arrangia. Quando un giorno tutti i progetti useranno solo i nomi nuovi, basterà rimuovere `EnvCompat::apply()` dai bootstrap.

### Lo step `🗄️ Update App` fallisce con `GITHUB_API_TOKEN: unbound variable`

Causa: nei GitHub Secrets della repo manca `GITHUB_API_TOKEN` **oppure** nel `deploy.yml` manca la riga `GITHUB_API_TOKEN: ${{ secrets.GITHUB_API_TOKEN }}` nell'`env:` del job.

Fix:

1. Verifica che la riga sia presente nell'`env:` del workflow (vedi blocco YAML "Workflow" in cima).
2. Verifica che il secret esista:
   ```bash
   gh secret list --repo "$REPO_FULL_NAME" | grep GITHUB_API_TOKEN
   ```
3. Se manca, aggiungilo (il valore deve corrispondere a `Wonder\App\Credentials::appToken()` sull'app target):
   ```bash
   gh secret set GITHUB_API_TOKEN --repo "$REPO_FULL_NAME" --body "<token-applicativo>"
   ```

Nel workflow lo step controlla in modo difensivo che il token sia popolato e produce un messaggio `::error::` chiaro se manca.

## Best practice

- `php forge config` per configurare il progetto
- `php forge provision` solo quando devi configurare GitHub e Bitwarden
- `php forge update --local` solo in locale
- `php forge db:init` per il provisioning esplicito del database locale
- `php forge update` in CI o in contesti non locali
- `php forge start` per l’avvio locale rapido
- in CI usa sempre `composer install --no-scripts`
- non mettere provisioning GitHub/Bitwarden nei Composer scripts
- non affidarti a script impliciti quando puoi chiamare i comandi in modo esplicito
