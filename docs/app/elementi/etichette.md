# Etichette in PDF

`Wonder\Support\Labels` stampa fogli di etichette con righe di testo, un valore
in evidenza (per esempio il prezzo) e un codice EAN-8 o EAN-13, su fogli A4 a
griglia o su rotolo termico. Il PDF esce da `Wonder\Pdf` (FPDF).

```php
use Wonder\Support\Labels\LabelFormat;
use Wonder\Support\Labels\LabelSheet;

$format = LabelFormat::standard()['a4-24'];

$labels = LabelSheet::labels([
    ['lines' => ['Maglia', 'Blu / M', 'SKU MAG-BLU-M'], 'highlight' => '29,90 €', 'ean' => '4006381333931', 'copies' => 3],
]);

$pdf = LabelSheet::render($format, $labels, start: 4, title: 'Etichette');
```

## Formati

`LabelFormat::standard()` restituisce i formati più diffusi, indicizzati per
chiave:

| Chiave | Formato |
| --- | --- |
| `a4-24` | A4, 3 × 8 etichette da 70 × 37 mm |
| `a4-40` | A4, 4 × 10 etichette da 52,5 × 29,7 mm |
| `a4-65` | A4, 5 × 13 etichette da 38,1 × 21,2 mm |
| `roll-50x30` | Rotolo termico, una etichetta 50 × 30 mm per pagina |
| `roll-40x25` | Rotolo termico, una etichetta 40 × 25 mm per pagina |

Un formato su misura si crea con `LabelFormat::a4($key, $name, $columns,
$rows, $width, $height)`, che centra la griglia senza spazi nel foglio, con
`LabelFormat::roll($key, $name, $width, $height)` oppure con il costruttore,
che accetta tutte le misure in mm (margini e spazi fra le etichette compresi). Un modulo o un sito che offre la scelta del
formato parte da `standard()` e aggiunge o toglie formati con un proprio hook.

## Contenuto

`LabelSheet::labels($items)` prepara un'etichetta per copia. Ogni elemento
accetta:

- `lines`: righe di testo, la prima in grassetto; quelle vuote si scartano;
- `highlight`: un valore in grande sotto le righe;
- `ean`: 8 o 13 cifre, codificate con `Wonder\Support\Barcode\Ean`;
- `copies`: quante etichette uguali (predefinito 1).

Un EAN con la cifra di controllo errata non ferma il foglio: l'etichetta esce
senza barre e con «(EAN non valido)» accanto alle cifre. Quando l'etichetta è
bassa, le righe di testo si tolgono dall'ultima perché le barre restino alte
almeno 5 mm.

## Impaginazione

- `plan($format, $labels, $start)` dà pagina e angolo (in mm) di ogni
  etichetta; `$start` salta le prime caselle di un foglio già usato in parte
  e sul rotolo non conta.
- `layout($format, $label)` dà le misure interne: righe, valore, barre e
  cifre.
- `render($format, $labels, $start, $title)` disegna il PDF e lo restituisce
  come stringa.

`plan()` e `layout()` sono pubblici perché i test possano verificare le misure
senza leggere il PDF compresso.
