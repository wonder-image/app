---
icon: house
---

# Cos'è wonder-image/app

`wonder-image/app` è il **core del framework** Wonder: una libreria PHP
distribuita come pacchetto Composer (`wonder-image/app`). Non è un sito: è il
motore che un **sito** installa sotto `vendor/wonder-image/app` e usa per
costruire frontend, backend, API, CRUD, permessi e gestione utenti.

{% hint style="info" %}
**Per chi è questa documentazione.** Per chi sviluppa o estende il framework e
per chi costruisce un sito sopra di esso. Se stai lavorando su un progetto
scaffoldato da [`wonder-image/new-site`](https://github.com/wonder-image/new-site),
quasi tutto ciò che ti serve è in **Concetti fondamentali**.

**Percorso di lettura consigliato:** Introduzione → Concetti fondamentali (in
ordine) → Piattaforma come reference quando serve.
{% endhint %}

## Le due parti del sistema

| Termine | Cos'è | Dove vive |
|---|---|---|
| **framework** (`wonder-image/app`) | Il core: Model, Resource, Form, Tabelle, Permessi, Moduli | repo `wonder-image/app`; in un sito sta in `vendor/wonder-image/app/` |
| **site** (scaffold: `new-site`, `immobili-site`, `rsvp-site`) | Il progetto reale: pagine, contenuti, configurazioni | repo del progetto; usa il framework sotto `vendor/` |
| **lib** (`wonder-image/lib`) | Il design system JS/CSS (classi `.wi-*`) | pacchetto npm `wonder-image` |
| **module** (`wonder-image/<slug>`) | Pacchetto opzionale che aggiunge Model/Resource/route | scoperto via Composer, abilitato dal sito |

## Le 7 aree fondamentali

Il framework si capisce seguendo un'unica catena:
**Modulo → Risorsa → Form → Tabella → Database → Permessi → Componenti**.

| Area | Cosa fa | Pagina |
|---|---|---|
| **Moduli** | Pacchetti che estendono il framework | [Moduli](concetti/moduli/README.md) |
| **Risorse e Model** | CRUD su una tabella: form, lista, API | [Risorse e Model](concetti/risorse/README.md) |
| **Creazione Form** | Dichiarare input con `FormField` | [Form](concetti/form/README.md) |
| **Render tabelle** | Liste backend con `TableColumn` | [Render delle tabelle](concetti/tabelle/README.md) |
| **Database** | `dataSchema()` + `tableSchema()`, migrazioni | [Model e Database](concetti/risorse/database.md) |
| **Utenti e permessi** | Authority, ruoli, gestione utenti | [Utenti e Permessi](concetti/utenti/README.md) |
| **Componenti** | Card, Container, Alert, Button, Badge, Dropdown | [Componenti UI](concetti/componenti/README.md) |

Per vedere come si incastrano (e i 4 flussi tipici: creazione, modifica,
visualizzazione lista, accesso negato) parti dalla
[Mappa end-to-end](concetti/mappa-end-to-end.md).

## Avvio rapido

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


Oltre allo scaffold generico `wonder-image/new-site` puoi partire da uno
scaffold **verticale già preconfigurato** — stesso flusso, contenuti di settore
già pronti:

- [`wonder-image/immobili-site`](https://github.com/wonder-image/immobili-site) — sito immobiliare
- [`wonder-image/rsvp-site`](https://github.com/wonder-image/rsvp-site) — sito eventi / RSVP

Dettagli in [Avvio rapido](introduzione/avvio-rapido.md) e
[Installazione e Deploy](piattaforma/installazione-e-deploy.md).

## Bootstrap (entrypoint)

Il punto di ingresso del pacchetto è
[`wonder-image.php`](https://github.com/wonder-image/app): risolve `ROOT`,
carica l'autoloader del sito e poi `function`, `config`, `service`,
`middleware` e infine `bootstrap/backend.php` o `bootstrap/frontend.php`.
Spiegato in [Architettura in 5 minuti](introduzione/architettura.md).

{% hint style="warning" %}
**Regola di manutenzione.** Quando una modifica cambia architettura,
bootstrap/runtime, layout strutturale, convenzioni per sviluppatori o punti di
estensione, il lavoro non è completo finché non vengono aggiornati **insieme**:
la documentazione pertinente sotto `docs/app/*`, `AGENTS.md` e la skill AI
rilevante. Non modificare `.agents/` a mano: aggiorna la skill alla sorgente e
poi risincronizza con `npx skills`.
{% endhint %}
