---
icon: list-check
---

# Azioni di massa

## Cos'è

Selezione multipla nelle liste backend: una colonna di checkbox (con
«seleziona tutto» nell'intestazione) e, sopra la tabella, una barra con le
azioni da applicare alle righe scelte. Esempi: confermare o annullare più
ordini, pubblicare o nascondere più prodotti.

La Resource dichiara le azioni con `TableLayoutSchema::bulkActions([...])`.
Il JS sta nella lib (`backend/js/bulk.js`); il server esegue l'azione su
`POST /api/backend/bulk/`.

## Dove si trova nel codice

| Elemento | File | Ruolo |
|---|---|---|
| Azione | `class/Backend/Table/BulkAction.php` | builder dell'azione |
| Supporto | `class/Backend/Table/BulkActions.php` | colonna, barra, normalizzazione, esecuzione |
| Endpoint | `app/http/api/backend/bulk.php` | `POST /api/backend/bulk/` (`backend.bulk`) |
| Testi | `resources/lang/{it,en}/bulk.json` | etichette, conteggi, errori |
| JS | lib, `src/build/backend/js/bulk.js` | selezione, barra, chiamata |

## Esempio (copiabile)

```php
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Backend\Table\BulkAction;

public static function tableLayoutSchema(): TableLayoutSchema
{
    return TableLayoutSchema::for(static::class)
        ->bulkActions([
            BulkAction::toggle('publish', 'active', true)
                ->label(__t('products.bulk.publish'))
                ->icon('bi bi-eye'),
            BulkAction::toggle('draft', 'active', false)
                ->label(__t('products.bulk.draft'))
                ->icon('bi bi-eye-slash'),
            BulkAction::make('archive')
                ->label(__t('products.bulk.archive'))
                ->confirm(__t('products.bulk.archive_confirm'))
                ->handle(static function (array $ids, string $resourceClass, ?object $user): array {
                    $failed = [];

                    foreach ($ids as $id) {
                        // ... se non si può: $failed[$id] = 'Motivo';
                    }

                    return ['done' => array_diff($ids, array_keys($failed)), 'failed' => $failed];
                }),
            BulkAction::delete(),
        ]);
}
```

## Il builder

| Metodo | Cosa fa |
|---|---|
| `BulkAction::make($key)` | nuova azione; la chiave è `[a-z0-9_-]`, al massimo 64 caratteri, unica nella Resource |
| `->label($text)` | testo del bottone (senza, la chiave) |
| `->icon('bi bi-eye')` | icona prima del testo |
| `->variant('danger')` | colore Bootstrap del bottone (`btn-outline-*`): primary, secondary, success, danger, warning, info, light, dark; predefinito dark |
| `->permission('update' \| 'delete', ?Resource::class)` | permesso richiesto (predefinito `update`), della Resource della lista o di quella indicata: per gli ordini, `OrderActionResource::class`, la stessa dei bottoni della scheda |
| `->confirm($text)` | chiede conferma prima di partire (finestra della lib); con `danger` la conferma è rossa |
| `->handle(fn (array $ids, string $resourceClass, ?object $user): array\|int)` | il gestore |
| `->transaction(false)` | il gestore non gira in una transazione (chi apre le sue, come le azioni degli ordini) |
| `BulkAction::toggle($key, $column, $bool)` | mette `$column` a `'true'`/`'false'` su tutte le righe |
| `BulkAction::delete($key = 'delete')` | elimina come il bottone della lista: `assertDeletable()`, poi `deleteRecord()` o il soft delete; permesso `delete`, con conferma |

Il gestore riceve gli id già filtrati e restituisce:

- `['done' => int[], 'failed' => [id => messaggio]]`: gli id fuori da quelli
  ricevuti si scartano, un messaggio vuoto diventa «Operazione non riuscita.»;
- un intero (o niente): tutte le righe fatte.

Un'eccezione del gestore fa fallire tutte le righe: con una `RuntimeException`
il messaggio è il suo, altrimenti quello generico. Dentro la transazione,
l'eccezione annulla anche le scritture già fatte.

## Come funziona

- La colonna `row-select` va a sinistra, dopo la maniglia del riordino e dopo
  i segnali di riga (`row_signals`) se sono la prima colonna. Non si ordina e
  non si stampa.
- La selezione vale per la pagina visibile: cambiare pagina, cercare,
  filtrare o ordinare la svuota. La barra resta nascosta finché non c'è
  almeno una riga scelta e mostra quante sono.
- Il browser manda solo slug della Resource, chiave dell'azione e id (al
  massimo 500), con il token CSRF. L'azione la trova il server fra quelle
  della Resource, mai dalla richiesta.
- Il server tiene solo gli id che esistono, rientrano in
  `querySchema()['condition']` (di solito le righe non cancellate) e
  l'utente vede (visibilità per riga); gli altri spariscono senza errore.
- Risposta: `{done, failed, messages}`; il browser mostra l'esito in un toast
  (con i messaggi delle righe fallite, raggruppati) e ricarica la lista.
- Se qualcosa è stato fatto su una tabella sincronizzata, il server riesporta
  i dati come dopo un salvataggio.

## Permessi ed errori

| Caso | Risposta |
|---|---|
| non POST | 405 |
| token CSRF mancante o scaduto | 419 |
| Resource sconosciuta | 404 |
| azione inesistente o id non validi (nessuno, più di 500) | 422 |
| lista in sola lettura o tabella sincronizzata letta in questo ambiente | 403 |
| permesso mancante (`update`/`delete` della Resource, o di quella di `permission()`) | 403 |
| nessuna riga visibile fra quelle mandate | 404 |

In sola lettura la colonna e la barra non si stampano proprio. La barra
mostra solo le azioni che l'utente può usare (lo stesso controllo
dell'endpoint); senza nessuna, niente barra e niente colonna.

Attenzione: in `PermissionSchema` un'azione che la Resource non limita resta
aperta a tutti. Una lista con `access(area, ['list', 'view'])` concede quindi
`update` a chiunque: in quel caso indica in `permission()` la Resource che
protegge davvero l'azione.

## Errori comuni

- **Chiave doppia** → `RuntimeException` già alla dichiarazione: ogni azione
  ha la sua chiave.
- **Testo scritto nel codice** → etichette e conferme passano da `__t()`.
- **Azione che cambia altre tabelle senza transazione** → lascia
  `transaction()` acceso, salvo gestori che ne aprono una loro.
- **Toggle su una colonna sincronizzata** → in un ambiente che la legge,
  tutte le righe falliscono con «Colonna in sola lettura».

## Checklist

- [ ] `bulkActions([...])` nel `tableLayoutSchema()`
- [ ] etichette e conferme via `__t()`
- [ ] `confirm()` sulle azioni che non si annullano
- [ ] il gestore restituisce `failed` con un motivo per le righe saltate
- [ ] `permission('delete')` per ciò che elimina
