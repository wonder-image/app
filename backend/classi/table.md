# Table

La classe Table viene utilizzata per la creazione di tabelle dinamiche. Utilizza la libreria [DataTables](https://datatables.net/).

***

## Configurazione

```php
use Wonder\Backend\Table;
```

Per l'inizializzazione della classe è necessario specificare:

| Variabile     | Valore                                        |
| ------------- | --------------------------------------------- |
| `$table`      | Nome della tabella                            |
| `$connection` | Connessione al database che ospita la tabella |

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

| Variabile                      | Default      | Valore                                     |
| ------------------------------ | ------------ | ------------------------------------------ |
| `$column`                      | obbligatorio | Nome colonna ordinamento                   |
| `$direction`                   | `DESC`       | Direzione ordinamento `ASC` \|\| `DESC`    |
| `$columnWhenFilterIsActive`    | `null`       | Colonna se l'utente ha attivato i filtri   |
| `$directionWhenFilterIsActive` | `null`       | Direzione se l'utente ha attivato i filtri |

### addColumn

La funzione `$TABLE->addColumn()` viene utilizzata per l'inserimento delle colonne della tabella. Le variabili all'interno di questa funzione sono:

<table><thead><tr><th width="187">Variabile</th><th width="122">Default</th><th>Valore</th></tr></thead><tbody><tr><td><code>$label</code></td><td>obbligatorio</td><td>Intestazione colonna</td></tr><tr><td><code>$column</code></td><td>obbligatorio</td><td>Nome della colonna</td></tr><tr><td><code>$orderable</code></td><td><code>false</code></td><td>Indicare se è possibile ordinare la tabella secondo questa colonna <code>true</code> || <code>false</code></td></tr><tr><td><code>$class</code></td><td><code>null</code></td><td>Indicare delle classi aggiuntive da dare alla colonna</td></tr><tr><td><code>$hiddenDevice</code></td><td><code>null</code></td><td><p>Dispositivi dove la colonna non è visibile</p><p><code>null</code> || <code>mobile</code> || <code>tablet</code> || <code>desktop</code> </p><p><a href="../../variabili/variabili/classe-table/hiddendevice.md">scopri di più</a></p></td></tr><tr><td><code>$width</code></td><td><code>'auto'</code></td><td>Indicare la larghezza della colonna. I valori validi sono <code>auto</code> || <code>little</code> || <code>medium</code> || <code>big</code> </td></tr><tr><td><code>$other</code></td><td><code>[]</code></td><td></td></tr></tbody></table>
