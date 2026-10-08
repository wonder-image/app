# Catalogo dei componenti

Questa cartella è la fonte della documentazione dei componenti di
`wonder-image/app`: una scheda PHP per ogni Element, con descrizione, esempi
e note per tema. Le schede non si leggono qui: le rende il **catalogo**, una
pagina che mostra per ogni esempio il codice da copiare e l'anteprima viva nel
tema scelto (Wonder o Bootstrap, quest'ultimo anche in modalità scura).

## Dove si vede

| Dove | URL | Cosa serve |
|---|---|---|
| Backend di un sito (sezione **Dev → Componenti**, solo `admin`) | `/backend/app/docs/components/` | niente: il sito ha già la lib e i propri token CSS, le anteprime Wonder usano lo stile del sito |
| Dalla radice del pacchetto, senza un sito | `http://127.0.0.1:8090/` | `npm install` una volta (porta `wonder-image/lib` in `node_modules`), poi `composer docs` oppure `php bin/docs.php [host:porta]` |

Il server autonomo (`Wonder\Docs\Server`) non usa database, sessione né
`.env`: definisce da sé le costanti che i renderer si aspettano, serve gli
asset della lib da `node_modules/wonder-image` e rende il tema Wonder con i
token di default del framework (`Wonder\View\CssTokens::defaultRoot()`, gli
stessi che `cssRoot()` scrive in un sito nuovo).

La versione minima della lib resta dichiarata in un solo punto,
`extra.wonder.lib` del `composer.json`; il `package.json` del pacchetto la
ripete solo come vincolo npm e un test controlla che coincidano.

## Come è organizzato

```
docs/components/
  README.md              ← questa guida
  form/*.php             ← categoria "Form"        (Elements/Form/Components/*, Form)
  components/*.php       ← categoria "Componenti"  (Elements/Components/*)
  media/*.php            ← categoria "Media"       (Elements/Media/*)
  charts/*.php           ← categoria "Grafici"     (Elements/Charts/*)
```

La cartella è la categoria. Dentro ogni categoria le schede si dividono in
**gruppi** (dichiarati in `Wonder\Docs\Catalog::defaultCategories()`):

| Categoria | Gruppi |
|---|---|
| `form` | `structure` (Form, Hidden), `text` (testo e numeri), `choice` (select, check, toggle), `date`, `file`, `action` (Button, Submit), `advanced` (Repeater, ricerca remota, reCAPTCHA, ...) |
| `components` | `action` (Button, ButtonGroup, Dropdown, Link, QuickCreateButton), `feedback` (Alert, Badge, Tooltip, HelpText), `layout` (Card, Container, Accordion, Modal, SectionTitle), `content` (Text, RichText, DataItem, InfoCard, MetricCard), `choice` (Choice, ChoiceGroup, Steps), `docs` (Code, Preview) |
| `media` | nessun gruppo |
| `charts` | nessun gruppo |

Il codice che fa funzionare tutto sta in `class/Docs/`:

| Classe | Ruolo |
|---|---|
| `ComponentDoc`, `Example` | il DSL delle schede |
| `Catalog` | legge le cartelle, ordina, risolve gli slug; `addPath()` aggiunge le schede di un sito o di un modulo |
| `ThemeSupport` | chiede al `Themes\Resolver` in quali temi esiste il renderer: la disponibilità **non si scrive a mano** |
| `Snippet`, `ExampleRunner` | preparano ed eseguono il codice dell'esempio: ciò che vedi è ciò che gira |
| `ApiReference` | i metodi pubblici della classe, letti con la reflection |
| `CatalogPage`, `PreviewPage`, `Pages`, `Urls` | i dati delle pagine, uguali per backend e server autonomo |
| `Server` | il router di `php bin/docs.php` |

Le viste sono in `app/view/pages/docs/` (indice, scheda, anteprima e barra
laterale) e sono condivise dai due host; il backend le incarta nel suo layout
(`app/view/pages/backend/docs/components.php`), il server autonomo in
`app/view/layout/docs/base.php`.

## Scrivere una scheda

Un file per componente, nella cartella della categoria, che **ritorna** un
`ComponentDoc`. Tutto in italiano.

```php
<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Components\Badge;
use Wonder\Elements\Components\ButtonGroup;

return ComponentDoc::for(Badge::class)
    ->title('Badge')
    ->group('feedback')
    ->order(20)
    ->tags('etichetta', 'stato', 'pill')
    ->description('Un\'etichetta compatta per stati e contatori. Con un `href` diventa un link.')
    ->uses(ButtonGroup::class)
    ->docs('concetti/componenti/README.md#esempio-button-badge-group-dropdown', 'Componenti UI')
    ->related('button')
    ->note('wonder', 'Usa le classi `badge-<variante>` e `badge-<variante>-o` della lib.')
    ->example('Base', <<<'PHP'
    Badge::make('Bozza')
    PHP)
    ->example('Varianti', <<<'PHP'
    ButtonGroup::make([
        Badge::make('Nuovo')->variant('primary'),
        Badge::make('Bozza')->variant('secondary')->outline(),
        Badge::make('12')->variant('danger')->pill(),
    ])
    PHP, 'Il nome della variante è lo stesso nei due temi.')
    ->example(
        Example::make('Solo Bootstrap')
            ->code(<<<'PHP'
            Badge::make('Backend')->variant('dark')
            PHP)
            ->themes('bootstrap')
            ->height(80)
    );
```

### Le regole

- **`for(Classe::class)`** è l'Element documentato. Titolo e slug (kebab-case
  del nome corto, per esempio `input-text`) si derivano da lì; `title()` e
  `slug()` li cambiano. Gli slug sono unici nel catalogo: se due Element hanno
  lo stesso nome corto (il `Button` dei form e quello dei componenti) una delle
  due schede dichiara lo slug, per esempio `form-button`.
