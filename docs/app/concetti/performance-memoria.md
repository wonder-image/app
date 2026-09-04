# Memoria e performance del rendering frontend

## La regola: mai `Model::all()` non limitato sul frontend

`Model::all()` / `Model::getAll()` NON applicano `LIMIT` e caricano l'intero
result set in memoria (`fetch_all`), poi `decorateRows()` avvolge ogni riga in
un oggetto: il picco di memoria raddoppia col numero di righe. Su tabelle che
crescono è la causa #1 di `Allowed memory size exhausted`.

Per liste rivolte all'utente usa sempre un limite/paginazione:

    // ❌ evita sul frontend
    $prodotti = Product::all();

    // ✅ preferisci
    $prodotti = Product::find($condizione, $limit, $order, $direction);

## Il profiler di memoria (dev-only)

Con `APP_DEBUG=1` (o su localhost) ogni request frontend scrive nel PHP error
log una riga:

    [MEM][WARN] /prodotti peak=182.4MB heaviest=App\Models\Product::all():12043

- `WARN` quando il picco supera `MEMORY_PROFILE_THRESHOLD_MB` (default 128), `INFO` sotto.
- `heaviest` nomina il `Model::all()` che ha restituito più righe oltre
  `MEMORY_PROFILE_ROWS_THRESHOLD` (default 500), oppure `-` se nessuno.

Il profiler è inerte con debug spento: nessun log, un solo check booleano.

## Env

| Variabile | Default | Effetto |
|---|---|---|
| `APP_DEBUG` | (spento) | Attiva profiler e warning heavy-fetch. |
| `MEMORY_PROFILE_THRESHOLD_MB` | `128` | Soglia MB per il livello `WARN`. |
| `MEMORY_PROFILE_ROWS_THRESHOLD` | `500` | Righe oltre cui un fetch è "pesante". |
