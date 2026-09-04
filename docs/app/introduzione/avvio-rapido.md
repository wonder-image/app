---
icon: rocket
---

# Avvio rapido

Questa pagina porta da zero a un sito Wonder funzionante in locale. Per la
procedura completa (deploy, GitHub Actions, Bitwarden) vedi
[Installazione e Deploy](../piattaforma/installazione-e-deploy.md).

## Prerequisiti

- **PHP 8.2+** e **Composer**
- **Node 20+** (il pacchetto npm `wonder-image` richiede Node ≥ 20)
- **MySQL / MariaDB** locale (per `php forge db:init`)
- Consigliato: **Laravel Herd** per servire il sito su `https://nome.test`

Verifica:

```bash
php --version
composer --version
node --version
```

## 1. Crea il progetto

Scegli lo **scaffold** di partenza. Sono tutti progetti che installano
`wonder-image/app` sotto `vendor/` e proseguono con lo stesso flusso `forge`;
cambia solo cosa trovi già pronto al primo avvio:

| Scaffold | Quando usarlo |
|---|---|
| [`wonder-image/new-site`](https://github.com/wonder-image/new-site) | sito **generico**: parti da vuoto |
| [`wonder-image/immobili-site`](https://github.com/wonder-image/immobili-site) | sito **immobiliare** preconfigurato (Model, Resource e pagine di settore già pronti) |
| [`wonder-image/rsvp-site`](https://github.com/wonder-image/rsvp-site) | sito **eventi / RSVP** preconfigurato (inviti e conferme di partecipazione) |

```bash
# scaffold generico (default)
composer create-project wonder-image/new-site:dev-main nome-progetto

# oppure uno scaffold verticale già preconfigurato
composer create-project wonder-image/immobili-site:dev-main nome-progetto
composer create-project wonder-image/rsvp-site:dev-main nome-progetto

cd nome-progetto
```

Il suffisso `:dev-main` forza l'ultimo commit del branch `main` dello scaffold
scelto. Se vedi una versione vecchia: `composer clear-cache` e ripeti. I passi
successivi (`forge config`, `update --local`, `db:init`, `start`) sono identici
per tutti e tre.

## 2. Configura il progetto

```bash
php forge config
```

Completa `.env`, normalizza `APP_DOMAIN` / `APP_URL` / `ASSETS_VERSION`,
aggiorna `composer.json`, crea `package.json` se manca e in locale esegue
`npm install wonder-image`.

Per scaricare nel `.env` le credenziali di sviluppo condivise dal project
Bitwarden `dev-shared` (anche nei progetti esistenti):

```bash
php forge credentials
```

Il comando chiede e salva `BWS_ACCESS_TOKEN` se manca, aggiunge solo le
chiavi assenti o vuote e non avvia il provisioning GitHub/produzione.

## 3. Genera i file locali

```bash
php forge update --local
```

Crea `handler/index.php` (front controller), applica le tabelle ed esegue i
task locali. **Senza questo passo manca `handler/index.php`** e il routing non
funziona.

## 4. Inizializza il database locale

```bash
php forge db:init \
  --admin-host=127.0.0.1 --admin-port=3306 \
  --admin-username=root --admin-password=secret
```

Crea database, utente applicativo e grant MySQL; deriva `DB_DATABASE` da
`APP_DOMAIN`. Le credenziali admin MySQL **non** vengono salvate nel `.env`.

## 5. Avvia

```bash
php forge start
```

Se Herd è disponibile pubblica il sito su `https://APP_DOMAIN.test`; altrimenti
avvia il server PHP integrato. Se il DB manca, suggerisce `php forge db:init`.

## 6. Entra nel backend

Vai su `https://nome.test/backend/` e accedi con le credenziali `USER_*` del
`.env`. Da lì configuri contenuti, utenti, CSS.

## Comandi forge essenziali

| Comando | Quando | Cosa fa |
|---|---|---|
| `php forge config` | setup iniziale, locale | completa `.env`, npm install |
| `php forge credentials` | locale, setup o recovery | scarica i default `dev-shared` da Bitwarden |
| `php forge provision` | solo locale | GitHub + Bitwarden + dev-shared |
| `php forge update --local` | locale | genera `handler/`, applica tabelle, task CLI |
| `php forge update` | CI / server | applica tabelle e update (no task CLI) |
| `php forge db:init` | locale | crea DB e utente applicativo |
| `php forge build` | CI, pre-deploy | genera file statici senza DB |
| `php forge start` | locale | avvia il sito (Herd o `php -S`) |
| `php forge make:model` / `make:resource` | sviluppo | scaffolding di Model/Resource |
| `php forge export` / `import` | multi-ambiente | sincronizza dati condivisi via JSON |
| `php forge status:modules` / `validate:module` | moduli | stato e validazione manifest |
| `php forge publish:module <slug>` | moduli | copia le view overrideabili in `custom/modules/<slug>/view` |

I comandi vivono in `class/Console/Commands/*` e si lanciano dalla radice del
**sito**.

## Errori comuni

- **Manca `handler/index.php`** → hai eseguito solo `php forge config`. Lancia
  `php forge update --local`.
- **`npm WARN EBADENGINE`** → Node < 20. Aggiorna a Node 20+.
- **Mancano le credenziali dev condivise** → esegui `php forge credentials`.
- **403 / pagina backend vuota** → utente senza authority. Vedi
  [Utenti e Permessi](../concetti/utenti/README.md).
- **Versione installata vecchia** → `composer clear-cache` e riusa `:dev-main`.

## Checklist

- [ ] `php --version` ≥ 8.2, `node --version` ≥ 20
- [ ] progetto creato con `:dev-main`
- [ ] `php forge config` eseguito
- [ ] `php forge credentials` eseguito, se usi il project Bitwarden `dev-shared`
- [ ] `php forge update --local` eseguito (esiste `handler/index.php`)
- [ ] DB inizializzato con `php forge db:init`
- [ ] sito raggiungibile e login backend ok
