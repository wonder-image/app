# Attivita pianificate e cron

Wonder usa un unico cron del server per eseguire le attivita scadute. Il menu
**Backend > Attivita pianificate**, riservato ad `admin`, contiene riepilogo,
pianificazioni e registro esecuzioni.

## Installazione nel sito

Aggiornare le dipendenze Composer (inclusa `dragonmantank/cron-expression`),
quindi eseguire dal sito:

```sh
php forge update --local
php forge schedule:run
```

`forge build` e `forge update` generano `bin/scheduler.php`. Nel cPanel
configurare una sola esecuzione al minuto (`* * * * *`), se consentita dal piano:

```sh
/usr/local/bin/php /home/h624uw5n/public_html/bin/scheduler.php
```

Percorso, utente e binario PHP dipendono dal sito. PHP CLI deve consentire
`proc_open`. All'inizio mantenere visibile l'output per verificare l'avvio;
il backend mostra l'ultimo contatto ricevuto in UTC. Non serve un token nel
comando cPanel. Il vecchio file `api/task/sitemap.php` viene rimosso dal build:
sostituire il vecchio cron dopo aver verificato la nuova generazione, senza
lasciare entrambi attivi.

## Attivita da codice

La logica applicativa resta in servizi riutilizzabili. `Api\Handler::run()`,
come in `app/http/api/backend/alert.php`, rimane il confine HTTP: autentica,
invia una risposta e termina la richiesta. Non includere un endpoint HTTP
dentro un cron. Per callback brevi usare il builder `Task`:

```php
// custom/config/tasks.php nel sito
use Wonder\App\Scheduler\{Context, Task};

return [
    Task::make('site.example', function (Context $context): array {
        $context->checkDeadline();
        $context->log("Avvio elaborazione\n");
        // Richiamare qui il servizio del sito, con timeout sulle richieste esterne.
        return ['processed' => 0];
    })
        ->named('Elaborazione del sito')
        ->schedule('*/10 * * * *')
        ->maxSeconds(120)
        ->active(),
];
```

`->parameters(callable)` valida e restituisce un array di parametri normalizzati.
`->withDefaults(['feed' => 1])` imposta quelli della pianificazione iniziale.
Senza validatore sono ammessi solo parametri vuoti. I custom sono sospesi per
default; `active()` abilita solo la pianificazione iniziale.

Per attivita complesse estendere `AbstractTask`, implementando `key()` e
`run(Context): array`. Sono sovrascrivibili `label()`, `expression()`,
`enabled()`, `timeout()`, `defaultParameters()` e `validate(array): array`.
E possibile implementare direttamente `Contracts\TaskInterface`.

Non usare `exit`, `die` o `Api\Response` nelle attivita: restituire risultati
oppure lanciare un'eccezione. Un'uscita prematura viene registrata come
interruzione. Leggere le credenziali dalla configurazione del sito, senza
inserirle nei parametri persistiti o nell'output.

## Moduli e override

Un entrypoint puo implementare il contratto opzionale
`Wonder\App\Module\Contracts\ModuleTasks` oltre a `ModuleInterface`:

```php
public static function tasks(): iterable
{
    return [new SyncFeedTask()];
}
```

Solo i moduli abilitati contribuiscono al registro. Il sito puo sostituire
un'attivita restituendo una classe con lo stesso identificativo in
`custom/config/tasks.php`. Precedenza: framework, moduli, sito. Le collisioni
tra moduli vengono rifiutate; usare chiavi come `immobili.sync`.

La registrazione e la sincronizzazione DB sono separate. Update e tick creano
soltanto le pianificazioni iniziali mancanti. Un registro di inizializzazione
impedisce di ricreare default rimossi; frequenza, parametri e stato modificati
restano intatti. Si possono aggiungere piu pianificazioni della stessa attivita.
Dal pannello si possono sospendere; i moduli non disponibili non vengono eseguiti.

## Sicurezza e API

I file CLI rifiutano l'accesso HTTP. Il tick verifica che l'utente API `@system`
esista e sia abilitato. La fiducia CLI deriva dall'accesso al server; non
richiede di copiare la chiave nelle righe cron.

Gli ingressi HTTP riusano `Handler`, `Endpoint` e Bearer token esistenti, con
`$call->requireUsername('@system')` oltre ad `api_internal_user`:

- `POST /api/task/scheduler/`, JSON `{"schedule_id": 1}`;
- `POST /api/task/sitemap/`, senza parametri.

Richiedono HTTPS e il token valido di `@system`. Restituiscono `202` e
richiedono l'esecuzione al prossimo tick, senza lavori lunghi nella richiesta
web. La precedente route sitemap GET non avvia piu il crawler. Il token di
`@system` e distinto dal campo generico `security.api_key`. Il backend usa
sessione amministrativa e token CSRF per modifiche e avvii.

