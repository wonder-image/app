# Repeater raggruppato — Piano di lavoro

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dare al repeater del backend le righe raggruppate — selettore
"Raggruppa per", testate richiudibili, casella-comando che scrive sulle righe
del gruppo — senza cambiare niente per i repeater che non la chiedono. E
correggere l'Accordion, che dentro un form schiaccia i campi.

**Architecture:** Quattro setter su `InputRepeater` finiscono in `context`. Una
classe pura `App\Support\RepeaterGroups` risolve valore ed etichetta di ogni
riga per ogni colonna raggruppabile; il renderer Bootstrap li stampa come
attributi `data-` sulle righe e aggiunge la barra del selettore più un
`<template>` per le testate. Il JS inline costruisce le testate e riordina con
`order` di flexbox: il DOM delle righe non si sposta mai, quindi posting,
`positionKey` e riordino restano quelli di oggi. L'Accordion prende sul proprio
corpo la griglia che la Card mette sul `card-body`.

**Tech Stack:** PHP 8.2, tema Bootstrap (`class/Themes/Bootstrap/`), JS inline
nel renderer (niente `wonder-image/lib`), harness di test del repo
(`tests/harness.php`, `check()`, un file per argomento eseguito con
`php tests/...Test.php`), verifica finale da un sito.

**Spec:** [2026-09-22-repeater-raggruppato-design.md](../specs/2026-09-22-repeater-raggruppato-design.md)

## Global Constraints

- Lingua: **italiano** in etichette, commenti e testi visibili. **Inglese** per
  nomi di classi, metodi, variabili, chiavi di `context` e attributi `data-`.
- Il **markup delle righe** di un repeater che non dichiara `repeaterGroupBy()`
  non cambia di un carattere. Il blocco `<script>`, che è condiviso, guadagna le
  funzioni nuove: senza raggruppamento non fanno niente.
- Le funzioni JS si definiscono con la guardia già in uso:
  `window.x = window.x || function (...) {...}`.
- Le colonne arrivano in **due forme** — `Input` (da `RepeaterColumn::key()`) o
  array descrittore (`['name' => ..., 'helper' => ..., 'options' => [...]]`):
  ogni funzione nuova le gestisce entrambe, come fa già `renderColumn()`.
- Niente modifiche a `wonder-image/lib`.
- Niente selettori `querySelector` che attraversino repeater annidati: sempre
  `:scope > .wi-repeater-row` sul contenitore delle righe.
- Chiude con il tag **v.2.3.0**, che porta fuori anche il quick-create FK già
  in `main` e mai rilasciato.

---

### Task 1: `RepeaterGroups`, la classe pura

Tutta la risoluzione di valori ed etichette sta qui, dove si prova senza
browser. Il renderer e il JS diventano ignoranti.

**Files:**
- Create: `class/App/Support/RepeaterGroups.php`
- Test: `tests/Support/RepeaterGroupsTest.php`

**Interfaces:**
- Produces:
  - `RepeaterGroups::of(array $columns, array $rows, array $groupBy): array` —
    `['row_1' => ['product_variant_id' => ['value' => '10', 'label' => 'Blu']]]`
  - `RepeaterGroups::labelOf(mixed $column, mixed $value): string`
  - `RepeaterGroups::columnByKey(array $columns, string $key): mixed`
  - `RepeaterGroups::nameOf(mixed $column): string`
  - `RepeaterGroups::labelOfColumn(mixed $column): string`

- [x] **Step 1: Scrivi il test che fallisce**

`tests/Support/RepeaterGroupsTest.php`:

```php
<?php
/** php tests/Support/RepeaterGroupsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\Support\RepeaterGroups;

$colonne = [
    RepeaterColumn::key('id')->hidden(),
    RepeaterColumn::key('name')->text()->label('Versione'),
    RepeaterColumn::key('product_variant_id')
        ->select(['10' => 'Blu', '11' => 'Rosso'])
        ->label('Colore'),
];

$righe = [
    'row_1' => ['name' => 'Blu / S', 'product_variant_id' => '10'],
    'row_2' => ['name' => 'Blu / M', 'product_variant_id' => '10'],
    'row_3' => ['name' => 'Rosso / S', 'product_variant_id' => '11'],
    'row_4' => ['name' => 'Senza colore'],
];

check('ogni riga porta valore ed etichetta della colonna', function () use ($colonne, $righe) {
    $gruppi = RepeaterGroups::of($colonne, $righe, ['product_variant_id']);

    return $gruppi['row_1']['product_variant_id'] === ['value' => '10', 'label' => 'Blu']
        && $gruppi['row_3']['product_variant_id'] === ['value' => '11', 'label' => 'Rosso'];
});

check('una riga senza valore resta senza etichetta', function () use ($colonne, $righe) {
    $gruppi = RepeaterGroups::of($colonne, $righe, ['product_variant_id']);

    return $gruppi['row_4']['product_variant_id'] === ['value' => '', 'label' => ''];
});

check('un valore fuori dalle opzioni resta se stesso', function () use ($colonne) {
    $gruppi = RepeaterGroups::of($colonne, ['row_1' => ['product_variant_id' => '99']], ['product_variant_id']);

    return $gruppi['row_1']['product_variant_id'] === ['value' => '99', 'label' => '99'];
});

check('una colonna di testo si etichetta da sé', function () use ($colonne, $righe) {
    $gruppi = RepeaterGroups::of($colonne, $righe, ['name']);

    return $gruppi['row_1']['name'] === ['value' => 'Blu / S', 'label' => 'Blu / S'];
});

check('una colonna che non esiste si ignora', function () use ($colonne, $righe) {
    return RepeaterGroups::of($colonne, $righe, ['colore']) === [];
});

check('le colonne in forma di array funzionano uguale', function () use ($righe) {
    $colonne = [[
        'name' => 'product_variant_id',
        'helper' => 'select',
        'label' => 'Colore',
        'options' => ['10' => 'Blu', '11' => 'Rosso'],
    ]];

    $gruppi = RepeaterGroups::of($colonne, $righe, ['product_variant_id']);

    return $gruppi['row_2']['product_variant_id']['label'] === 'Blu';
});

check('il selettore legge nome ed etichetta della colonna', function () use ($colonne) {
    $colonna = RepeaterGroups::columnByKey($colonne, 'product_variant_id');

    return $colonna !== null
        && RepeaterGroups::nameOf($colonna) === 'product_variant_id'
        && RepeaterGroups::labelOfColumn($colonna) === 'Colore'
        && RepeaterGroups::columnByKey($colonne, 'colore') === null;
});

check('più colonne insieme danno più chiavi per riga', function () use ($colonne, $righe) {
    $gruppi = RepeaterGroups::of($colonne, $righe, ['product_variant_id', 'name']);

    return array_keys($gruppi['row_1']) === ['product_variant_id', 'name'];
});

summary();
```

