# Wonder Image App

Core PHP del framework Wonder, installato dai siti come dipendenza Composer.

[Documentazione](docs/app/README.md) · [Avvio rapido](docs/app/introduzione/avvio-rapido.md)

## Crea e avvia un progetto

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
