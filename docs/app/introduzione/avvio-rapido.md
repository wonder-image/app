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

```bash
composer create-project wonder-image/new-site:dev-main nome-progetto
cd nome-progetto
```

Il suffisso `:dev-main` forza l'ultimo commit del branch `main` di
`wonder-image/new-site`. Se vedi una versione vecchia: `composer clear-cache` e
ripeti.

## 2. Configura il progetto

```bash
php forge config
```

Completa `.env`, normalizza `APP_DOMAIN` / `APP_URL` / `ASSETS_VERSION`,
aggiorna `composer.json`, crea `package.json` se manca e in locale esegue
`npm install wonder-image`.

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
| `php forge provision` | solo locale | GitHub + Bitwarden + dev-shared |
| `php forge update --local` | locale | genera `handler/`, applica tabelle, task CLI |
| `php forge update` | CI / server | applica tabelle e update (no task CLI) |
| `php forge db:init` | locale | crea DB e utente applicativo |
| `php forge build` | CI, pre-deploy | genera file statici senza DB |
| `php forge start` | locale | avvia il sito (Herd o `php -S`) |
| `php forge make:model` / `make:resource` | sviluppo | scaffolding di Model/Resource |
| `php forge export` / `import` | multi-ambiente | sincronizza dati condivisi via JSON |
| `php forge status:modules` / `validate:module` | moduli | stato e validazione manifest |

I comandi vivono in `class/Console/Commands/*` e si lanciano dalla radice del
**sito**.

## Errori comuni

- **Manca `handler/index.php`** → hai eseguito solo `php forge config`. Lancia
  `php forge update --local`.
- **`npm WARN EBADENGINE`** → Node < 20. Aggiorna a Node 20+.
- **403 / pagina backend vuota** → utente senza authority. Vedi
  [Utenti e Permessi](../concetti/utenti/README.md).
- **Versione installata vecchia** → `composer clear-cache` e riusa `:dev-main`.

## Checklist

- [ ] `php --version` ≥ 8.2, `node --version` ≥ 20
- [ ] progetto creato con `:dev-main`
- [ ] `php forge config` eseguito
- [ ] `php forge update --local` eseguito (esiste `handler/index.php`)
- [ ] DB inizializzato con `php forge db:init`
- [ ] sito raggiungibile e login backend ok