- [x] **Step 2: Esegui il test e verifica che fallisca**

```bash
php tests/Support/RepeaterGroupsTest.php
```

Atteso: errore fatale, `Class "Wonder\App\Support\RepeaterGroups" not found`.

- [x] **Step 3: Scrivi la classe**

`class/App/Support/RepeaterGroups.php`:

```php
<?php

namespace Wonder\App\Support;

use Wonder\App\ResourceSchema\Input;

/**
 * Valore ed etichetta di ogni riga di un repeater, per ogni colonna su cui si
 * può raggruppare.
 *
 * Sta qui e non nel renderer perché è l'unica parte del raggruppamento che si
 * può provare senza un browser: il renderer stampa quello che questa classe
 * decide, e il JS legge senza sapere niente di opzioni ed etichette.
 *
 * Le colonne arrivano in due forme, `Input` o array descrittore, come le
 * accetta il renderer: chi chiama non deve normalizzarle prima.
 */
final class RepeaterGroups
{
    /**
     * @param array<int, mixed> $columns colonne del repeater
     * @param array<string, mixed> $rows righe, per chiave di riga
     * @param list<string> $groupBy chiavi delle colonne raggruppabili
     * @return array<string, array<string, array{value: string, label: string}>>
     */
    public static function of(array $columns, array $rows, array $groupBy): array
    {
        $groups = [];

        foreach ($groupBy as $key) {
            $key = trim((string) $key);
            $column = $key === '' ? null : self::columnByKey($columns, $key);

            if ($column === null) {
                continue;
            }

            foreach ($rows as $rowKey => $row) {
                $value = is_array($row) ? ($row[$key] ?? '') : '';
                $value = is_scalar($value) ? (string) $value : '';

                $groups[(string) $rowKey][$key] = [
                    'value' => $value,
                    'label' => self::labelOf($column, $value),
                ];
            }
        }

        return $groups;
    }

    /** L'etichetta di un valore: l'opzione se c'è, altrimenti il valore stesso. */
    public static function labelOf(mixed $column, mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        if ($value === '') {
            return '';
        }

        foreach (self::optionsOf($column) as $optionValue => $label) {
            if ((string) $optionValue === $value) {
                return is_scalar($label) ? (string) $label : $value;
            }
        }

        return $value;
    }

    /** La colonna con quella chiave, o `null`. */
    public static function columnByKey(array $columns, string $key): mixed
    {
        foreach ($columns as $column) {
            if (self::nameOf($column) === $key) {
                return $column;
            }
        }

        return null;
    }

    public static function nameOf(mixed $column): string
    {
        if ($column instanceof Input) {
            return trim($column->name);
        }

        return is_array($column) ? trim((string) ($column['name'] ?? '')) : '';
    }

    /** L'etichetta della colonna: è quella che legge il selettore. */
    public static function labelOfColumn(mixed $column): string
    {
        if ($column instanceof Input) {
            return trim((string) $column->get('label'));
        }

        return is_array($column) ? trim((string) ($column['label'] ?? '')) : '';
    }

    /** @return array<array-key, mixed> */
    private static function optionsOf(mixed $column): array
    {
        $options = $column instanceof Input
            ? $column->get('options')
            : (is_array($column) ? ($column['options'] ?? []) : []);

        return is_array($options) ? $options : [];
    }
}
```

- [x] **Step 4: Esegui il test e verifica che passi**

```bash
php tests/Support/RepeaterGroupsTest.php
```

Atteso: `8 test, 0 falliti`.

- [x] **Step 5: Commit**

```bash
git add class/App/Support/RepeaterGroups.php tests/Support/RepeaterGroupsTest.php
git commit -m "Repeater groups: pure value and label resolution"
```

---

### Task 2: I setter su `InputRepeater`

**Files:**
- Modify: `class/App/ResourceSchema/Inputs/InputRepeater.php`
- Test: `tests/Support/RepeaterGroupsContextTest.php`