## Esecuzione e metriche

Il runner riusa `Support\NamedLock`: un lock impedisce tick paralleli e uno
per identificativo attivita impedisce sovrapposizioni tra pianificazioni dello
stesso lavoro. I worker sono processi PHP separati, eseguiti sequenzialmente.
Il tick smette di avviare nuove attivita dopo 50 secondi; quella gia avviata
puo proseguire fino al proprio timeout (default 300 secondi, massimo 3600),
con 10 secondi di tolleranza per il worker.

La scelta privilegia chi non viene eseguito da piu tempo. Dopo un fermo si
esegue una volta il lavoro dovuto e si calcola la prossima scadenza futura:
nessuna raffica di recupero e nessun retry automatico. Per grandi import
usare lotti e salvare il cursore nel servizio applicativo.

I timestamp persistiti e mostrati nei log sono UTC; le espressioni cron sono
interpretate nel fuso IANA della pianificazione, inizialmente `Europe/Rome`.

Ogni esecuzione registra stato, inizio/fine, durata in millisecondi, picco
memoria PHP in byte, CPU in millisecondi quando disponibile, output e risultato.
Il picco PHP non e la memoria RSS ne il consumo complessivo dell'hosting.
La CPU dei task ordinari misura il lavoro dopo il bootstrap; il picco memoria
comprende il processo. Il crawler sitemap riporta le metriche del proprio
processo PHP. Le metriche finali dei processi uccisi possono mancare e non
vengono sostituite con zeri. I worker orfani vengono riconciliati dopo 3700
secondi soltanto se il relativo lock e libero.

Output e risultato sono limitati a 16 KiB ciascuno. Token `@system`, Bearer e
valori dei parametri dal nome sensibile vengono oscurati; i servizi devono
comunque evitare di scrivere altri segreti.

Il riepilogo offre periodi di 7, 30, 90 e 180 giorni, medie, massimi, totali e
successi per attivita. Esecuzioni aperte o saltate non entrano nelle medie;
i valori nulli sono esclusi dalle medie della metrica corrispondente.

**Conservazione unica: 180 giorni**, senza override. La pulizia elimina fino
a 1000 record per tick finche il lavoro giornaliero e concluso e non elimina
esecuzioni aperte. Nessun aggregato viene conservato oltre i 180 giorni.
Gli identificativi di inizializzazione e l'ultimo contatto sono stato operativo.

## Preconfigurati e integrazioni future

- **Sitemap**: `Tasks\SitemapTask`, attiva ogni notte alle 00:00. Riusa il
  crawler XML-Sitemaps esistente in un processo isolato e la configurazione
  gestita da Forge. La classe e estendibile. L'esito si basa sul codice di
  uscita del crawler: verificare anche il file XML nel sito reale.
- **Euribor**: `Tasks\EuriborTask`, disponibile automaticamente dove esiste
  `App\Models\Site\Euribor::sync()`, come nel sito Agliati. Pianificazione
  iniziale sospesa, ore 12:00 dal lunedi al venerdi. Riusa il servizio gia
  condiviso dall'endpoint e dal comando CLI, senza richieste durante
  l'installazione. Il modello e sostituibile in una sottoclasse.
- **Recensioni Google di tutte le sedi**: futura integrazione, senza riusare
  lo script con Place ID fisso. Partire da `society_locations.google_place_id`,
  elaborare una sede per lotto e usare identificativi recensione stabili con
  upsert. Places restituisce al massimo cinque recensioni per luogo, non
  l'archivio completo. Per i profili gestiti valutare Business Profile con
  autorizzazione OAuth e paginazione. Fonti: [Places](https://developers.google.com/maps/documentation/places/web-service/reference/rest/v1/places),
  [Business Profile](https://developers.google.com/my-business/reference/rest/v4/accounts.locations.reviews/list).
- **Orari Google di tutte le sedi**: futura integrazione sullo stesso elenco.
  Separare orari regolari, eccezioni e secondari; confrontare i dati ricevuti
  con quelli locali prima di definire la politica di aggiornamento. Non
  sovrascrivere le modifiche manuali senza una scelta esplicita del sito.
  Le credenziali Google sono distinte dal token Wonder `@system`.

## Verifica

Dal framework: `php tests/scheduler.php`. Il test DB/processi
`tests/scheduler-integration.php` richiede un sito temporaneo
`wonder-scheduler-*` con database locale di prova. Dal sito eseguire anche
`php forge update --local`, `php forge start` e verificare i permessi backend.
Su Aruba verificare `proc_open`, percorso PHP e generazione sitemap reale
prima di sostituire il cron precedente.
