# Table

La classe Table viene utilizzata per la creazione di tabelle dinamiche. Utilizza la libreria [DataTables](https://datatables.net/).

***

## Configurazione

```php
use Wonder\Backend\Table;
```

Per l'inizializzazione della classe è necessario specificare:

<table><thead><tr><th width="164">Variabile</th><th>Valore</th></tr></thead><tbody><tr><td><code>$table</code></td><td>Nome della tabella</td></tr><tr><td><code>$connection</code></td><td>Connessione al database che ospita la tabella</td></tr></tbody></table>

```php
$TABLE = new Table( $table, $connection );
```

### endpoint

È necessario indicare l'enpoint dov'è contenuto il file che analizza i dati inviati da DataTables. È possibile utilizzare l'endpoint di default utilizzando la variabile `$API->DataTables` oppure creare un endpoint custom utilizzando la documentazione [Server-Side processing di DataTables](https://datatables.net/examples/data\_sources/server\_side.html).

```php
$TABLE->endpoint( $API->DataTables );
```

### query

La funzione `$TABLE->query( $query )` viene utilizzata per filtrare la tabella.

```php
$TABLE->query("`deleted` = 'false'");
```

### queryOrder

La funzione `$TABLE->queryOrder()` viene utilizzata per l'ordinamento della tabella. Le variabili all'interno di questa funzione sono:

```php
$TABLE->queryOrder('creation');
```

<table><thead><tr><th width="327">Variabile</th><th width="123">Default</th><th>Valore</th></tr></thead><tbody><tr><td><code>$column</code></td><td>obbligatorio</td><td>Nome colonna ordinamento</td></tr><tr><td><code>$direction</code></td><td><code>DESC</code></td><td>Direzione ordinamento <code>ASC</code> || <code>DESC</code></td></tr><tr><td><code>$columnWhenFilterIsActive</code></td><td><code>null</code></td><td>Colonna se l'utente ha attivato i filtri</td></tr><tr><td><code>$directionWhenFilterIsActive</code></td><td><code>null</code></td><td>Direzione se l'utente ha attivato i filtri</td></tr></tbody></table>

### addColumn

La funzione `$TABLE->addColumn()` viene utilizzata per l'inserimento delle colonne della tabella. Le variabili all'interno di questa funzione sono:

<table><thead><tr><th width="187">Variabile</th><th width="122">Default</th><th>Valore</th></tr></thead><tbody><tr><td><code>$label</code></td><td>obbligatorio</td><td>Intestazione colonna</td></tr><tr><td><code>$column</code></td><td>obbligatorio</td><td>Nome della colonna</td></tr><tr><td><code>$orderable</code></td><td><code>false</code></td><td>Indicare se è possibile ordinare la tabella secondo questa colonna <code>true</code> || <code>false</code></td></tr><tr><td><code>$class</code></td><td><code>null</code></td><td>Indicare delle classi aggiuntive da aggiungere alla colonna</td></tr><tr><td><code>$hiddenDevice</code></td><td><code>null</code></td><td><p>Dispositivi dove la colonna non è visibile</p><p><code>null</code> || <code>mobile</code> || <code>tablet</code> || <code>desktop</code> </p><p><a href="../../app/specifiche/variabili/classe-table/addcolumn/hiddendevice.md">scopri di più</a></p></td></tr><tr><td><code>$width</code></td><td><code>'auto'</code></td><td><p>Larghezza della colonna</p><p><code>null</code> || <code>little</code> || <code>medium</code> || <code>big</code>  </p><p><a href="../../app/specifiche/variabili/classe-table/addcolumn/width.md">scopri di più</a></p></td></tr><tr><td><code>$format</code></td><td><code>[]</code></td><td><p>Rielaborazione dell'output </p><p><a href="../../app/specifiche/variabili/classe-table/addcolumn/format.md">scopri di più</a></p></td></tr></tbody></table>

