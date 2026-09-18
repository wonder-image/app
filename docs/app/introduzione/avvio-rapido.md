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

## Procedura iniziale

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

### Cron sul server

Dopo il primo deploy, apri **cPanel → Processi Cron** e aggiungi un solo
cron per lo scheduler Wonder:

```sh
/usr/local/bin/php /home/h624uw5n/public_html/bin/scheduler.php
```

Sostituisci `h624uw5n` e il percorso con quelli del tuo hosting. Il binario
PHP indicato è quello dell'esempio Aruba; PHP CLI deve consentire `proc_open`.

**Frequenza: ogni minuto (`* * * * *`).** Compila i campi cPanel così:

| Minuto | Ora | Giorno del mese | Mese | Giorno della settimana |
|---|---|---|---|---|
| `*` | `*` | `*` | `*` | `*` |

`forge build` e `forge update` generano `bin/scheduler.php`: il deploy deve
eseguire il build prima del caricamento oppure l'update sul server.
Il file generato è escluso da Git con `/bin/scheduler.php`; gli altri script
personalizzati in `bin/` possono essere versionati.

Ogni minuto lo scheduler controlla cosa è dovuto: le frequenze delle singole
attività si gestiscono nel backend. Non aggiungere un cron cPanel per ogni
attività e non inserire il token `@system` nel comando CLI. Se esistono vecchi
cron dedicati, sostituiscili dopo aver verificato le attività corrispondenti
nel nuovo scheduler, evitando esecuzioni duplicate.

Controlla **Backend → Attività pianificate → Riepilogo** per verificare
l'ultimo contatto ricevuto. Per definire le attività del sito e dei moduli,
vedi [Cron job da codice](../piattaforma/cron-job.md).

### Note sulla procedura iniziale

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
| `php forge schedule:run` | sito, verifica manuale | esegue le attività pianificate scadute |
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
