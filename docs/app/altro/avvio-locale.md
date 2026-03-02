# Avvio Locale (PHP 8.4)

Guida rapida per creare e avviare un progetto Wonder in locale con DB separato.

## 1) Prerequisiti

```bash
php -v
composer -V
mysql --version
```

## 2) Crea progetto test parallelo

```bash
cd /Users/andreamarinoni/Desktop/PROGETTI/template
cp -R new-site new-site-php84-sf8
cd new-site-php84-sf8
```

## 3) Collega il pacchetto `app` locale (worktree lab)

```bash
composer config repositories.wonder-image-app path ../app-php84-sf8
composer require wonder-image/app:"dev-codex/php84-sf8-lab as 1.5.x-dev" --with-all-dependencies
```

## 4) Configura `.env`

Imposta almeno:

```env
APP_DEBUG=true
APP_URL=http://127.0.0.1:8088
APP_KEY=una_chiave_random

DB_HOSTNAME=127.0.0.1:3307
DB_USERNAME=sf8_test_user
DB_PASSWORD=...
DB_DATABASE=main:myapp_sf8_test
```

## 5) Avvio semplice con Forge

Dal root del progetto (`new-site-php84-sf8`):

```bash
php forge start
```

Il comando:
- avvia server PHP locale
- gestisce route directory (`/backend/`)
- abilita `/update/` anche in sviluppo locale
- completa automaticamente `.env` se trova campi vuoti (`APP_URL`, blocco DB, `USER_PASSWORD`)
- fa un check DB iniziale

Puoi anche passare i default DB da riga comando (usati solo se i campi `.env` sono vuoti):

```bash
php forge start --db-hostname=127.0.0.1:3307 --db-username=sf8_test_user --db-password=secret --db-database=main:myapp_sf8_test
```

## 6) URL utili

- Home: `http://127.0.0.1:8088/`
- Backend: `http://127.0.0.1:8088/backend/`
- Login backend: `http://127.0.0.1:8088/backend/account/login/`
- Update (safe, senza side-effect): `http://127.0.0.1:8088/update/`
- Esegui update: `http://127.0.0.1:8088/update/run/`

## 7) Vedere il DB in modo chiaro (CLI)

Connessione:

```bash
mysql -h 127.0.0.1 -P 3307 -u sf8_test_user -p myapp_sf8_test
```

Comandi utili:

```sql
SHOW TABLES;
DESCRIBE security;
SELECT id, mail_host, stripe_test FROM security LIMIT 20;
```

## Nota su `/update/`

Se avvii con `php -S` senza router custom, `/update/` potrebbe non funzionare.
Con `php forge start` la route viene gestita automaticamente.
