# Table Column Formatter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere a `wonder-image/app` una API `TableColumn::formatter('nome')` che rende una cella tabella tramite un formatter con nome registrato (riceve l'intera riga), e usarla nel modulo `immobili` per miniatura, `mq`, prezzo arrotondato/`/mese` e nome con sottotitolo, più la stella evidenza come `badgeIcon`.

**Architecture:** Un `ColumnFormatterRegistry` (nome→callable) risolto server-side funge da whitelist; `Column::formatter()` salva il nome nello schema colonna, `ResourceTableRenderer` lo propaga in `$format`, e `Field::setValue()` invoca il formatter con la riga. I moduli registrano i formatter tramite il meccanismo `boot.files` del `module.json` (già esistente). Le closure non possono attraversare il giro AJAX di `list-table` (POST `fields`), quindi il nome è l'unico dato che viaggia.

**Tech Stack:** PHP 8.2, framework `wonder-image/app` (Composer), modulo `wonder-image/immobili` (Composer path-symlinked nel sito `immobili-site`), DataTables SSP.

## Global Constraints

- I test del framework sono **script PHP semplici** (no PHPUnit): `require vendor/autoload.php`, helper `eq()`, `exit(0)` se passano. Si eseguono con `php tests/<path>Test.php` dalla root del repo. Copiare questo stile.
- Non modificare mai `vendor/` nel sito. Il lavoro framework sta in `packages/app`; il lavoro modulo in `packages/immobili`.
- **Nessuna nuova colonna DB.** Rielaborare i dati esistenti.
- Ogni formatter fa l'**escape** dei campi riga che inserisce (`htmlspecialchars(..., ENT_QUOTES)`), ed è **difensivo** sui campi mancanti (`?? ''`).
- Consegna al sito: commit su branch dedicato in `packages/app`, merge in `main`, **push su origin (outward-facing: richiede conferma esplicita dell'utente)**, poi `composer update wonder-image/app` nel sito.
- `php -l` su ogni file PHP toccato; `composer dump-autoload` nel repo dove si aggiungono classi nuove.

---

### Task 1: `ColumnFormatterRegistry` (framework)

**Files:**
- Create: `class/Backend/Table/ColumnFormatterRegistry.php`
- Test: `tests/Backend/Table/ColumnFormatterRegistryTest.php`

**Interfaces:**
- Produces: `Wonder\Backend\Table\ColumnFormatterRegistry` con
  `register(string $name, callable $formatter): void`,
  `has(string $name): bool`,
  `call(string $name, array $row): string`,
  `reset(): void`.

- [ ] **Step 1: Write the failing test**

Create `tests/Backend/Table/ColumnFormatterRegistryTest.php`:

```php
<?php
/** php tests/Backend/Table/ColumnFormatterRegistryTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Table\ColumnFormatterRegistry;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

ColumnFormatterRegistry::reset();

eq('unknown not registered', ColumnFormatterRegistry::has('immobili.prezzo'), false);
eq('call unknown returns empty', ColumnFormatterRegistry::call('immobili.prezzo', ['prezzo' => 10]), '');

ColumnFormatterRegistry::register('immobili.prezzo', static fn (array $row): string => '€ ' . (int) ($row['prezzo'] ?? 0));
eq('registered', ColumnFormatterRegistry::has('immobili.prezzo'), true);
eq('call runs with row', ColumnFormatterRegistry::call('immobili.prezzo', ['prezzo' => 255000]), '€ 255000');
eq('trimmed lookup', ColumnFormatterRegistry::has(' immobili.prezzo '), true);

// il valore di ritorno è castato a stringa
ColumnFormatterRegistry::register('n', static fn (array $row) => 42);
eq('return cast to string', ColumnFormatterRegistry::call('n', []), '42');

ColumnFormatterRegistry::reset();
eq('reset clears', ColumnFormatterRegistry::has('immobili.prezzo'), false);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/Backend/Table/ColumnFormatterRegistryTest.php`
Expected: FAIL — `Class "Wonder\Backend\Table\ColumnFormatterRegistry" not found`.

- [ ] **Step 3: Write the implementation**

Create `class/Backend/Table/ColumnFormatterRegistry.php`:

```php
<?php

namespace Wonder\Backend\Table;

/**
 * Registry dei formatter di colonna invocabili via TableColumn::formatter().
 * Il nome viaggia nel POST di list-table (giro DataTables): il registry è la
 * whitelist — un nome non registrato non viene mai invocato. Le callable vivono
 * solo server-side (mai serializzate).
 */
final class ColumnFormatterRegistry
{
    /** @var array<string, callable> */
    private static array $formatters = [];

    public static function register(string $name, callable $formatter): void
    {
        $name = trim($name);

        if ($name !== '') {
            self::$formatters[$name] = $formatter;
        }
    }

    public static function has(string $name): bool
    {
        return isset(self::$formatters[trim($name)]);
    }

    /** Invoca il formatter registrato; '' se il nome non è registrato. */
    public static function call(string $name, array $row): string
    {
        $name = trim($name);

        if (!isset(self::$formatters[$name])) {
            return '';
        }

        return (string) (self::$formatters[$name])($row);
    }

    public static function reset(): void
    {
        self::$formatters = [];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/Backend/Table/ColumnFormatterRegistryTest.php`
Expected: PASS — `ALL PASS`.

- [ ] **Step 5: Commit**

```bash
git add class/Backend/Table/ColumnFormatterRegistry.php tests/Backend/Table/ColumnFormatterRegistryTest.php
git commit -m "feat(table): ColumnFormatterRegistry per formatter di colonna con nome"
```

---

### Task 2: `Column::formatter()` + rimozione `callback()` morto (framework)

**Files:**
- Modify: `class/Elements/Table/Column.php` (aggiungi `formatter()`, rimuovi `callback()`)
- Test: `tests/App/ResourceSchema/TableColumnFormatterTest.php`

**Interfaces:**
- Consumes: nulla.
- Produces: `Wonder\App\ResourceSchema\TableColumn::formatter(string $name): self` (ereditato da `Column`), che imposta `schema['formatter']`.

- [ ] **Step 1: Write the failing test**

Create `tests/App/ResourceSchema/TableColumnFormatterTest.php`:

```php
<?php
/** php tests/App/ResourceSchema/TableColumnFormatterTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\ResourceSchema\TableColumn;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

$schema = TableColumn::key('nome')->formatter('immobili.nome')->toArray();
eq('formatter salvato nello schema', $schema['formatter'] ?? null, 'immobili.nome');
eq('name preservato', $schema['name'] ?? null, 'nome');

// il metodo morto callback() non deve più esistere
eq('callback() rimosso', method_exists(TableColumn::class, 'callback'), false);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/App/ResourceSchema/TableColumnFormatterTest.php`
Expected: FAIL — `Call to undefined method ...::formatter()` (o `callback() rimosso` fallisce perché ancora presente).

- [ ] **Step 3: Modify `class/Elements/Table/Column.php`**

Rimuovere il metodo morto (righe ~53-56):

```php
    public function callback($callback): self
    {
        return $this->schema('callback', $callback);
    }
```

e sostituirlo con:

```php
    public function formatter(string $name): self
    {
        return $this->schema('formatter', trim($name));
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/App/ResourceSchema/TableColumnFormatterTest.php`
Expected: PASS — `ALL PASS`.

- [ ] **Step 5: Commit**

```bash
git add class/Elements/Table/Column.php tests/App/ResourceSchema/TableColumnFormatterTest.php
git commit -m "feat(table): Column::formatter(); rimosso callback() morto"
```

---

### Task 3: Passthrough nel renderer + dispatch in `Field` (framework)

**Files:**
- Modify: `class/Backend/Support/ResourceTableRenderer.php` (metodo `legacyColumnFormat`)
- Modify: `class/Backend/Table/Field.php` (metodo `setValue`)
- Modify: `docs/app/backend/table-columns.md` (nota sul formatter — creare se assente)
- Test: `tests/Backend/Table/FieldFormatterTest.php`

**Interfaces:**
- Consumes: `ColumnFormatterRegistry` (Task 1), `TableColumn::formatter()` (Task 2).
- Produces: rendering: se `$format['formatter']` è registrato, la cella = output del formatter; se non registrato, cella vuota.

- [ ] **Step 1: Write the failing test**

Create `tests/Backend/Table/FieldFormatterTest.php`:

```php
<?php
/** php tests/Backend/Table/FieldFormatterTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Table\Field;
use Wonder\Backend\Table\ColumnFormatterRegistry;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

function makeField(): Field {
    $TABLE = (object) [
        'id' => 'tbl-1', 'table' => 'immobili', 'connection' => null, 'database' => 'main',
        'field' => [], 'page' => 0, 'length' => 10, 'link' => [],
    ];
    $PATH = (object) [ 'site' => '', 'backend' => '/backend', 'app' => '/app', 'api' => '/api' ];
    $TEXT = (object) [
        'titleS' => 'immobile', 'titleP' => 'immobili', 'last' => 'ultimi', 'all' => 'tutti',
        'article' => 'gli', 'full' => 'pieno', 'empty' => 'vuoto', 'this' => 'questo',
    ];
    $USER = (object) [ 'area' => '', 'authority' => '' ];
    $PAGE = (object) [ 'redirect' => '', 'redirectBase64' => '' ];
    return new Field($TABLE, $PATH, $TEXT, $USER, $PAGE);
}

ColumnFormatterRegistry::reset();
ColumnFormatterRegistry::register('immobili.prezzo', static fn (array $row): string =>
    '€ ' . number_format((int) ($row['prezzo'] ?? 0), 0, ',', '.'));

// formatter registrato: la cella usa il suo output, riceve tutta la riga
$field = makeField();
$got = $field->newField(
    ['id' => 5, 'prezzo' => 255000, 'contratto_id' => 'V'],
    'prezzo',
    ['format' => 'text', 'formatter' => 'immobili.prezzo']
);
eq('formatter registrato rende la cella', $got, '€ 255.000');

// formatter NON registrato: cella vuota, nessuna esecuzione
$field = makeField();
$got = $field->newField(
    ['id' => 5, 'prezzo' => 255000],
    'prezzo',
    ['format' => 'text', 'formatter' => 'immobili.non_registrato']
);
eq('formatter non registrato -> vuoto', $got, '');

ColumnFormatterRegistry::reset();

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/Backend/Table/FieldFormatterTest.php`
Expected: FAIL — `formatter registrato rende la cella` non passa (dispatch assente: la cella cade nel valore semplice, `''` o il valore grezzo).

- [ ] **Step 3: Passthrough in `ResourceTableRenderer::legacyColumnFormat()`**

In `class/Backend/Support/ResourceTableRenderer.php`, dentro `legacyColumnFormat(array $config)`, accanto ai passaggi esistenti (`function`, `badge`, `link`), aggiungere prima del `return $format;`:

```php
        if (isset($config['formatter']) && is_string($config['formatter']) && trim($config['formatter']) !== '') {
            $format['formatter'] = trim($config['formatter']);
        }
```

- [ ] **Step 4: Dispatch in `Field::setValue()`**

In `class/Backend/Table/Field.php`, nel metodo `setValue($format)`, subito **dopo** il blocco badge:

```php
                # Render badge booleano (API dichiarativa, contesto iniettato — mai global)
                if (isset($format['badge']) && is_array($format['badge'])) {

                    return $this->setBadgeValue($format, $COLUMN_VALUE);

                }
```

inserire il dispatch del formatter (prima del blocco `# Set value from function`):

```php
            # Render via formatter con nome (riceve l'intera riga). Il registry è
            # la whitelist: nome non registrato => cella vuota, mai esecuzione
            # arbitraria. Il formatter possiede l'intera cella (nessuna
            # formattazione-tipo aggiuntiva, nessun href-wrap).
                if (isset($format['formatter']) && is_string($format['formatter']) && trim($format['formatter']) !== '') {

                    return \Wonder\Backend\Table\ColumnFormatterRegistry::has($format['formatter'])
                        ? \Wonder\Backend\Table\ColumnFormatterRegistry::call($format['formatter'], $this->row)
                        : '';

                }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/Backend/Table/FieldFormatterTest.php`
Expected: PASS — `ALL PASS`.

- [ ] **Step 6: Verify nessuna regressione sui test tabella esistenti**

Run:
```bash
php tests/Backend/Table/FieldFunctionWhitelistTest.php
php tests/Backend/Table/FieldBadgeLegacyRemapTest.php
php tests/App/ResourceSchema/TableColumnBadgeTest.php
```
Expected: tutti `ALL PASS`.

- [ ] **Step 7: Documentare**

Aggiungere a `docs/app/backend/table-columns.md` (creare il file se non esiste) una sezione breve: `TableColumn::formatter('nome')`, registrazione via `ColumnFormatterRegistry::register('nome', fn(array $row): string => ...)`, vincolo "nomi non closure per via del giro AJAX", responsabilità di escape del formatter, e che i moduli registrano via `boot.files`.

- [ ] **Step 8: Commit**

```bash
git add class/Backend/Support/ResourceTableRenderer.php class/Backend/Table/Field.php tests/Backend/Table/FieldFormatterTest.php docs/app/backend/table-columns.md
git commit -m "feat(table): dispatch formatter con nome in Field + passthrough renderer + docs"
```

---

### Task 4: helper prezzo lista (`immobili`)

**Files:**
- Modify: `src/helpers.php` (aggiungi `immobiliListPrice`)
- Test: `tests/list-price.php`

**Interfaces:**
- Consumes: `immobiliFormatPrice()`, `immobiliIsTrue()` (già in `src/helpers.php`).
- Produces: `immobiliListPrice(array $row): string`.

- [ ] **Step 1: Write the failing test**

Create `tests/list-price.php`:

```php
<?php
/** php tests/list-price.php */
declare(strict_types=1);

require __DIR__ . '/../src/helpers.php';

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    if ($got !== $expected) { $fail++; echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  got:      " . var_export($got, true) . "\n"; }
    else { echo "ok: $label\n"; }
}

// vendita
eq('vendita', immobiliListPrice(['contratto_id' => 'V', 'prezzo' => 255000]), '€ 255.000');
// affitto: prezzo_affitto + /mese
eq('affitto', immobiliListPrice(['contratto_id' => 'A', 'prezzo_affitto' => 1200]), '€ 1.200 /mese');
// affitto senza prezzo_affitto: fallback su prezzo
eq('affitto fallback prezzo', immobiliListPrice(['contratto_id' => 'A', 'prezzo' => 900]), '€ 900 /mese');
// trattativa riservata (vendita)
eq('riservata vendita', immobiliListPrice(['contratto_id' => 'V', 'prezzo' => 0, 'trattativa_riservata' => 'true']), 'Trattativa riservata');
// trattativa riservata affitto
eq('riservata affitto', immobiliListPrice(['contratto_id' => 'A', 'trattativa_riservata_affitto' => 'true']), 'Trattativa riservata');
// prezzo assente e non riservato
eq('vuoto -> trattino', immobiliListPrice(['contratto_id' => 'V', 'prezzo' => 0]), '—');

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/list-price.php`
Expected: FAIL — `Call to undefined function immobiliListPrice()`.

- [ ] **Step 3: Add `immobiliListPrice` in `src/helpers.php`**

Aggiungere (accanto agli altri helper, dopo `immobiliFormatPrice`):

```php
if (!function_exists('immobiliListPrice')) {
    /**
     * Prezzo formattato per la lista backend. Vendita usa `prezzo`; affitto
     * (`contratto_id` = 'A') usa `prezzo_affitto` (fallback `prezzo`) con `/mese`.
     * "Trattativa riservata" se il flag riservato è attivo; "—" se manca il prezzo.
     */
    function immobiliListPrice(array $row): string
    {
        $isRent = strtoupper(trim((string) ($row['contratto_id'] ?? ''))) === 'A';

        if ($isRent) {
            if (immobiliIsTrue($row['trattativa_riservata_affitto'] ?? '')) {
                return 'Trattativa riservata';
            }
            $price = immobiliFormatPrice($row['prezzo_affitto'] ?? 0);
            if ($price === '') {
                $price = immobiliFormatPrice($row['prezzo'] ?? 0);
            }
            return $price === '' ? '—' : $price . ' /mese';
        }

        if (immobiliIsTrue($row['trattativa_riservata'] ?? '')) {
            return 'Trattativa riservata';
        }
        $price = immobiliFormatPrice($row['prezzo'] ?? 0);
        return $price === '' ? '—' : $price;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/list-price.php`
Expected: PASS — `ALL PASS`.

- [ ] **Step 5: Commit** (nel repo `packages/immobili`)

```bash
git add src/helpers.php tests/list-price.php
git commit -m "feat(immobili): immobiliListPrice per la colonna prezzo della lista"
```

---

### Task 5: `ImmobilePresenter::coverImage()` (`immobili`)

**Files:**
- Modify: `src/Services/ImmobilePresenter.php` (aggiungi metodo pubblico `coverImage`)

**Interfaces:**
- Consumes: metodo privato esistente `images(int $immobileId): array` (voci con `src`, `processed`, e tipo foto/planimetria).
- Produces: `ImmobilePresenter::coverImage(array $row): string` — URL della prima foto copertina processata, `''` se assente.

- [ ] **Step 1: Leggere il presenter**

Leggere `src/Services/ImmobilePresenter.php`, in particolare `images()` (~riga 223) e come vengono separate foto/planimetrie, per confermare la forma delle voci (`src`, `processed`, eventuale `tipo`) e come il presenter viene costruito altrove (costruttore/dipendenze).

- [ ] **Step 2: Aggiungere `coverImage`**

Aggiungere il metodo pubblico (riusando `images()`; adattare il filtro foto/planimetria a ciò che `images()` restituisce — se `images()` ritorna già solo foto, il filtro `tipo` è superfluo):

```php
    /**
     * URL della foto copertina (prima immagine processata) per la lista backend.
     * Riusa images(): stessa risoluzione URL webp/resized del frontend.
     */
    public function coverImage(array $row): string
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return '';
        }

        foreach ($this->images($id) as $img) {
            if (!empty($img['processed']) && (string) ($img['src'] ?? '') !== '') {
                return (string) $img['src'];
            }
        }

        return '';
    }
```

- [ ] **Step 3: Lint**

Run: `php -l src/Services/ImmobilePresenter.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit** (repo `packages/immobili`)

```bash
git add src/Services/ImmobilePresenter.php
git commit -m "feat(immobili): ImmobilePresenter::coverImage per la miniatura in lista"
```

Nota: la verifica funzionale (URL corretto) avviene end-to-end nel Task 8.

---

### Task 6: Consegna del framework al sito

**Files:** nessun file di codice nuovo. Operazioni git/composer.

**Interfaces:**
- Consumes: Task 1-3 committati su un branch di `packages/app`.
- Produces: `vendor/wonder-image/app` aggiornato nel sito con `ColumnFormatterRegistry` + `formatter()`.

- [ ] **Step 1: Portare il lavoro framework su un branch dedicato**

In `packages/app`, i Task 1-3 vanno su un branch dedicato (es. `feat/table-column-formatter`), **non** su `fix/skills-all-flag-agent-dir`. Se i commit sono finiti sul branch sbagliato, spostarli:
```bash
cd /Users/andreamarinoni/Developer/packages/app
git switch -c feat/table-column-formatter   # se non già creato prima del Task 1
```

- [ ] **Step 2: Merge in `main` e push (CONFERMA UTENTE)**

Il push è outward-facing: chiedere conferma esplicita all'utente prima di eseguirlo.
```bash
cd /Users/andreamarinoni/Developer/packages/app
git switch main
git merge --no-ff feat/table-column-formatter
git push origin main
```

- [ ] **Step 3: Aggiornare il sito**

```bash
cd /Users/andreamarinoni/Developer/boilerplates/immobili-site
composer update wonder-image/app
```
Expected: `wonder-image/app` aggiornato a `dev-main` con l'ultimo commit.

- [ ] **Step 4: Verificare che la classe risolva nel sito**

```bash
cd /Users/andreamarinoni/Developer/boilerplates/immobili-site
php -r "require 'vendor/autoload.php'; var_dump(class_exists('Wonder\\\\Backend\\\\Table\\\\ColumnFormatterRegistry'), method_exists('Wonder\\\\App\\\\ResourceSchema\\\\TableColumn','formatter'));"
```
Expected: `bool(true)` due volte.

---

### Task 7: Boot file dei formatter del modulo (`immobili`)

**Files:**
- Create: `config/formatters.php`
- Modify: `module.json` (aggiungi `boot.files`)

**Interfaces:**
- Consumes: `ColumnFormatterRegistry` (framework, ora nel sito), `immobiliListPrice`/`immobiliFormatSurface` (Task 4 e helper esistente), `ImmobilePresenter::coverImage` (Task 5), `__r()` (framework).
- Produces: registrazione dei formatter `immobili.superficie`, `immobili.prezzo`, `immobili.miniatura`, `immobili.nome` ad ogni request (initial + AJAX).

- [ ] **Step 1: Creare `config/formatters.php`**

```php
<?php

use Wonder\Backend\Table\ColumnFormatterRegistry;
use Wonder\Plugin\Immobili\Services\ImmobilePresenter;

if (!class_exists(ColumnFormatterRegistry::class)) {
    return; // difensivo: framework non ancora aggiornato
}

ColumnFormatterRegistry::register('immobili.superficie', static fn (array $row): string =>
    immobiliFormatSurface($row['superficie'] ?? 0));

ColumnFormatterRegistry::register('immobili.prezzo', static fn (array $row): string =>
    immobiliListPrice($row));

ColumnFormatterRegistry::register('immobili.miniatura', static function (array $row): string {
    $src = (new ImmobilePresenter())->coverImage($row);
    if ($src === '') {
        return '';
    }
    $src = htmlspecialchars($src, ENT_QUOTES);
    $alt = htmlspecialchars((string) ($row['nome'] ?? ''), ENT_QUOTES);
    return "<img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\" "
        . "style=\"width:56px;height:42px;object-fit:cover;border-radius:6px;flex:0 0 auto\">";
});

ColumnFormatterRegistry::register('immobili.nome', static function (array $row): string {
    $id    = (int) ($row['id'] ?? 0);
    $edit  = htmlspecialchars((string) __r('backend.resource.immobili.edit', ['id' => $id]), ENT_QUOTES);
    $nome  = htmlspecialchars((string) ($row['nome'] ?? ''), ENT_QUOTES);

    $tipologia = trim((string) ($row['tipologia_nome'] ?? ''));
    $strada    = ucwords(strtolower(trim((string) ($row['strada'] ?? ''))));
    $indirizzo = trim((string) ($row['indirizzo'] ?? ''));
    $comune    = trim((string) ($row['comune_nome'] ?? ''));

    $via = trim($strada . ' ' . $indirizzo);
    $loc = trim($via . ($comune !== '' ? ', ' . $comune : ''), ', ');
    $sub = $tipologia;
    if ($loc !== '') {
        $sub = $sub !== '' ? $sub . ' | ' . $loc : $loc;
    }
    $sub = htmlspecialchars($sub, ENT_QUOTES);

    // Miniatura ripiegata nella cella nome (niente colonna virtuale)
    $thumb = ColumnFormatterRegistry::call('immobili.miniatura', $row);

    return "<a href=\"{$edit}\" class=\"d-flex align-items-center gap-2 text-dark text-decoration-none\">"
        . $thumb
        . "<span class=\"d-inline-block\">"
        . "<span class=\"fw-semibold d-block\">{$nome}</span>"
        . ($sub !== '' ? "<span class=\"d-block text-muted small\">{$sub}</span>" : '')
        . "</span></a>";
});
```

- [ ] **Step 2: Dichiarare il boot file in `module.json`**

Aggiungere la chiave `boot` (accanto a `routes`/`permissions`/`database`):

```json
    "boot": {
        "files": ["config/formatters.php"]
    },
```

- [ ] **Step 3: Lint**

Run: `php -l config/formatters.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Verificare che i formatter siano registrati al bootstrap del sito**

Caricare qualsiasi pagina backend del sito (o eseguire un piccolo harness che includa il bootstrap) e verificare:
```bash
cd /Users/andreamarinoni/Developer/boilerplates/immobili-site
php -r "require 'vendor/autoload.php'; \Wonder\App\Module\Registry::bootFiles(); foreach ((array) \Wonder\App\Module\Registry::bootFiles() as \$f) require \$f; var_dump(\Wonder\Backend\Table\ColumnFormatterRegistry::has('immobili.prezzo'));" 2>&1 | tail -3
```
Expected: include il boot file e stampa `bool(true)`. Se il bootstrap completo non è disponibile da CLI, verificare via caricamento pagina nel Task 8.

- [ ] **Step 5: Commit** (repo `packages/immobili`)

```bash
git add config/formatters.php module.json
git commit -m "feat(immobili): registra i formatter di colonna via boot.files"
```

---

### Task 8: `ImmobileResource` tableSchema/labelSchema + verifica end-to-end (`immobili`)

**Files:**
- Modify: `src/Resources/ImmobileResource.php` (`tableSchema`, `labelSchema`)

**Interfaces:**
- Consumes: formatter registrati (Task 7), `badgeIcon` (già nel framework).
- Produces: lista immobili conforme allo screenshot.

- [ ] **Step 1: Aggiornare `tableSchema()`**

Sostituire il corpo di `tableSchema()` con:

```php
    public static function tableSchema(): array
    {
        return [
            // Prima colonna: stella "in evidenza" come icona-badge (vuota se non
            // in evidenza, cliccabile per rimuovere; per attivarla usa il menu «…»).
            TableColumn::key('evidence')->evidenceBadge(true)->badgeVariant('badgeIcon')->size('little'),
            // Nome: miniatura + riferimento + sottotitolo (tipologia | indirizzo, comune) con link alla modifica.
            TableColumn::key('nome')->formatter('immobili.nome')->size('big'),
            TableColumn::key('comune_nome')->text()->size('medium'),
            TableColumn::key('prezzo')->formatter('immobili.prezzo'),
            TableColumn::key('superficie')->formatter('immobili.superficie')->size('little'),
            TableColumn::key('creation')->datetime()->sortable(),
            TableColumn::key('sold')->booleanBadge('sold')
                ->badgeOff('In vendita', 'bi bi-tag', 'primary')
                ->badgeOn('Venduto', 'bi bi-check2-circle', 'dark')
                ->size('little'),
            TableColumn::key('visible')->visibleBadge(true)->size('little'),
            TableColumn::key('actions')->button()->actions(['edit', 'visible', 'evidence', 'delete']),
        ];
    }
```

- [ ] **Step 2: Lint**

Run: `php -l src/Resources/ImmobileResource.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Verifica end-to-end nel sito in esecuzione**

Aprire la lista immobili nel backend (`https://immobili.test/backend/immobili/...`, utente `admin`) e confermare visivamente:
1. Nessun warning PHP.
2. Prima colonna: stella gialla (`badgeIcon`) solo sulle righe in evidenza; cliccabile.
3. Colonna nome: miniatura foto + riferimento in grassetto + sottotitolo `tipologia | Via ..., Comune`, cliccabile → apre la scheda modifica.
4. Prezzo: arrotondato senza decimali (`€ 255.000`); affitti con `/mese`.
5. Superficie: `48 mq`.
6. Stato: badge `In vendita` (blu) / `Venduto`.
7. Ordinando per «Inserimento» o cercando (refresh AJAX), i formatter restano applicati (verifica che il giro DataTables mantenga miniatura/prezzo/superficie/nome).

- [ ] **Step 4: Commit** (repo `packages/immobili`)

```bash
git add src/Resources/ImmobileResource.php
git commit -m "feat(immobili): lista con miniatura, prezzo/mq formattati, stella badgeIcon"
```

---

## Self-Review

**Spec coverage:** registry (T1) ✓, `formatter()`+rimozione `callback()` (T2) ✓, passthrough+dispatch+docs (T3) ✓, registrazione via `boot.files` (T7) ✓, formatter prezzo/superficie/miniatura/nome (T4-T7) ✓, `badgeIcon` (T8) ✓, consegna commit/push dev-main (T6) ✓, verifica end-to-end (T8) ✓. Nessuna nuova colonna DB ✓.

**Placeholder scan:** nessun TBD/TODO; ogni step ha codice/comandi concreti. Gli unici punti "verifica sui dati" (semantica `strada`/`indirizzo`, forma di `images()`) sono step di lettura espliciti con codice concreto a valle, non placeholder.

**Type consistency:** `ColumnFormatterRegistry::{register,has,call,reset}`, `TableColumn::formatter(string): self`, `immobiliListPrice(array): string`, `ImmobilePresenter::coverImage(array): string`, nomi formatter `immobili.{superficie,prezzo,miniatura,nome}` — coerenti tra i task.
