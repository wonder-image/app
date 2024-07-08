# format

Il valore della colonna format varia in base al nome della colonna passata in [`$TABLE->addColumn()`](../../../../../backend/classi/table.md#addcolumn). È possibile passare nel parametro `$column` i seguenti parametri:

<table><thead><tr><th width="239">$column</th><th>Dettaglio</th></tr></thead><tbody><tr><td><a href="format.md#nome-della-colonna">Nome della colonna</a></td><td>Inserisce il valore della colonna</td></tr><tr><td><a href="format.md#action_button"><code>action_button</code></a></td><td>Inserisce un bottone azione</td></tr><tr><td><code>position_arrow_up</code></td><td>Inserisce una freccia per spostare le righe in alto</td></tr><tr><td><code>position_arrow_down</code></td><td>Inserisce una freccia per spostare le righe in basso</td></tr></tbody></table>

### Nome della colonna

Se viene inserito il nome della colonna il parametro `$format` può assumere questo valore:

```php
$format = [
    'value' => '' || [],
    'function' => [
        'name' => '',
        'parameter' => [],
        'return' => ''
    ],
    'format' => '' || 'image' || 'date',
    'href' => '' || 'modify' || 'view' || 'mailto' || 'tel',
];
```

<table><thead><tr><th width="204"></th><th width="265">Default</th><th>Dettaglio</th></tr></thead><tbody><tr><td>value</td><td>Colonna indicata nella funzione $TABLE->addColumn()</td><td>Nome colonna da cui prendere il valore. Può essere un array</td></tr><tr><td>function</td><td>null</td><td>È possibile formattare il risultato con una funzione</td></tr><tr><td>function['name']</td><td>null</td><td>Nome della funzione da chiamare</td></tr><tr><td>function['parameter']</td><td>id</td><td>Parametri della funzione. Colonna da passare alla funzione.</td></tr><tr><td>function['return']</td><td>null</td><td>Valore che la funzione deve tornare. Per utilizzare questo parametro la funzione deve rispondere con un array di oggetti.</td></tr><tr><td>format</td><td>null</td><td>È possibile convertire il valore in immagine o data</td></tr><tr><td>href</td><td>null</td><td>Link da dare al valore.</td></tr></tbody></table>

***

### action\_button

Se viene inserito come nome della colonna la stringa `action_button`, il parametro `$format` può assumere questo valore:

```php
$format = [
    'view' => '' || true || false,
    'modify' => '' || true || false,
    'download' => '' || true || false,
    'authority' => '' || true || false,
    'evidence' => '' || true || false,
    'visible' => '' || true || false,
    'active' => '' || true || false,
    'delete' => '' || true || false,
    'link' => [
        'label' => '',
        'href' => '',
        'target' => '',
        'request' => '',
        'key' => [],
        'filter' => [
            'row' => [],
            'area' => [],
            'authority' => [],
        ],
    ]
```
