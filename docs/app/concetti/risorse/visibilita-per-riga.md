---
icon: eye-slash
---

# Visibilità per riga

## Cos'è

Una regola che dice **quali righe di una tabella può vedere un utente**: per
esempio un agente commerciale vede solo i propri clienti e ordini. Si dichiara
una volta, per tabella, e il core la applica ovunque legge le righe della
Resource: lista backend, pagine del record, export, API generate e azioni per
id della lista.

Una tabella **senza regola si comporta esattamente come prima**: nessuna query
in più, condizioni identiche.

Codice: `class/App/Visibility/RowVisibility.php`,
`RowVisibilityScope.php`, `ResourceRows.php`.

## Dichiarare la regola

La regola riceve l'utente e restituisce una condizione:

- un array `colonna => valore` (valori bindati; una lista diventa `IN`, `null`
  diventa `IS NULL`, la lista vuota non mostra nulla);
- una stringa SQL **scritta dal codice**, mai dalla richiesta;
- `null` per nessuna restrizione per quell'utente.

### Dalla Resource

```php
use Wonder\App\Visibility\RowVisibility;

final class OrderResource extends Resource
{
    public static function rowVisibility(object $user): string|array|null
    {
        // L'agente vede solo i propri ordini; gli altri ruoli tutto.
        return in_array('agent', RowVisibility::authorities($user), true)
            ? [ 'agent_id' => (int) $user->id ]
            : null;
    }
}
```

### Dall'area, senza toccare la Resource

Un sito o un modulo può aggiungere una regola a una tabella che non possiede,
per esempio da `custom/config/config.php` o dal `boot()` del modulo:

```php
RowVisibility::rule('gst_orders', static function (object $user): ?array {
    return in_array('agent', RowVisibility::authorities($user), true)
        ? [ 'agent_id' => (int) $user->id ]
        : null;
});
```

Più regole sulla stessa tabella vanno **in AND**: una dichiarazione restringe,
non allarga mai.

### Chi vede tutto

`admin` e `administrator` (`RowVisibility::SEE_ALL`) saltano la regola, che
non viene nemmeno chiamata. Una Resource cambia l'elenco con
`rowVisibilitySeeAll(): array`, una regola d'area con il terzo argomento di
`rule($table, $rule, [ 'admin', 'administrator', 'capo-area' ])`.

{% hint style="warning" %}
La regola **non può nominare `deleted`**: `Model::find()` aggiunge il filtro di
soft delete solo se la condizione non lo nomina, e una regola che lo facesse
farebbe ricomparire le righe eliminate. Una condizione che nomina `deleted`, una
stringa vuota o un tipo diverso da stringa/array/`null` lanciano
`LogicException`.
{% endhint %}

## Quale utente

| Contesto | Utente | Effetto |
|---|---|---|
| Pagina backend con sessione | `$USER` | regola dell'utente |
| API generata con bearer di un utente | utente del token | regola dell'utente |
| API generata con token di pagina | utente della sessione, o anonimo | anonimo = `id` 0, nessuna authority |
| Bearer `@system` (server, task) | nessuno | nessuna restrizione |
| CLI, scheduler, `forge` | nessuno | nessuna restrizione |

`RowVisibility::currentUser()` restituisce l'utente del contesto;
`RowVisibility::as($user, $callback)` lo sostituisce per la durata della
callback (annidabile).

## Dove la applica il core

| Punto | Comportamento |
|---|---|
| Lista backend (`ListProvider`, SSP) | la regola entra nel `WHERE` di righe, ricerca, filtri e conteggi |
| Export della lista | stesse righe della lista |
| Pagine `view` / `edit` / `update` / `delete` | riga nascosta = **404** |
| API generate (`show`, `update`, `delete`, `list`) | riga nascosta = **404** |
| Azioni per id della lista (`delete`, `change/boolean`, `move`, `file/delete`, `file/move`, `delete-icon`) | riga nascosta = **404** |

Una riga nascosta **non esiste**: la risposta è sempre 404, mai 403, che
confermerebbe l'esistenza dell'id. Con una regola attiva, `move` rifiuta (422)
anche lo scambio di posizione con una riga vicina che l'utente non vede.

## Codice che non passa dal core

Tool MCP, widget e totali della dashboard, opzioni dei campi relazione
costruite a mano leggono i dati da sé. Devono passare da uno
`RowVisibilityScope`:

```php
$scope = RowVisibility::current();         // utente del contesto
$scope = RowVisibility::for($user);        // utente esplicito (es. MCP)

$scope->find(OrderModel::class, [ 'status' => 'open' ]);
$scope->findById(OrderModel::class, $id);  // null se nascosta
$scope->count(OrderModel::class);          // totali della dashboard
$scope->condition('gst_orders', $where);   // condizione da passare altrove
$scope->visible('gst_orders', $id);
```

Per una query SQL scritta a mano: `RowVisibility::applySql($table, $where,
$user)` restituisce il `WHERE` con la regola in AND; `RowVisibility::visible()`
controlla un id.

## Test

`php tests/App/RowVisibilityTest.php` copre una Resource con regola su SQLite
in memoria: lista, URL diretto, API, MCP, admin e tabella senza regola.
