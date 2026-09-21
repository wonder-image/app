---
icon: triangle-exclamation
---

# Errori ripetuti

Un servizio esterno che smette di rispondere sbaglia cento volte di seguito.
`error_reports` tiene **una riga per problema**, con un contatore, e manda
l'email una volta sola.

## Segnalare

```php
use Wonder\App\Support\Errors\ErrorReporter;

ErrorReporter::report('developer', 'fatture-in-cloud', 'invoice.send', $exception, [
    'invoice' => $numero,
]);
```

Il primo argomento è il **gruppo di destinatari**: il core non sa chi siano, li
risolve chi segnala (sotto). Al posto dell'eccezione si può passare una stringa,
quando l'errore non ne ha una.

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

Il core non ha un'idea di "sviluppatore" o "commerciante": glielo dice chi
segnala.

```php
ErrorReporter::recipientsUsing(static fn (string $audience): array => $audience === 'developer'
    ? ['dev@esempio.it']
    : ['negozio@esempio.it']);
```

Senza risolutore la riga si scrive lo stesso e nessuno riceve niente. Gli
indirizzi che non sono indirizzi vengono scartati.

## Guardare e chiudere

La pagina **Set Up → Errori** (`admin`) elenca gli errori aperti con servizio,
azione, destinatario, occorrenze, prima e ultima volta. Da lì si segna risolto.
Da codice:

```php
ErrorReporter::open();            // tutti gli aperti
ErrorReporter::open('merchant');  // solo quelli di un gruppo
ErrorReporter::resolve($id, $userId);
```

La tabella non si sincronizza tra ambienti: gli errori di un ambiente sono i
suoi.