- **`group()`** sceglie il gruppo dentro la categoria, `order()` l'ordine nel
  gruppo (più basso, più in alto). Senza gruppo la scheda sta in testa alla
  categoria.
- **`description()`** è testo semplice: niente HTML. Le parti fra apici inversi
  diventano `<code>`; una riga vuota separa i paragrafi. Dice cos'è il
  componente, quando si usa e cosa lo distingue nei due temi.
- **`uses(...)`** elenca le classi che gli esempi nominano oltre al componente:
  la riga `use` corrispondente viene aggiunta in testa al codice mostrato (e
  eseguito) quando lo snippet la usa. Non serve ripetere `use` negli esempi.
- **`example()`** accetta `('Titolo', $codice, 'descrizione')` oppure un
  `Example` configurato (`themes()`, `height()`). Il codice è un nowdoc
  (`<<<'PHP'`) **senza `<?php`** e senza `return`: l'ultima istruzione è
  l'espressione che produce l'Element (o un array di Element, o una stringa
  HTML). Prima possono esserci altre istruzioni (variabili, cicli). Un `echo`
  esplicito funziona lo stesso: l'output viene catturato.
- **`Example::themes('bootstrap')`** limita un esempio a un tema, quando usa
  un'API che esiste solo lì (`Accordion::link()`, `Modal::frontend()`). La
  scheda dell'altro tema mostra il motivo al posto dell'anteprima.
- **`Example::height(px)`** dà all'anteprima un'altezza minima: serve a ciò
  che si apre sopra il contenuto (dropdown, calendari, modal).
- **`note('tema', ...)`** racconta una differenza di comportamento in quel
  tema; compare nella scheda e, come tooltip, nell'indice.
- **`unsupported('tema', 'motivo')`** forza un tema come non disponibile quando
  il renderer esiste ma non rende davvero il componente. È l'eccezione: di
  norma la disponibilità la calcola `ThemeSupport`.
- **`docs()`** rimanda alla guida GitBook (percorso relativo a `docs/app/` o
  URL), **`related()`** ad altre schede per slug, **`deprecated()`** segna il
  componente da non usare in codice nuovo.
- Per le immagini d'esempio usa `Wonder\Docs\Assets::url('paesaggio-1.jpg')`
  (file in `resources/assets/docs/`, raggiunti allo stesso URL in un sito e
  nel server autonomo) e aggiungi `Assets::class` a `uses()`. Le tre JPEG
  `paesaggio-1..3` hanno accanto le varianti responsive (`-240` ... `-2400`,
  `.jpg` e `.webp`) che `Image`, `Gallery` e `Swiper` si aspettano; gli SVG
  (`quadrato-*`, `ritratto-1`) servono per loghi e icone e restano un
  semplice `<img>`.

### Validare

```bash
php tests/Docs/CatalogRenderTest.php     # ogni esempio si rende nei temi disponibili
php bin/docs.php                          # poi apri http://127.0.0.1:8090/ e guarda le anteprime
```

Il test ferma il commit su un esempio che lancia un'eccezione o produce HTML
vuoto; l'anteprima nella pagina mostra comunque l'errore al posto del
componente, così la scheda resta leggibile mentre la correggi.

## Perché un solo catalogo per i due temi

Wonder e Bootstrap hanno fogli di stile che non convivono sulla stessa pagina
(`.btn`, `.badge`, `.modal` esistono in entrambi con regole diverse). Per
questo ogni anteprima è un `<iframe>` che carica una pagina a sé con i soli
asset del tema scelto: la scheda resta una, con le schede Wonder/Bootstrap
sopra ogni esempio e un selettore in testa alla pagina che le cambia tutte
insieme (la scelta resta in `localStorage`). Dove un componente o un'API
esiste in un tema solo, la scheda lo dice al posto dell'anteprima invece di
mostrare un markup sbagliato.

Due componenti nati con il catalogo sono riusabili ovunque nel backend e nel
frontend: `Wonder\Elements\Components\Code` (blocco di codice con
evidenziazione server-side e bottone "copia") e
`Wonder\Elements\Components\Preview` (finestra di anteprima con sorgenti
selezionabili e passaggio chiaro/scuro). Sono documentati nel catalogo stesso,
gruppo "Documentazione".

## GitBook

La guida in `docs/app/` resta su GitBook per i concetti; non può eseguire PHP
né caricare la lib, quindi non ospita le anteprime. La pagina
[Componenti UI → Catalogo](../app/concetti/componenti/catalogo.md) spiega come
aprirlo. Se un'istanza del catalogo sarà pubblica, GitBook può incorporarla
con un embed.
