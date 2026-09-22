# Repeater raggruppato — design

**Data:** 2026-09-22
**Repo:** `wonder-image/app` (framework)
**Tipo:** architetturale (capacità del componente Repeater: schema + renderer Bootstrap + JS inline), più una correzione all'Accordion

## Contesto

Il repeater del backend mostra righe piatte, tutte allo stesso livello. Con
poche righe va bene; con dodici diventa un muro. Il caso che ha fatto nascere
questa spec è la griglia delle versioni del modulo `wonder-image/gestionale`:
due colori per tre taglie fanno sei righe, tre colori per quattro taglie ne
fanno dodici, e chi compila deve scrivere il prezzo dodici volte scorrendo un
elenco in cui le righe si somigliano tutte.

Shopify risolve lo stesso problema con un selettore **"Raggruppa per"** sopra
la griglia: scegli Colore e le dodici righe diventano tre blocchi richiudibili
(`Blu — 4 varianti`), ognuno con una casella **Prezzo gruppo** che scrive sulle
righe che contiene.

Non è un problema del gestionale: è del repeater. Qualunque risorsa con righe
correlate numerose — listini, orari, taglie, righe di un documento — ha lo
stesso muro.

## Obiettivo

Dare al repeater la capacità di **raggruppare le righe per il valore di una
colonna**, con testate richiudibili e una casella-comando che scrive sulle
righe del gruppo, senza cambiare niente di come le righe vengono dichiarate,
rese, postate e sincronizzate.

Un repeater che non dichiara il raggruppamento deve produrre **esattamente**
l'HTML di oggi.

## Non obiettivi (YAGNI)

- Raggruppare per **più colonne insieme** (Colore *e* Taglia annidati).
  Shopify stesso ne offre una sola.
- Raggruppamento **lato server** con impaginazione o caricamento a blocchi: le
  righe continuano ad arrivare tutte nella pagina.
- Il tema **Wonder** (frontend). Il repeater raggruppato è una cosa da
  pannello; il tema pubblico non lo rende.
- Modifiche a **`wonder-image/lib`**: il JS del repeater è inline nel renderer
  Bootstrap (come il quick-create) e lì resta.
- **Ordinare** i gruppi a mano, o ricordarne l'ordine: i gruppi seguono
  l'ordine delle opzioni della colonna.

## Decisioni prese (in brainstorming)

- **Il DOM delle righe non si tocca.** I gruppi si formano con le testate
  inserite come fratelli e l'ordinamento visivo di flexbox (`order`). Il
  repeater è già usato da mezzo pannello: spostare davvero i nodi rimetterebbe
  in discussione posizioni, `positionKey`, riordino e template.
- **Una colonna per volta**, scelta fra quelle dichiarate.
- **Le etichette le risolve PHP**, non il JS: il renderer stampa su ogni riga
  il valore *e* l'etichetta già pronta per ogni colonna raggruppabile.
- **La casella-comando è un comando, non un dato.** Scrive negli input delle
  righe sotto, subito e sotto gli occhi; non viene postata, non esiste nel
  salvataggio.
- **Con un raggruppamento attivo le frecce di riordino spariscono**: "sposta
  su" dentro un gruppo non vuol dire niente.
- **L'Accordion si corregge qui**, perché la stessa scheda che userà i gruppi
  ha bisogno di blocchi che si chiudano davvero.

## Design

### 1. Dichiarazione — tre setter su `InputRepeater`

```php
FormField::key('products')
    ->repeater([...])
    ->repeaterGroupBy('product_variant_id')            // una o più colonne
    ->repeaterGroupCommand('price', 'Prezzo del gruppo')
    ->repeaterGroupCollapsed()                          // chiusi alla nascita
    ->repeaterGroupCountLabel('versione', 'versioni')   // "Blu — 4 versioni"
```

Tutti e quattro finiscono in `context`, come gli altri `repeater*`:
`group_by`, `group_command`, `group_collapsed`, `group_count_label`. Nessuna
firma esistente cambia.

`repeaterGroupBy()` accetta le chiavi delle colonne su cui si può raggruppare,
nell'ordine in cui compaiono nel selettore. La prima **non** è attiva di
default: alla nascita il raggruppamento è **Nessuno**, finché non lo si sceglie
(o finché `localStorage` non ricorda l'ultima scelta per quel repeater).

### 2. `RepeaterGroups` — la classe pura

`class/App/Support/RepeaterGroups.php`, accanto a `Repeater.php`.

```php
RepeaterGroups::of(array $columns, array $rows, array $groupBy): array
// → [ 'row_3' => [ 'product_variant_id' => ['value' => '10', 'label' => 'Blu'] ], ... ]

RepeaterGroups::labelOf(mixed $column, mixed $value): string
// select → l'etichetta dell'opzione; altrimenti il valore stesso, come stringa
```

Tutta la risoluzione delle etichette sta qui, dove si può provare senza un
browser: una colonna `select` risolve `10 → "Blu"` dalle sue opzioni, una
colonna di testo usa il proprio valore, una colonna senza valore finisce nel
gruppo vuoto.

### 3. Render

Sulle righe, un attributo per colonna raggruppabile:

```html
<div class="col-12 wi-repeater-row"
     data-wi-row-key="row_3"
     data-wi-group-product_variant_id="10"
     data-wi-group-label-product_variant_id="Blu"> … </div>
```

Sopra la griglia, solo se `group_by` è dichiarato e le righe sono più di una:

```html
<div class="wi-repeater-groupbar">
  <label>Raggruppa per</label>
  <select class="wi-repeater-groupby">
    <option value="">Nessuno</option>
    <option value="product_variant_id">Colore</option>
  </select>
</div>
```

L'etichetta di ogni voce è quella della colonna (`label` dell'`Input`), non la
sua chiave.