**Interfaces:**
- Consumes: niente.
- Produces: `repeaterGroupBy(string ...$columnKeys)`,
  `repeaterGroupCommand(string $columnKey, string $label = '')`,
  `repeaterGroupCollapsed(bool $collapsed = true)`,
  `repeaterGroupCountLabel(string $singular, string $plural)`.
  Scrivono in `context` le chiavi `group_by`, `group_command`,
  `group_collapsed`, `group_count_label`.

- [x] **Step 1: Scrivi il test che fallisce**

`tests/Support/RepeaterGroupsContextTest.php`:

```php
<?php
/** php tests/Support/RepeaterGroupsContextTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\RepeaterColumn;

$campo = fn () => FormField::key('products')->repeater([
    RepeaterColumn::key('name')->text()->label('Versione'),
    RepeaterColumn::key('price')->number()->label('Prezzo'),
]);

check('senza setter il context non parla di gruppi', function () use ($campo) {
    $context = (array) ($campo()->get('context') ?? []);

    return !isset($context['group_by'])
        && !isset($context['group_command'])
        && !isset($context['group_collapsed']);
});

check('le colonne raggruppabili finiscono nel context', function () use ($campo) {
    $context = (array) ($campo()->repeaterGroupBy('product_variant_id', 'name')->get('context') ?? []);

    return $context['group_by'] === ['product_variant_id', 'name'];
});

check('le chiavi vuote non entrano', function () use ($campo) {
    $context = (array) ($campo()->repeaterGroupBy('product_variant_id', '', '  ')->get('context') ?? []);

    return $context['group_by'] === ['product_variant_id'];
});

check('il comando porta colonna ed etichetta', function () use ($campo) {
    $context = (array) ($campo()->repeaterGroupCommand('price', 'Prezzo del gruppo')->get('context') ?? []);

    return $context['group_command'] === ['column' => 'price', 'label' => 'Prezzo del gruppo'];
});

check('il comando senza etichetta tiene una stringa vuota', function () use ($campo) {
    $context = (array) ($campo()->repeaterGroupCommand('price')->get('context') ?? []);

    return $context['group_command'] === ['column' => 'price', 'label' => ''];
});

check('chiusi alla nascita e parole del conteggio', function () use ($campo) {
    $context = (array) ($campo()
        ->repeaterGroupCollapsed()
        ->repeaterGroupCountLabel('versione', 'versioni')
        ->get('context') ?? []);

    return $context['group_collapsed'] === true
        && $context['group_count_label'] === ['singular' => 'versione', 'plural' => 'versioni'];
});

check('i setter si concatenano e tornano il repeater', function () use ($campo) {
    $input = $campo()->repeaterGroupBy('name')->repeaterGroupCollapsed(false);

    return $input instanceof \Wonder\App\ResourceSchema\Inputs\InputRepeater
        && ((array) $input->get('context'))['group_collapsed'] === false;
});

summary();
```

- [x] **Step 2: Esegui il test e verifica che fallisca**

```bash
php tests/Support/RepeaterGroupsContextTest.php
```

Atteso: `Call to undefined method ... ::repeaterGroupBy()`.

- [x] **Step 3: Aggiungi i setter**

In `class/App/ResourceSchema/Inputs/InputRepeater.php`, dopo
`repeaterSortable()`:

```php
    /**
     * Le colonne per cui si può raggruppare, nell'ordine del selettore.
     *
     * Dichiararle non raggruppa niente: alla nascita il repeater è piatto, e
     * il raggruppamento lo sceglie chi guarda.
     */
    public function repeaterGroupBy(string ...$columnKeys): static
    {
        $keys = [];

        foreach ($columnKeys as $key) {
            $key = trim($key);

            if ($key !== '' && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys === [] ? $this : $this->context('group_by', $keys);
    }

    /**
     * La casella sulla testata del gruppo: scrive il suo valore in quella
     * colonna di ogni riga del gruppo.
     *
     * È un comando, non un dato: non viene postata e non esiste nel
     * salvataggio.
     */
    public function repeaterGroupCommand(string $columnKey, string $label = ''): static
    {
        $columnKey = trim($columnKey);

        return $columnKey === ''
            ? $this
            : $this->context('group_command', ['column' => $columnKey, 'label' => trim($label)]);
    }

    public function repeaterGroupCollapsed(bool $collapsed = true): static
    {
        return $this->context('group_collapsed', $collapsed);
    }

    /** Le parole del conteggio in testata: "4 versioni", "1 versione". */
    public function repeaterGroupCountLabel(string $singular, string $plural): static
    {
        return $this->context('group_count_label', [
            'singular' => trim($singular),
            'plural' => trim($plural),
        ]);
    }
```

- [x] **Step 4: Esegui il test e verifica che passi**

```bash
php tests/Support/RepeaterGroupsContextTest.php
```

Atteso: `7 test, 0 falliti`.

- [x] **Step 5: Commit**

```bash
git add class/App/ResourceSchema/Inputs/InputRepeater.php tests/Support/RepeaterGroupsContextTest.php
git commit -m "Repeater groups: declaration setters"
```

---

### Task 3: Il render — attributi, barra, template della testata

**Files:**
- Modify: `class/Themes/Bootstrap/Form/Components/Repeater.php`
- Test: `tests/Themes/RepeaterGroupRenderTest.php`

