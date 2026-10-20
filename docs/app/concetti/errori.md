---
icon: triangle-exclamation
---

# Errori ripetuti

Un servizio esterno che smette di rispondere sbaglia cento volte di seguito.
`error_reports` tiene **una riga per problema**, con un contatore, e manda
l'email una volta sola.

Qui stanno i **guasti tecnici**, quelli che deve vedere chi sviluppa. Quello che
riguarda chi usa il sito — un ordine da controllare, una spedizione ferma — non
è un errore ma una notifica, e si racconta con parole sue, altrove.

## Segnalare

```php
use Wonder\App\Support\Errors\ErrorReporter;

ErrorReporter::report('fatture-in-cloud', 'invoice.send', $exception, [
    'invoice' => $numero,
]);
```

Al posto dell'eccezione si può passare una stringa, quando l'errore non ne ha
una.

L'impronta nasce da servizio, azione, classe dell'eccezione, file e riga: lo
stesso errore resta la stessa riga, anche a distanza di giorni. `report()`
ritorna `true` solo quando è partita un'email.

| Situazione | Cosa succede |
|---|---|
| Prima volta | riga nuova, email |
| Di nuovo | contatore e data aggiornati, niente email |
| Segnato risolto | la riga si chiude |
| Torna dopo la chiusura | riga riaperta, contatore da capo, email |

## Chi riceve l'email

Gli indirizzi li conosce il sito, non il core:

```php
ErrorReporter::recipientsUsing(static fn (): array => ['dev@esempio.it']);
```

Senza risolutore la riga si scrive lo stesso e nessuno riceve niente. Gli
indirizzi che non sono indirizzi vengono scartati.

## Guardare e chiudere

La pagina **Dev → Log e diagnostica → Errori** (`admin`) elenca gli errori aperti con servizio,
azione, occorrenze, prima e ultima volta. Da lì si segna risolto.
Da codice:

```php
ErrorReporter::open();   // gli aperti, dal più recente
ErrorReporter::resolve($id, $userId);
```

La tabella non si sincronizza tra ambienti: gli errori di un ambiente sono i
suoi.

## Tabelle DataTables (`list.table`)

Un errore SQL delle liste backend (`api.backend.list.table`, `SSP::complex`)
non passa da `error_reports`. La risposta al browser è sempre la stessa,
`{"error":"Errore nel caricamento della tabella."}`, senza query né messaggio
del database. Il dettaglio finisce in `storage/logs/error.log` tramite
`Logger::log()`, con servizio `backend` e azione `list.table`. Solo con il
debug attivo (`Debug::enabled()`: `APP_DEBUG`, o in locale) la risposta
aggiunge la chiave `detail` con il messaggio.

Prima di qualsiasi query, COUNT compreso, `ListProvider::fetch()` verifica la
firma dei frammenti della config (`query`, `query_filter`, `query_custom`,
`search_columns`, `select`, `group_by`). Ogni firma è legata alla chiave
canonica della tabella, allo schema (`custom[schema]`) e al campo
(`ConfigCodec::context()`). Se un frammento è manomesso, spostato su un altro
campo o riusato con un'altra `config[table]` o un altro schema, la risposta è
200 con `{"error":"Invalid table configuration signature.", "data":[]}` e
il `draw` della richiesta: nessuna query parte.
