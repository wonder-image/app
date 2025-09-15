# App 1.4.5

{% hint style="info" %}
Questo progetto è stato studiato per essere utilizzato con [Visual Studio Code](altro/estensioni-e-app-consigliate.md#visual-studio-code). Per il corretto funzionamento è consigliata l'installazione su VS Code  dell'estensione [SFTP](altro/estensioni-e-app-consigliate.md#sftp).
{% endhint %}

Per l'installazione del progetto [`wonder-image/new-site`](https://github.com/wonder-image/new-site) è necessaria l'installazione di [Composer](https://getcomposer.org/). Sostituire `project-name` con il nome del dominio.

```
composer create-project wonder-image/new-site project-name
```

***

## Configurazione

Una volta effettuata l'installazione è necessario proseguire con la configurazione come segue.

### .env

Personalizza il file .env con i dettagli del progetto

<table><thead><tr><th width="193">Variabile</th><th>Valore</th></tr></thead><tbody><tr><td><code>APP_DEBUG</code></td><td>Modalità applicazione. Se è in fase di sviluppo digitare <code>true</code> altrimenti <code>false</code></td></tr><tr><td><code>APP_URL</code></td><td>Url del sito "Si raccomanda l'utilizzo di <em>https://www.dominio.it</em>"</td></tr><tr><td><code>ASSETS_VERSION</code></td><td>Nome della cartella in /assets/0.0/</td></tr></tbody></table>

Connessione al database

<table><thead><tr><th width="193">Variabile</th><th>Valore</th></tr></thead><tbody><tr><td><code>DB_HOSTNAME</code></td><td>Indirizzo IP del database oppure utilizzare <code>localhost</code></td></tr><tr><td><code>DB_USERNAME</code></td><td>Username database</td></tr><tr><td><code>DB_PASSWORD</code></td><td>Password database</td></tr><tr><td><code>DB_DATABASE</code></td><td>Nome del database. È possibile inserire più database utilizzando il formato "key1: nome_database, key2: nome_database2" il valore key viene utilizzato per indicare successivamente il database associato alla tabella</td></tr></tbody></table>

Credenziali utente default accesso al Backend

| Variabile       | Valore   |
| --------------- | -------- |
| `USER_NAME`     | Nome     |
| `USER_SURNAME`  | Cognome  |
| `USER_EMAIL`    | Email    |
| `USER_USERNAME` | Username |
| `USER_PASSWORD` | Password |

È possibile utilizzare un account email personale per l'invio delle email da configurare nel bakcend. I campi devono essere compilati con le impostazioni del server in uscita.&#x20;

### Installazione

Per iniziare a utilizzare il framework ( una volta concluso l'upload di tutti i file tramite FTP ) bisogna  andare su un browser a scelta e digitare `dominio.it`.&#x20;

### Aggiornamenti

Tutte le volte che vengono create/modificate le tabelle per far si che la modifica sia effettiva bisogna andare su `dominio.it/update/`.

### Personalizza

Per continuare la configurazione andare al link `dominio.it/backend/` accedendo con le credenziali indicate nel file .env



<table data-view="cards"><thead><tr><th></th><th></th><th></th><th data-hidden data-card-target data-type="content-ref"></th></tr></thead><tbody><tr><td>Frontend</td><td></td><td></td><td></td></tr><tr><td>Backend</td><td></td><td></td><td><a href="broken-reference">Broken link</a></td></tr><tr><td>Specifiche</td><td></td><td></td><td><a href="broken-reference">Broken link</a></td></tr></tbody></table>