**Interfaces:**
- Consumes: `RepeaterGroups::of()`, `columnByKey()`, `labelOfColumn()` (Task 1);
  le chiavi di `context` (Task 2).
- Produces: sulle righe `data-wi-group-<colonna>` e
  `data-wi-group-label-<colonna>`; sul contenitore `#<id>-rows` gli attributi
  `data-wi-group-template` e `data-wi-group-collapsed`; la barra
  `.wi-repeater-groupbar` con `select.wi-repeater-groupby`; il
  `<template id="<id>-group-template">` con dentro `.wi-repeater-group-header`.

- [x] **Step 1: Scrivi il test che fallisce**

`tests/Themes/RepeaterGroupRenderTest.php`:

```php
<?php
/** php tests/Themes/RepeaterGroupRenderTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\RepeaterColumn;

$colonne = fn () => [
    RepeaterColumn::key('name')->text()->label('Versione'),
    RepeaterColumn::key('price')->number()->label('Prezzo'),
    RepeaterColumn::key('product_variant_id')
        ->select(['10' => 'Blu', '11' => 'Rosso'])
        ->label('Colore'),
];

$righe = [
    'row_1' => ['name' => 'Blu / S', 'price' => '19.90', 'product_variant_id' => '10'],
    'row_2' => ['name' => 'Rosso / S', 'price' => '19.90', 'product_variant_id' => '11'],
];

$html = fn (bool $gruppi) => (function () use ($gruppi, $colonne, $righe) {
    $campo = FormField::key('products')->repeater($colonne())->nested()->value($righe);

    if ($gruppi) {
        $campo->repeaterGroupBy('product_variant_id')
            ->repeaterGroupCommand('price', 'Prezzo del gruppo')
            ->repeaterGroupCountLabel('versione', 'versioni');
    }

    return $campo->render('bootstrap');
})();

check('senza raggruppamento il markup non cambia', function () use ($html) {
    $senza = $html(false);

    return !str_contains($senza, 'wi-repeater-groupbar')
        && !str_contains($senza, 'data-wi-group-')
        && !str_contains($senza, 'group-template');
});

check('le righe portano valore ed etichetta del gruppo', function () use ($html) {
    $con = $html(true);

    return str_contains($con, 'data-wi-group-product_variant_id="10"')
        && str_contains($con, 'data-wi-group-label-product_variant_id="Blu"')
        && str_contains($con, 'data-wi-group-label-product_variant_id="Rosso"');
});

check('la barra offre "Nessuno" e la colonna, con la sua etichetta', function () use ($html) {
    $con = $html(true);

    return str_contains($con, 'wi-repeater-groupbar')
        && str_contains($con, '<option value="">Nessuno</option>')
        && str_contains($con, '<option value="product_variant_id">Colore</option>');
});

check('il template della testata porta comando e parole del conteggio', function () use ($html) {
    $con = $html(true);

    return str_contains($con, 'wi-repeater-group-header')
        && str_contains($con, 'data-wi-command-column="price"')
        && str_contains($con, 'Prezzo del gruppo')
        && str_contains($con, 'data-wi-count-singular="versione"')
        && str_contains($con, 'data-wi-count-plural="versioni"');
});

check('con una riga sola non c\'è niente da raggruppare', function () use ($colonne) {
    $html = FormField::key('products')
        ->repeater($colonne())
        ->nested()
        ->value(['row_1' => ['name' => 'Unica', 'product_variant_id' => '10']])
        ->repeaterGroupBy('product_variant_id')
        ->render('bootstrap');

    return !str_contains($html, 'wi-repeater-groupbar');
});

check('una colonna dichiarata ma assente non rompe niente', function () use ($colonne, $righe) {
    $html = FormField::key('products')
        ->repeater($colonne())
        ->nested()
        ->value($righe)
        ->repeaterGroupBy('colore_che_non_esiste')
        ->render('bootstrap');

    return !str_contains($html, 'data-wi-group-colore_che_non_esiste');
});

summary();
```

- [x] **Step 2: Esegui il test e verifica che fallisca**

```bash
php tests/Themes/RepeaterGroupRenderTest.php
```

Atteso: passa il primo test, falliscono gli altri (nessun `data-wi-group-`).

- [x] **Step 3: Scrivi il render**

In `class/Themes/Bootstrap/Form/Components/Repeater.php`.

In testa al file:

```php
use Wonder\App\Support\RepeaterGroups;
```

Dentro `renderInput()`, dopo `$value = ['row_1' => []];`:

```php
        $groupBy = array_values(array_filter(
            array_map(static fn ($key): string => trim((string) $key), (array) ($context['group_by'] ?? [])),
            static fn (string $key): bool => $key !== '' && RepeaterGroups::columnByKey($columns, $key) !== null
        ));

        // Con una riga sola non c'è niente da raggruppare, e la barra sarebbe
        // solo un comando in più da leggere.
        $groupable = $groupBy !== [] && count($value) > 1;
        $groupTemplateId = $id.'-group-template';
        $groups = $groupable ? RepeaterGroups::of($columns, $value, $groupBy) : [];
        $groupAttrs = $groupable
            ? ' data-wi-group-template="'.$this->escape($groupTemplateId).'"'
                .' data-wi-group-collapsed="'.(!empty($context['group_collapsed']) ? 'true' : 'false').'"'
            : '';
        $groupBarHtml = $groupable ? $this->renderGroupBar($id, $rowId, $groupTemplateId, $columns, $groupBy) : '';
        $groupTemplateHtml = $groupable ? $this->renderGroupTemplate($groupTemplateId, $context) : '';
        $groupInitHtml = $groupable
            ? "<script>window.wiRepeaterGroupInit('{$rowId}', '{$groupTemplateId}', '{$id}-groupby');</script>"
            : '';
```