Le **testate** non si stampano lato server: le costruisce il JS da un
`<template>` emesso accanto a quello della riga, così una testata non può
finire nel posting né confondere il conteggio delle righe.

### 4. Il JS (inline nel renderer, come il resto)

Quattro funzioni nuove accanto a `wiRepeaterAddRow` e compagnia:

- `wiRepeaterGroupApply(rowsId, columnKey)` — legge da ogni riga il valore
  corrente (prima dall'input vivo della colonna, poi dal `data-` stampato),
  raccoglie i gruppi nell'ordine di prima comparsa, crea le testate mancanti,
  assegna gli `order` e nasconde le testate inutili. Con `columnKey` vuoto
  azzera tutto: `order` a zero, testate via, frecce di nuovo visibili.
- `wiRepeaterGroupToggle(header)` — apre e chiude (`d-none` sulle righe del
  gruppo).
- `wiRepeaterGroupCommand(input)` — scrive il valore nella colonna comandata di
  ogni riga del gruppo, emettendo `input`+`change` su ciascun campo perché
  eventuali widget se ne accorgano.
- Un ascoltatore sul contenitore: quando cambia un input della colonna attiva,
  o si aggiunge o elimina una riga, si richiama `wiRepeaterGroupApply`.

Righe nuove: nascono senza valore nella colonna di raggruppamento, quindi
finiscono in un gruppo **"Senza …"** in fondo, aperto. Appena si sceglie il
colore, la riga salta nel suo gruppo.

### 5. La correzione all'Accordion

`class/Themes/Bootstrap/Components/Accordion.php` passa i componenti al proprio
`accordion-body` **senza la riga a griglia** che la Card mette sul `card-body`:

```php
// Card
$html .= "<div class=\"card-body $classColumn $classGap\">";
// Accordion (oggi)
$html .= "<div class=\"accordion-body\">{$content}</div>";
```

Risultato: dentro un Accordion i campi di un form, che portano `col-span-*`,
non hanno nessun `row d-grid row-col-*` in cui stare, e si schiacciano. Per
questo il modulo gestionale ha un `foldable()` che è una Card travestita.

Correzione: l'Accordion usa `HasColumns` e `HasGap` come la Card e mette
`$classColumn $classGap` sul corpo, con gli stessi default. Un Accordion che
oggi contiene solo testo non cambia aspetto.

## Vincoli e casi limite

- **Colonne ammesse:** quelle con valori enumerabili — `select` (etichetta
  dall'opzione) o testo (etichetta = valore). Una colonna `hidden` funziona
  solo se il modulo le dà un'etichetta: il valore nudo non basta.
- **Raggruppamento e riordino** non convivono: con un gruppo attivo le frecce
  spariscono, e tornano scegliendo "Nessuno". Le posizioni salvate non
  cambiano mai per effetto del raggruppamento, perché il DOM non cambia.
- **Il template della riga** (`__ROW_KEY__`, `d-none`) resta fuori da ogni
  gruppo.
- **Eliminazione:** se sparisce l'ultima riga di un gruppo, la testata sparisce
  con lei.
- **Una riga sola, o nessuna:** il selettore non compare.
- **`nested(false)`** (campi postati come `colonna[]`): funziona, perché il
  JS legge gli input per posizione nella riga e non per nome.
- **La memoria della scelta** sta in `localStorage`, con chiave l'id del
  repeater. È una preferenza di vista: se il browser la rifiuta non succede
  niente, si riparte da "Nessuno".

## Testing

- `RepeaterGroups::of()` e `labelOf()`: i test del repo (`tests/harness.php`, `check()`), con colonne `select`, testo,
  valori mancanti, valori che non stanno fra le opzioni.
- Render: un test che monta un repeater con `repeaterGroupBy()` e verifica gli
  attributi `data-wi-group-*`, la presenza del selettore e del template della
  testata; e un test che monta lo stesso repeater **senza** raggruppamento e
  verifica che l'HTML non contenga nessuna delle aggiunte.
- Accordion: un test che verifica la riga a griglia sul corpo.
- A mano, in un sito: raggruppa, chiudi un gruppo, comanda il prezzo, salva e
  ricarica; poi "Nessuno" e verifica che le frecce tornino e il riordino
  salvi le posizioni giuste.

## Rilascio

Chiude con il tag **v.2.3.0**, che porta fuori anche il **quick-create FK**
(già in `main`, mai taggato). Il modulo gestionale alzerà il pavimento a
`^2.3.0`.

## Decisioni di questa spec

| # | Decisione | Perché |
|---|---|---|
| R1 | Gruppi visivi con `order`, DOM intatto | Il repeater è ovunque: spostare i nodi rimetterebbe in gioco posizioni e riordino |
| R2 | Una colonna di raggruppamento per volta | Copre il caso reale; l'annidamento raddoppierebbe il JS |
| R3 | Etichette risolte in PHP | Provabili senza browser; il JS resta ignorante |
| R4 | La casella di gruppo è un comando, non un dato | Si vede l'effetto subito e niente viaggia nel posting |
| R5 | Testate costruite dal JS, non dal server | Una testata nel DOM delle righe sarebbe una finta riga |
| R6 | Frecce nascoste quando si raggruppa | "Sposta su" dentro un gruppo non ha significato |
| R7 | Alla nascita nessun raggruppamento | Un repeater che si apre già diviso sorprende chi non ha chiesto niente |
| R8 | L'Accordion prende la griglia della Card | Stessa correzione, e senza di essa i blocchi richiudibili restano finti |

## Piani

Da scrivere dopo l'approvazione.
