# Performance per richiesta

Due strumenti del core: un contatore delle query, attivo solo in Debug, e
alcuni memo che evitano letture ripetute nella stessa richiesta. Per la
memoria del rendering vedi `docs/app/concetti/performance-memoria.md`.

## Contatore delle query

Con `APP_DEBUG=1` (o, se manca, `APP_ENV=local`; vedi `Wonder\App\Debug`) ogni richiesta
web scrive nel PHP error log una riga così:

    [SQL] /prodotti/ queries=42 top=12x SHOW TABLES LIKE ? | 6x SELECT * FROM `user` WHERE id = ? LIMIT ?

- `queries` è il totale delle query passate da `Wonder\Sql\Query`.
- `top` raggruppa le query per forma: letterali, numeri e liste `IN (...)`
  diventano `?`. Una forma ripetuta molte volte indica di solito un N+1 o una
  lettura da memoizzare.
- `QUERY_COUNTER_TOP` (env) cambia quante forme mostrare, default 10.
- In CLI la riga non si scrive. Nei test: `QueryCounter::setSink()`,
  `QueryCounter::groups()`, `QueryCounter::reset()`.
- Fuori Debug `QueryCounter::note()` esce alla prima riga: nessun costo.

Il contatore non vede le query che usano `mysqli` direttamente: i repository
del consenso, SSP delle tabelle backend, `prepare` dello scheduler, il DDL di
`CreateTable` e il `SET` della collation in `Connection`. Il totale è quindi
più basso di quello del log generale di MySQL.

## Memo per richiesta

`Wonder\App\Support\RequestMemo` tiene valori statici divisi per scope. Sotto
PHP-FPM vivono una richiesta. Lo scope di un valore letto da una tabella è il
nome della tabella.

| Cosa | Scope | Note |
| --- | --- | --- |
| `sqlTableExists()` | `@sql.table_exists` | Solo `true`: una tabella creata dopo si vede subito. |
| `infoUser($id)` per id | `user` | Si memoizza la riga, l'oggetto si ricostruisce a ogni chiamata. Le ricerche per altre colonne non si memoizzano. |
| `head.php` analytics | `analytics` | Una lettura per richiesta. |
| `head.php` font | `css_font` | Una lettura per richiesta. |
| `Asset::version()` | `Asset::VERSION_MEMO` | Uno stat per percorso; un file assente non si memoizza. |

Le chiavi delle letture da DB contengono la connessione (`sqlMemoConnectionKey()`),
perché `sqlDatabase()` può cambiare `$mysqli`.

### Invalidazione

Automatica:

- `Query::Insert/Update/Delete/Truncate` svuotano lo scope della tabella
  scritta. Passano di qui `sqlInsert`, `sqlModify`, `sqlDelete` e i `Model`.
- `Transaction` svuota tutto quando annulla.
- `CreateTable` svuota lo scope della tabella dopo il DDL.

A mano, se si scrive una tabella senza `Query` (SQL diretto su `mysqli`):

    RequestMemo::forget('user');          // tutto lo scope
    forgetInfoUser($id);                  // un utente
    forgetInfoUser();                     // tutti gli utenti
    RequestMemo::forget(Asset::VERSION_MEMO);
    RequestMemo::reset();                 // tutto

Uno script CLI che serve più richieste nello stesso processo, o un test, deve
chiamare `RequestMemo::reset()` tra una e l'altra.