La riga che costruisce le righe passa i gruppi:

```php
        foreach ($value as $rowKey => $rowValue) {
            $rowsHtml .= $this->renderRow(
                $columns,
                $name,
                is_array($rowValue) ? $rowValue : [],
                (string) $rowKey,
                false,
                $context,
                $groups[(string) $rowKey] ?? []
            );
        }
```

E l'HTML finale diventa:

```php
        return <<<HTML
<div id="{$id}" class="w-100 wi-input-repeater">
    {$heading}
    {$groupBarHtml}
    <div id="{$rowId}" class="row g-2"{$groupAttrs}>
        {$rowsHtml}
    </div>
    <template id="{$templateId}">{$templateHtml}</template>
    {$groupTemplateHtml}
    <div class="mt-2 d-flex justify-content-end">
        <button type="button" class="{$addButtonClass}" onclick="window.wiRepeaterAddRow('{$rowId}', '{$templateId}')"><i class="bi bi-plus-lg"></i> {$addLabel}</button>
    </div>
    {$this->script()}
    {$groupInitHtml}
</div>
HTML;
```

`renderRow()` prende il parametro nuovo, con default vuoto perché il template
della riga non appartiene a nessun gruppo:

```php
    private function renderRow(array $columns, string $name, array $rowValue, string $rowKey, bool $template, array $context, array $groupData = []): string
    {
```

e subito dopo `$deleteAttrs`:

```php
        $groupAttrs = '';

        foreach ($groupData as $columnKey => $info) {
            $columnKey = $this->escape((string) $columnKey);
            $groupAttrs .= ' data-wi-group-'.$columnKey.'="'.$this->escape((string) ($info['value'] ?? '')).'"'
                .' data-wi-group-label-'.$columnKey.'="'.$this->escape((string) ($info['label'] ?? '')).'"';
        }
```

con `$groupAttrs` aggiunto all'apertura della riga:

```php
        $html = "<div class=\"col-12 wi-repeater-row{$rowClass}\" data-wi-row-key=\"{$this->escape($rowKey)}\"{$groupAttrs}>";
```

E due metodi nuovi, in fondo alla classe prima di `script()`:

```php
    /** La barra "Raggruppa per": una voce per colonna dichiarata, più "Nessuno". */
    private function renderGroupBar(string $id, string $rowsId, string $templateId, array $columns, array $groupBy): string
    {
        $options = '<option value="">Nessuno</option>';

        foreach ($groupBy as $key) {
            $column = RepeaterGroups::columnByKey($columns, $key);
            $label = RepeaterGroups::labelOfColumn($column);
            $options .= '<option value="'.$this->escape($key).'">'
                .$this->escape($label !== '' ? $label : $key).'</option>';
        }

        return '<div class="wi-repeater-groupbar d-flex align-items-center gap-2 mb-2">'
            .'<label class="form-label mb-0 small text-body-secondary" for="'.$id.'-groupby">Raggruppa per</label>'
            .'<select id="'.$id.'-groupby" class="form-select form-select-sm w-auto wi-repeater-groupby"'
            .' onchange="window.wiRepeaterGroupApply(\''.$rowsId.'\', \''.$templateId.'\', this.value)">'
            .$options
            .'</select></div>';
    }

    /**
     * Il modello della testata di gruppo.
     *
     * Sta in un `<template>` e non fra le righe di proposito: una testata nel
     * contenitore sarebbe una riga finta, che il posting e i conteggi
     * dovrebbero imparare a saltare.
     */
    private function renderGroupTemplate(string $templateId, array $context): string
    {
        $command = is_array($context['group_command'] ?? null) ? $context['group_command'] : [];
        $countLabel = is_array($context['group_count_label'] ?? null) ? $context['group_count_label'] : [];
        $singular = $this->escape((string) ($countLabel['singular'] ?? 'riga'));
        $plural = $this->escape((string) ($countLabel['plural'] ?? 'righe'));
        $commandHtml = '';

        if (($command['column'] ?? '') !== '') {
            $commandHtml = '<div class="ms-auto wi-repeater-group-command" style="max-width:14rem"'
                .' data-wi-command-column="'.$this->escape((string) $command['column']).'">'
                .'<input type="text" class="form-control form-control-sm"'
                .' placeholder="'.$this->escape((string) ($command['label'] ?? '')).'"'
                .' oninput="window.wiRepeaterGroupCommand(this)">'
                .'</div>';
        }

        return '<template id="'.$this->escape($templateId).'">'
            .'<div class="col-12 wi-repeater-group-header"'
            .' data-wi-count-singular="'.$singular.'" data-wi-count-plural="'.$plural.'">'
            .'<div class="card border-0 bg-body-secondary"><div class="card-body py-2 d-flex align-items-center gap-2">'
            .'<button type="button" class="btn btn-sm btn-link text-decoration-none p-0 wi-repeater-group-toggle"'
            .' onclick="window.wiRepeaterGroupToggle(this)"><i class="bi bi-chevron-down"></i></button>'
            .'<strong class="wi-repeater-group-label"></strong>'
            .'<span class="small text-body-secondary wi-repeater-group-count"></span>'
            .$commandHtml
            .'</div></div></div></template>';
    }
```

- [x] **Step 4: Esegui il test e verifica che passi**

```bash
php tests/Themes/RepeaterGroupRenderTest.php
```

Atteso: `6 test, 0 falliti`.

- [x] **Step 5: Esegui tutti i test toccati finora**

```bash
php tests/Support/RepeaterGroupsTest.php && php tests/Support/RepeaterGroupsContextTest.php && php tests/Themes/RepeaterGroupRenderTest.php
```

Atteso: tre volte `0 falliti`.

- [x] **Step 6: Commit**

```bash
git add class/Themes/Bootstrap/Form/Components/Repeater.php tests/Themes/RepeaterGroupRenderTest.php
git commit -m "Repeater groups: row data, group bar and header template"
```

---

### Task 4: Il JS

Sei funzioni, tutte con la guardia `window.x = window.x || ...`, dentro
`script()` accanto a `wiRepeaterAddRow`. Non c'è test automatico: si prova
nel sito, allo Step 4.

**Files:**
- Modify: `class/Themes/Bootstrap/Form/Components/Repeater.php` (solo `script()`)

**Interfaces:**
- Consumes: gli attributi emessi nel Task 3.
- Produces: `wiRepeaterGroupInit`, `wiRepeaterGroupApply`, `wiRepeaterGroupValue`,
  `wiRepeaterGroupToggle`, `wiRepeaterGroupCommand`, `wiRepeaterGroupRefresh`.

- [x] **Step 1: Scrivi le funzioni nuove**

Dentro l'HEREDOC di `script()`, prima di `</script>`:

```javascript
    window.wiRepeaterGroupValue = window.wiRepeaterGroupValue || function (row, columnKey) {
        const field = row.querySelector('[name$="[' + columnKey + ']"], [name="' + columnKey + '[]"]');

        if (field) {
            const value = String(field.value === null || field.value === undefined ? '' : field.value);
            let label = value;

            if (field.tagName === 'SELECT' && field.selectedIndex >= 0) {
                label = field.options[field.selectedIndex].textContent.trim();
            }

            return { value: value, label: value === '' ? '' : label };
        }

        return {
            value: row.getAttribute('data-wi-group-' + columnKey) || '',
            label: row.getAttribute('data-wi-group-label-' + columnKey) || ''
        };
    };

    window.wiRepeaterGroupApply = window.wiRepeaterGroupApply || function (rowsId, templateId, columnKey) {
        const container = document.getElementById(rowsId);
        if (!container) return;

        columnKey = columnKey || '';
        container.dataset.wiGroupColumn = columnKey;
        container.dataset.wiGroupTemplate = templateId;

        try { window.localStorage.setItem('wi-repeater-group:' + rowsId, columnKey); } catch (error) {}

        container.querySelectorAll(':scope > .wi-repeater-group-header').forEach((header) => header.remove());

        const rows = Array.from(container.querySelectorAll(':scope > .wi-repeater-row'));

        rows.forEach((row) => {
            row.style.order = '';
            row.classList.remove('d-none');
            row.querySelectorAll('.wi-repeater-move-up, .wi-repeater-move-down')
                .forEach((button) => button.classList.toggle('d-none', columnKey !== ''));
        });

        if (columnKey === '') return;

        const template = document.getElementById(templateId);
        if (!template) return;

        const order = [];
        const buckets = new Map();

        rows.forEach((row) => {
            const found = window.wiRepeaterGroupValue(row, columnKey);

            if (!buckets.has(found.value)) {
                buckets.set(found.value, { label: found.label, rows: [] });
                order.push(found.value);
            }

            buckets.get(found.value).rows.push(row);
        });

        const collapsed = container.dataset.wiGroupCollapsed === 'true';
        let position = 0;

        order.forEach((key) => {
            const bucket = buckets.get(key);
            const fragment = template.content.cloneNode(true);
            const header = fragment.querySelector('.wi-repeater-group-header');
            const count = bucket.rows.length;

            header.dataset.wiGroupKey = key;
            header.style.order = String(position++);
            header.querySelector('.wi-repeater-group-label').textContent = bucket.label || 'Senza scelta';
            header.querySelector('.wi-repeater-group-count').textContent =
                count + ' ' + (count === 1
                    ? (header.dataset.wiCountSingular || 'riga')
                    : (header.dataset.wiCountPlural || 'righe'));

            if (collapsed) {
                header.classList.add('wi-repeater-group-closed');
                const icon = header.querySelector('.wi-repeater-group-toggle i');
                if (icon) icon.className = 'bi bi-chevron-right';
            }

            container.appendChild(fragment);

            bucket.rows.forEach((row) => {
                row.style.order = String(position++);
                row.classList.toggle('d-none', collapsed);
            });
        });
    };

    window.wiRepeaterGroupToggle = window.wiRepeaterGroupToggle || function (button) {
        const header = button.closest('.wi-repeater-group-header');
        if (!header) return;

        const container = header.parentElement;
        const columnKey = container.dataset.wiGroupColumn || '';
        const key = header.dataset.wiGroupKey || '';
        const closed = header.classList.toggle('wi-repeater-group-closed');

        Array.from(container.querySelectorAll(':scope > .wi-repeater-row')).forEach((row) => {
            if (window.wiRepeaterGroupValue(row, columnKey).value === key) {
                row.classList.toggle('d-none', closed);
            }
        });

        const icon = button.querySelector('i');
        if (icon) icon.className = closed ? 'bi bi-chevron-right' : 'bi bi-chevron-down';
    };

    window.wiRepeaterGroupCommand = window.wiRepeaterGroupCommand || function (input) {
        const header = input.closest('.wi-repeater-group-header');
        const box = input.closest('.wi-repeater-group-command');
        if (!header || !box) return;

        const container = header.parentElement;
        const columnKey = container.dataset.wiGroupColumn || '';
        const target = box.dataset.wiCommandColumn || '';
        const key = header.dataset.wiGroupKey || '';
        if (target === '') return;

        Array.from(container.querySelectorAll(':scope > .wi-repeater-row')).forEach((row) => {
            if (window.wiRepeaterGroupValue(row, columnKey).value !== key) return;

            const field = row.querySelector('[name$="[' + target + ']"], [name="' + target + '[]"]');
            if (!field) return;

            field.value = input.value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    };

    window.wiRepeaterGroupRefresh = window.wiRepeaterGroupRefresh || function (container) {
        if (!container || !container.dataset.wiGroupColumn) return;

        window.wiRepeaterGroupApply(
            container.id,
            container.dataset.wiGroupTemplate || '',
            container.dataset.wiGroupColumn
        );
    };

    window.wiRepeaterGroupInit = window.wiRepeaterGroupInit || function (rowsId, templateId, selectId) {
        const container = document.getElementById(rowsId);
        const select = document.getElementById(selectId);
        if (!container || !select) return;

        let remembered = '';
        try { remembered = window.localStorage.getItem('wi-repeater-group:' + rowsId) || ''; } catch (error) {}

        if (remembered !== '' && !Array.from(select.options).some((option) => option.value === remembered)) {
            remembered = '';
        }

        if (!container.dataset.wiGroupBound) {
            container.dataset.wiGroupBound = 'true';
            container.addEventListener('change', function (event) {
                const columnKey = container.dataset.wiGroupColumn || '';
                const name = event.target && event.target.name ? event.target.name : '';
                if (columnKey === '' || name === '') return;

                if (name.endsWith('[' + columnKey + ']') || name === columnKey + '[]') {
                    window.wiRepeaterGroupRefresh(container);
                }
            });
        }

        select.value = remembered;
        container.dataset.wiGroupTemplate = templateId;
        window.wiRepeaterGroupApply(rowsId, templateId, remembered);
    };
```

- [x] **Step 2: Aggancia aggiunta ed eliminazione**

In fondo a `wiRepeaterAddRow`, dopo `container.appendChild(fragment);`:

```javascript
        if (typeof window.wiRepeaterGroupRefresh === 'function') window.wiRepeaterGroupRefresh(container);
```

E in `wiRepeaterRemoveRow`, subito dopo la rimozione della riga (il punto in
cui oggi si chiama `row.remove()`):

```javascript
        if (typeof window.wiRepeaterGroupRefresh === 'function') window.wiRepeaterGroupRefresh(container);
```

dove `container` è l'elemento `.row` che conteneva la riga: se la funzione non
ce l'ha già in una variabile, prenderlo **prima** della rimozione con
`const container = row.parentElement;`.

- [x] **Step 3: Controlla la sintassi**

```bash
php -l class/Themes/Bootstrap/Form/Components/Repeater.php && php tests/Themes/RepeaterGroupRenderTest.php
```

Atteso: `No syntax errors` e `6 test, 0 falliti`.

- [x] **Step 4: Prova nel sito**

Serve un repeater con più righe e una colonna select. Nel sito di prova
`boilerplates/ecommerce-site` non ce n'è ancora uno (arriva con G2c): aggiungi
**temporaneamente** `->repeaterGroupBy('active')->repeaterGroupCommand('price', 'Prezzo del gruppo')->repeaterGroupCountLabel('versione', 'versioni')`
al repeater `products` di `ProductModelResource` del modulo gestionale, apri un
prodotto con più versioni su `https://ecommerce.test/backend/` e verifica:

1. la barra "Raggruppa per" compare, con "Nessuno" selezionato;
2. scegliendo la colonna nascono le testate, con conteggio giusto;
3. il pulsante ▾ chiude e riapre il gruppo;
4. scrivendo nella casella della testata i prezzi delle righe del gruppo
   cambiano **subito**, e solo quelli;
5. salvando, i prezzi salvati sono quelli visti;
6. tornando a "Nessuno" le frecce di riordino ricompaiono e il riordino salva
   le posizioni giuste;
7. ricaricando la pagina il raggruppamento scelto è ancora quello.

Poi **togli** la modifica temporanea dal modulo.

- [x] **Step 5: Commit**

```bash
git add class/Themes/Bootstrap/Form/Components/Repeater.php
git commit -m "Repeater groups: headers, collapse and group command in JS"
```

---

### Task 5: L'Accordion dentro un form

**Files:**
- Modify: `class/Themes/Bootstrap/Components/Accordion.php`
- Test: `tests/Themes/AccordionGridTest.php`

**Interfaces:**
- Produces: il corpo dell'Accordion diventa un contenitore a griglia come il
  `card-body` della Card.

- [x] **Step 1: Scrivi il test che fallisce**

`tests/Themes/AccordionGridTest.php`:

```php
<?php
/** php tests/Themes/AccordionGridTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\Elements\Components\Accordion;

$html = static function (): string {
    $accordion = (new Accordion)
        ->text('Scheda tecnica')
        ->columns(12)
        ->columnSpan(12)
        ->components([FormField::key('material')->text()->label('Materiale')->columnSpan(6)]);

    return $accordion->render('bootstrap');
};

check('il corpo è una griglia, come il card-body della Card', function () use ($html) {
    return (bool) preg_match('/accordion-body[^"]*row[^"]*"/', $html());
});

check('il campo dentro tiene la sua larghezza', function () use ($html) {
    return str_contains($html(), 'col-span-6') || str_contains($html(), 'col-6');
});

check('il titolo e il bottone restano quelli di prima', function () use ($html) {
    $reso = $html();

    return str_contains($reso, 'accordion-button')
        && str_contains($reso, 'Scheda tecnica')
        && str_contains($reso, 'data-bs-toggle="collapse"');
});

summary();
```

- [x] **Step 2: Esegui il test e verifica che fallisca**

```bash
php tests/Themes/AccordionGridTest.php
```

Atteso: fallisce il primo test — `accordion-body` non ha nessuna classe di
griglia. Se fallisce anche il terzo, la colpa è del test: `Accordion` potrebbe
non avere `columns()` (usa `CanSpanColumn`, non `IsContainer`). In quel caso
aggiungi il trait `IsContainer` all'elemento
`class/Elements/Components/Accordion.php`, come ce l'ha `Card`, e rilancia.

- [x] **Step 3: Correggi il renderer**

In `class/Themes/Bootstrap/Components/Accordion.php`:

```php
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Bootstrap\Concerns\HasColumns;
use Wonder\Themes\Bootstrap\Concerns\HasGap;
```

```php
    use CanSpanColumn, HasColumns, HasGap, RendersText, RendersComponentAttributes, RendersThemeComponents;
```

e, dentro `render()`:

```php
        // Il corpo è un contenitore a griglia come il `card-body` della Card:
        // senza, i campi di un form — che portano `col-span-*` — non hanno
        // nessuna riga in cui stare e si schiacciano in una striscia.
        $classColumn = $this->getColumns($class->columns);
        $classGap = $this->getGap($class->gap);
```

```php
        $html .= "<div class=\"accordion-body {$classColumn} {$classGap}\">{$content}</div>";
```

- [x] **Step 4: Esegui il test e verifica che passi**

```bash
php tests/Themes/AccordionGridTest.php
```

Atteso: `3 test, 0 falliti`.

- [x] **Step 5: Verifica che un Accordion di solo testo non cambi**

```bash
grep -rn "Accordion" docs/app --include=*.md | head
```

Apri nel sito una pagina che ne usa uno con dentro solo testo (se non ce ne
sono, salta): l'aspetto non deve cambiare.

- [x] **Step 6: Commit**

```bash
git add class/Themes/Bootstrap/Components/Accordion.php tests/Themes/AccordionGridTest.php
git commit -m "Accordion: grid body so form fields keep their width"
```

---

### Task 6: Documentazione e rilascio

**Files:**
- Modify: `docs/app/concetti/form/repeater.md`
- Modify: `CHANGELOG.md` (se il repo ne ha uno; altrimenti salta)

- [x] **Step 1: Documenta il raggruppamento**

In `docs/app/concetti/form/repeater.md`, una sezione nuova "Righe raggruppate"
con: i quattro setter, l'esempio completo qui sotto, la regola che il
raggruppamento è una vista (il DOM non si sposta, le posizioni non cambiano),
i tre limiti della spec (una colonna per volta, colonne con valori
enumerabili, frecce nascoste mentre si raggruppa) e la nota che la casella di
gruppo è un comando che non viene postato.

```php
FormField::key('products')
    ->repeater([
        RepeaterColumn::key('product_variant_id')->select($colori)->label('Colore')->columnSpan(2),
        RepeaterColumn::key('name')->text()->label('Versione')->columnSpan(2),
        RepeaterColumn::key('price')->number()->decimal(2)->label('Prezzo')->columnSpan(2),
    ])
    ->repeaterGroupBy('product_variant_id')
    ->repeaterGroupCommand('price', 'Prezzo del gruppo')
    ->repeaterGroupCountLabel('versione', 'versioni');
```

- [x] **Step 2: Esegui tutti i test nuovi**

```bash
php tests/Support/RepeaterGroupsTest.php && php tests/Support/RepeaterGroupsContextTest.php && php tests/Themes/RepeaterGroupRenderTest.php && php tests/Themes/AccordionGridTest.php
```

Atteso: quattro volte `0 falliti`.

- [x] **Step 3: Commit**

```bash
git add docs/app/concetti/form/repeater.md
git commit -m "Docs: grouped repeater rows"
```

- [ ] **Step 4: Rilascio v.2.3.0**

Il tag porta fuori **due** cose: il raggruppamento di questo piano e il
quick-create FK, già in `main` da prima e mai rilasciato.

```bash
git tag v.2.3.0 && git push origin main --tags
```

Poi, nel sito di prova, `composer update wonder-image/app` e `php forge update`;
da lì in avanti il modulo gestionale può alzare il pavimento a `^2.3.0`.
