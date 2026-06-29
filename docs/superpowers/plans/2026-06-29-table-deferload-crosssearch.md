# Table pre-render + cross-table search — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pre-render the first page of a `Wonder\Backend\Table\Table` server-side (skipping the initial DataTables AJAX) and let its search bar query columns on related tables via one-hop foreign keys.

**Architecture:** Extract the "config → formatted rows" logic from the API endpoint into a reusable `Wonder\Backend\Table\ListProvider`. The endpoint and `Table::generate()` both call it. `Table` renders the first page into `<tbody>` and emits a `deferLoading` count that an updated lib consumes (graceful degradation otherwise). Search resolves dotted `foreign_table.column` entries into descriptors via the Model's `foreign()` metadata; `SSP::filter()` turns descriptors into `IN (SELECT ...)` subqueries, leaving the main `SELECT *` intact.

**Tech Stack:** PHP 8.x (framework `wonder-image/app`), DataTables (server-side) in `wonder-image/lib` JS, MySQL via PDO (`SSP`) and mysqli (`Query`).

## Global Constraints

- No test framework in repo: tests are **standalone PHP scripts** run with `php tests/<path>/XxxTest.php`, starting with `require __DIR__ . '/../../vendor/autoload.php';` and using `assert()` / manual `echo` + exit codes. Follow `tests/Console/ProjectNamingTest.php`.
- Lint every touched PHP file with `php -l <file>` (must print "No syntax errors detected").
- Run `composer dumpautoload` after adding the new class.
- New PHP class lives under `class/` with namespace `Wonder\Backend\Table` (PSR-4 root `Wonder\` → `class/`).
- Keep the endpoint's JSON output byte-shape unchanged (it is consumed by the existing lib AJAX path).
- One-hop relations only. No JOINs in the main query (use `IN` subqueries). No pivot / many-to-many.
- Indentation/style: match each file's existing convention (the legacy `class/Backend/Table/*` files use 4-space indent inside a namespace block indented one level).

---

## File Structure

- **Create** `class/Backend/Table/ListProvider.php` — single responsibility: from a DataTables request + resolved table context, build the SSP column set and return the SSP result array. Also hosts two pure helpers (`buildColumns`, field resolution) reused by both call sites.
- **Modify** `app/api/backend/list/table.php` — becomes a thin wrapper that assembles the request objects and delegates to `ListProvider::fetch()`.
- **Modify** `class/Backend/Table/SSP.php` — `filter()` sanitizes then delegates to a new pure `buildSearchWhere()` that supports relation descriptors.
- **Modify** `class/Backend/Table/Table.php` — pre-render first page into `<tbody>`, add `deferLoading` to the script config; accept relation descriptors in `filterSearch`.
- **Modify** `class/Backend/Support/ResourceTableRenderer.php` — resolve dotted `foreign_table.column` search fields into descriptors using the Model schema.
- **Modify** (lib repo) `/Users/andreamarinoni/Developer/packages/lib/src/build/backend/js/list.js` — pass `config.default.deferLoading` to DataTables; rebuild `dist`.
- **Create** `tests/Backend/Table/SearchWhereTest.php` — unit test for `SSP::buildSearchWhere()`.
- **Create** `tests/Backend/Table/SearchFieldResolverTest.php` — unit test for the dotted-field resolver.

---

## Task 1: Cross-table WHERE builder in SSP (pure, TDD)

Refactor `SSP::filter()` so the SQL-string construction is a pure, testable method that also understands relation descriptors. `filter()` keeps doing the `sanitize()` call (which needs the framework runtime) and delegates the string building.

**Files:**
- Modify: `class/Backend/Table/SSP.php` (method `filter`, ~lines 151-179; add `buildSearchWhere`)
- Test: `tests/Backend/Table/SearchWhereTest.php`

**Interfaces:**
- Produces:
  - `SSP::buildSearchWhere(string $searchValue, array $fields): string` — pure. `$fields` is a mixed list: plain strings = main-table columns; arrays = descriptors `['table'=>string,'local_key'=>string,'foreign_key'=>string,'columns'=>string[]]`. Returns a WHERE clause **without** the leading `WHERE` keyword, or `''` when nothing searchable. Caller prepends `WHERE `.
  - `SSP::filter(array $request, $columns): string` — unchanged signature; now returns `'WHERE ' . buildSearchWhere(sanitize(value), $columns)` when there is a search value, else `''`.

- [ ] **Step 1: Write the failing test**

Create `tests/Backend/Table/SearchWhereTest.php`:

```php
<?php
/**
 * Standalone test (no phpunit in repo):
 *   php tests/Backend/Table/SearchWhereTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Table\SSP;

$fail = 0;
function check(string $label, $got, $expected) {
    global $fail;
    if ($got !== $expected) {
        $fail++;
        echo "FAIL: $label\n  expected: $expected\n  got:      $got\n";
    } else {
        echo "ok: $label\n";
    }
}

// 1) plain columns, single word
check(
    'single word, main columns',
    SSP::buildSearchWhere('mario', ['name', 'surname']),
    "(CONCAT_WS(' ', `name`, `surname`) LIKE '%mario%')"
);

// 2) plain columns, two words -> AND between words
check(
    'two words, main columns',
    SSP::buildSearchWhere('mario rossi', ['name', 'surname']),
    "(CONCAT_WS(' ', `name`, `surname`) LIKE '%mario%') AND (CONCAT_WS(' ', `name`, `surname`) LIKE '%rossi%')"
);

// 3) one relation descriptor + main columns, single word
$relation = ['table' => 'user', 'local_key' => 'user_id', 'foreign_key' => 'id', 'columns' => ['email', 'username']];
check(
    'relation + main, single word',
    SSP::buildSearchWhere('mario', ['name', $relation]),
    "((CONCAT_WS(' ', `name`) LIKE '%mario%') OR (`user_id` IN (SELECT `id` FROM `user` WHERE CONCAT_WS(' ', `email`, `username`) LIKE '%mario%')))"
);

// 4) only a relation, no main columns
check(
    'relation only',
    SSP::buildSearchWhere('mario', [$relation]),
    "((`user_id` IN (SELECT `id` FROM `user` WHERE CONCAT_WS(' ', `email`, `username`) LIKE '%mario%')))"
);

// 5) empty search value -> empty string
check('empty value', SSP::buildSearchWhere('', ['name']), '');

// 6) no usable fields -> empty string
check('no fields', SSP::buildSearchWhere('mario', []), '');

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/Backend/Table/SearchWhereTest.php`
Expected: FATAL/ERROR — `Call to undefined method Wonder\Backend\Table\SSP::buildSearchWhere()`.

- [ ] **Step 3: Implement `buildSearchWhere` and rewire `filter`**

In `class/Backend/Table/SSP.php`, replace the body of `filter()` and add the pure builder. New `filter`:

```php
        static function filter ( $request, $columns )
        {
            if ( !isset($request['search']) || $request['search']['value'] === '' ) {
                return '';
            }

            $where = self::buildSearchWhere( sanitize($request['search']['value']), is_array($columns) ? $columns : [] );

            return $where === '' ? '' : 'WHERE '.$where;
        }
```

Add the pure builder (same class):

```php
        /**
         * Build the search WHERE body (without the leading "WHERE").
         *
         * @param string $searchValue Already-sanitized search string
         * @param array  $fields      Mixed list: string = main-table column;
         *   array = relation descriptor with keys table, local_key, foreign_key, columns[]
         * @return string WHERE body, or '' when nothing is searchable
         */
        static function buildSearchWhere ( string $searchValue, array $fields )
        {
            $searchValue = trim($searchValue);
            if ( $searchValue === '' ) {
                return '';
            }

            $mainCols  = [];
            $relations = [];

            foreach ( $fields as $field ) {
                if ( is_string($field) && $field !== '' ) {
                    $mainCols[] = $field;
                } elseif ( is_array($field)
                    && !empty($field['table'])
                    && !empty($field['local_key'])
                    && !empty($field['foreign_key'])
                    && !empty($field['columns']) && is_array($field['columns']) ) {
                    $relations[] = $field;
                }
            }

            if ( $mainCols === [] && $relations === [] ) {
                return '';
            }

            $words   = preg_split('/\s+/', $searchValue, -1, PREG_SPLIT_NO_EMPTY);
            $perWord = [];

            foreach ( $words as $word ) {
                $like = "'%".$word."%'";
                $ors  = [];

                if ( $mainCols !== [] ) {
                    $concat = "CONCAT_WS(' ', `".implode('`, `', $mainCols)."`)";
                    $ors[]  = "(".$concat." LIKE ".$like.")";
                }

                foreach ( $relations as $rel ) {
                    $concat = "CONCAT_WS(' ', `".implode('`, `', $rel['columns'])."`)";
                    $ors[]  = "(`".$rel['local_key']."` IN (SELECT `".$rel['foreign_key']."` FROM `".$rel['table']."` WHERE ".$concat." LIKE ".$like."))";
                }

                $perWord[] = count($ors) === 1 ? $ors[0] : '('.implode(' OR ', $ors).')';
            }

            return implode(' AND ', $perWord);
        }
```

Note: descriptor entries are validated at the call site (Task 4) before reaching here, so `buildSearchWhere` trusts the structure but still skips malformed entries.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/Backend/Table/SearchWhereTest.php`
Expected: prints `ALL PASS`, exit 0.

- [ ] **Step 5: Lint**

Run: `php -l class/Backend/Table/SSP.php`
Expected: `No syntax errors detected in class/Backend/Table/SSP.php`

- [ ] **Step 6: Commit**

```bash
git add class/Backend/Table/SSP.php tests/Backend/Table/SearchWhereTest.php
git commit -m "feat(table): cross-table search WHERE builder in SSP"
```

---

## Task 2: Dotted search-field resolver in ResourceTableRenderer (pure, TDD)

Resolve `foreign_table.column` search entries into descriptors using the Model's `tableSchema()` foreign metadata. Keep plain entries as-is.

**Files:**
- Modify: `class/Backend/Support/ResourceTableRenderer.php` (`tableLayoutSearchFields()` ~line 273; add `resolveSearchFields()`)
- Test: `tests/Backend/Table/SearchFieldResolverTest.php`

**Interfaces:**
- Consumes: `SSP::buildSearchWhere` descriptor shape (Task 1).
- Produces:
  - `ResourceTableRenderer::resolveSearchFields(array $fields, array $foreignMap): array` — **static, pure**. `$fields` = trimmed strings (may contain dots). `$foreignMap` maps `foreign_table => ['local_key'=>string,'foreign_key'=>string]`. Returns a mixed list: plain strings for non-dotted (or unresolved) entries; descriptors for resolved `table.column`, with columns on the same relation merged into one descriptor preserving order.

- [ ] **Step 1: Write the failing test**

Create `tests/Backend/Table/SearchFieldResolverTest.php`:

```php
<?php
/** php tests/Backend/Table/SearchFieldResolverTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Support\ResourceTableRenderer;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

$foreignMap = [
    'user' => ['local_key' => 'user_id', 'foreign_key' => 'id'],
];

// plain fields untouched
eq('plain only',
    ResourceTableRenderer::resolveSearchFields(['name', 'surname'], $foreignMap),
    ['name', 'surname']
);

// dotted resolves; multiple columns on same relation merge
eq('dotted merged',
    ResourceTableRenderer::resolveSearchFields(['name', 'user.email', 'user.username'], $foreignMap),
    ['name', ['table' => 'user', 'local_key' => 'user_id', 'foreign_key' => 'id', 'columns' => ['email', 'username']]]
);

// unknown foreign table -> entry dropped (no FK to resolve)
eq('unknown relation dropped',
    ResourceTableRenderer::resolveSearchFields(['name', 'ghost.col'], $foreignMap),
    ['name']
);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/Backend/Table/SearchFieldResolverTest.php`
Expected: ERROR — undefined method `resolveSearchFields`.

- [ ] **Step 3: Implement the resolver and wire it in**

In `class/Backend/Support/ResourceTableRenderer.php` add the static pure method:

```php
    /**
     * Resolve dotted `foreign_table.column` search entries into relation
     * descriptors using a foreign-key map. Plain entries pass through.
     *
     * @param array<int,string> $fields
     * @param array<string,array{local_key:string,foreign_key:string}> $foreignMap
     * @return array<int,string|array{table:string,local_key:string,foreign_key:string,columns:array<int,string>}>
     */
    public static function resolveSearchFields(array $fields, array $foreignMap): array
    {
        $plain      = [];
        $relations  = []; // foreign_table => descriptor (by reference via index)
        $order      = []; // preserves first-seen order of output entries

        foreach ($fields as $field) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            if (strpos($field, '.') === false) {
                $plain[]  = $field;
                $order[]  = ['plain', count($plain) - 1];
                continue;
            }

            [$table, $column] = explode('.', $field, 2);
            $table  = trim($table);
            $column = trim($column);

            if ($table === '' || $column === '' || !isset($foreignMap[$table])) {
                continue; // unresolved relation -> drop
            }

            if (!isset($relations[$table])) {
                $relations[$table] = [
                    'table'       => $table,
                    'local_key'   => $foreignMap[$table]['local_key'],
                    'foreign_key' => $foreignMap[$table]['foreign_key'],
                    'columns'     => [],
                ];
                $order[] = ['relation', $table];
            }

            if (!in_array($column, $relations[$table]['columns'], true)) {
                $relations[$table]['columns'][] = $column;
            }
        }

        $out = [];
        foreach ($order as [$kind, $ref]) {
            $out[] = $kind === 'plain' ? $plain[$ref] : $relations[$ref];
        }

        return $out;
    }
```

Then change `tableLayoutSearchFields()` to build the foreign map from the model schema and apply the resolver. Replace its final `return` block:

```php
    private function tableLayoutSearchFields(): array
    {
        $fields = (array) ($this->tableLayoutSchema['search_fields'] ?? []);

        if ($fields === []) {
            return $this->defaultSearchFields();
        }

        $fields = array_values(array_filter(array_map(
            static fn ($field) => is_string($field) ? trim($field) : '',
            $fields
        )));

        if ($fields === []) {
            return $this->defaultSearchFields();
        }

        return self::resolveSearchFields(array_values(array_unique($fields)), $this->foreignKeyMap());
    }

    /**
     * Map foreign_table => {local_key, foreign_key} from the Model tableSchema.
     *
     * @return array<string,array{local_key:string,foreign_key:string}>
     */
    private function foreignKeyMap(): array
    {
        $map = [];

        foreach ($this->modelClass::tableSchema() as $column) {
            if (!is_object($column) || !method_exists($column, 'getSchema')) {
                continue;
            }

            $foreignTable = $column->getSchema('foreign_table');
            if (!is_string($foreignTable) || trim($foreignTable) === '') {
                continue;
            }

            $map[trim($foreignTable)] = [
                'local_key'   => (string) $column->name,
                'foreign_key' => (string) ($column->getSchema('foreign_key') ?: 'id'),
            ];
        }

        return $map;
    }
```

(`$this->modelClass` already exists, set in the constructor at line 30. `Column::$name` and `getSchema()` come from `Wonder\Sql\TableSchema` + `HasSchema`.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/Backend/Table/SearchFieldResolverTest.php`
Expected: `ALL PASS`, exit 0.

- [ ] **Step 5: Lint**

Run: `php -l class/Backend/Support/ResourceTableRenderer.php`
Expected: No syntax errors.

- [ ] **Step 6: Commit**

```bash
git add class/Backend/Support/ResourceTableRenderer.php tests/Backend/Table/SearchFieldResolverTest.php
git commit -m "feat(table): resolve dotted cross-table search fields from model FKs"
```

---

## Task 3: searchFields descriptor pass-through (TableLayoutSchema + Table)

Allow descriptor arrays (not just strings) to survive from `searchFields()` config down into `Table::filterSearch`, so the resolver's output reaches the SSP config. Currently `TableLayoutSchema::searchFields()` filters to strings only.

**Files:**
- Modify: `class/App/ResourceSchema/TableLayoutSchema.php` (`searchFields()` ~line 130)
- Modify: `class/Backend/Table/Table.php` (`filterSearch()` line 267 — accepts mixed list; no transformation needed, just ensure it stores as-is which it already does)

**Interfaces:**
- Consumes: resolver output from Task 2 (mixed string/descriptor list).
- Produces: `config.search_columns` JSON now may contain descriptor objects; `SSP::filter` (Task 1) already handles them.

Note on ordering vs Task 2: `TableLayoutSchema::searchFields()` stores the **raw** user strings (including dotted ones); `ResourceTableRenderer` resolves them to descriptors later. So this task only needs to stop dropping dotted strings — and they are already strings, so today's filter keeps them. The real change is allowing **descriptors** when a caller passes them directly (standalone use). Keep the string-trimming for strings and pass arrays through untouched.

- [ ] **Step 1: Update `searchFields()` to preserve descriptors**

Replace the method body in `class/App/ResourceSchema/TableLayoutSchema.php`:

```php
    public function searchFields(array $fields): self
    {
        $clean = [];

        foreach ($fields as $field) {
            if (is_string($field)) {
                $trimmed = trim($field);
                if ($trimmed !== '') {
                    $clean[] = $trimmed;
                }
            } elseif (is_array($field) && !empty($field['table']) && !empty($field['columns'])) {
                $clean[] = $field;
            }
        }

        $this->schema['search_fields'] = array_values($clean);

        return $this;
    }
```

- [ ] **Step 2: Confirm `ResourceTableRenderer::tableLayoutSearchFields()` tolerates descriptors**

`tableLayoutSearchFields()` (after Task 2) maps with `is_string($field) ? trim($field) : ''` and filters empties — which would **drop** descriptor arrays. Update its mapping to keep arrays:

In `class/Backend/Support/ResourceTableRenderer.php`, change the cleaning step inside `tableLayoutSearchFields()`:

```php
        $fields = array_values(array_filter(array_map(
            static function ($field) {
                if (is_string($field)) {
                    return trim($field);
                }
                return (is_array($field) && !empty($field['table']) && !empty($field['columns'])) ? $field : '';
            },
            $fields
        ), static fn ($f) => $f !== ''));
```

Then pass through `resolveSearchFields` only the **string** entries for dotted resolution, while keeping already-formed descriptors. Adjust the return:

```php
        $strings     = array_values(array_filter($fields, 'is_string'));
        $descriptors = array_values(array_filter($fields, 'is_array'));

        $resolved = self::resolveSearchFields(array_values(array_unique($strings)), $this->foreignKeyMap());

        return array_merge($resolved, $descriptors);
```

- [ ] **Step 3: Lint**

Run: `php -l class/App/ResourceSchema/TableLayoutSchema.php && php -l class/Backend/Support/ResourceTableRenderer.php`
Expected: No syntax errors (both).

- [ ] **Step 4: Re-run Task 2 test (regression)**

Run: `php tests/Backend/Table/SearchFieldResolverTest.php`
Expected: `ALL PASS` (resolver unchanged).

- [ ] **Step 5: Commit**

```bash
git add class/App/ResourceSchema/TableLayoutSchema.php class/Backend/Support/ResourceTableRenderer.php
git commit -m "feat(table): allow cross-table search descriptors through layout schema"
```

---

## Task 4: Validate relation descriptors against the live schema

Before a descriptor is used to build SQL, verify the foreign table and its columns exist, so a malformed/hostile `config.search_columns` cannot inject unknown identifiers. Validation runs where the DB is reachable (resolution time in `ResourceTableRenderer`, which already runs in the app runtime with `sqlColumnExists`/`sqlTableInfo` available).

**Files:**
- Modify: `class/Backend/Support/ResourceTableRenderer.php` (`foreignKeyMap()` / after `resolveSearchFields`)

**Interfaces:**
- Consumes: `resolveSearchFields` output.
- Produces: descriptor list with only existing table+columns; invalid columns removed, descriptors with no valid columns dropped.

- [ ] **Step 1: Add validation helper**

In `class/Backend/Support/ResourceTableRenderer.php`:

```php
    /**
     * Drop relation descriptors / columns that do not exist in the DB.
     * Plain string entries pass through unchanged.
     */
    private function validateSearchDescriptors(array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            if (is_string($field)) {
                $out[] = $field;
                continue;
            }

            if (!is_array($field) || empty($field['table'])) {
                continue;
            }

            $table = (string) $field['table'];

            // sqlTableInfo / sqlColumnExists are framework globals available at runtime.
            $validCols = array_values(array_filter(
                (array) ($field['columns'] ?? []),
                static fn ($col) => is_string($col) && $col !== '' && sqlColumnExists($table, $col)
            ));

            if ($validCols === [] || !sqlColumnExists($table, (string) $field['foreign_key'])) {
                continue;
            }

            $field['columns'] = $validCols;
            $out[] = $field;
        }

        return $out;
    }
```

Then wrap the return of `tableLayoutSearchFields()`:

```php
        return $this->validateSearchDescriptors(array_merge($resolved, $descriptors));
```

- [ ] **Step 2: Lint**

Run: `php -l class/Backend/Support/ResourceTableRenderer.php`
Expected: No syntax errors.

- [ ] **Step 3: Verify `sqlColumnExists` exists and its signature**

Run: `grep -rn "function sqlColumnExists" app class vendor/wonder-image 2>/dev/null | head`
Expected: a definition `function sqlColumnExists($table, $column)`. If the signature differs (e.g. needs a database arg), adapt the call accordingly before committing. If only `sqlTableInfo` exists, derive column existence from it.

- [ ] **Step 4: Commit**

```bash
git add class/Backend/Support/ResourceTableRenderer.php
git commit -m "feat(table): validate cross-table search descriptors against schema"
```

---

## Task 5: Extract `ListProvider` and make the endpoint a thin wrapper

Move the column-building + SSP call out of the endpoint into a reusable class. Endpoint output must stay identical.

**Files:**
- Create: `class/Backend/Table/ListProvider.php`
- Modify: `app/api/backend/list/table.php`

**Interfaces:**
- Consumes: `SSP::complex` (existing), `Wonder\Backend\Table\Field` (existing), `Wonder\App\Credentials::database()`.
- Produces:
  - `ListProvider::buildColumns(array $fields, bool $arrow, int $lineCount, Field $renderer): array` — static. `$fields` = the `config.fields` array (each has `name`, optional `other`). Returns the `$COLUMNS` array of `['db','dt','format','formatter']` exactly as the endpoint builds today (special-casing `position-up`, `position-down`, `menu`).
  - `ListProvider::fetch(array $request, object $name, object $text, object $user, object $page, $path): array` — static. Returns the SSP result array `['draw','recordsTotal','recordsFiltered','data']`. `$request` mirrors today's `$_POST`. `$name` carries `table, database, field, schema, link, page, length` and any fields `Field` needs.

- [ ] **Step 1: Create `ListProvider` with `buildColumns` + `fetch`**

Create `class/Backend/Table/ListProvider.php`. Move the logic from `app/api/backend/list/table.php` lines 56-66 (CUSTOM decode), 70-121 (arrow/page/count), 126-232 (columns + SSP) into the class. Concretely:

```php
<?php

namespace Wonder\Backend\Table;

use Wonder\App\Credentials;
use Wonder\Backend\Table\Field;
use Wonder\Backend\Table\SSP;

final class ListProvider
{
    /**
     * Build the SSP column set (db name, dt index, format, formatter).
     *
     * @param array<int,array<string,mixed>> $fields config.fields entries
     */
    public static function buildColumns(array $fields, bool $arrow, int $lineCount, Field $renderer): array
    {
        $columns = [];
        $columnN = 0;

        foreach ($fields as $format) {
            $columnName = $format['name'];
            $other      = $format['other'] ?? [];

            if ($columnName === 'position-up' || $columnName === 'position-down') {
                $arrowDir   = $columnName === 'position-up' ? 'position_arrow_up' : 'position_arrow_down';
                $columnName = 'id';
                $format     = ['visible' => $arrow, 'lines' => $lineCount];
                $formatter  = static function ($row, $column, $format) use ($renderer, $arrowDir) {
                    return $renderer->newField($row, $arrowDir, $format);
                };
            } elseif ($columnName === 'menu') {
                $columnName = 'id';
                $format     = $other;
                $formatter  = static function ($row, $column, $format) use ($renderer) {
                    return $renderer->newField($row, 'action_button', $format);
                };
            } else {
                $format    = empty($other) ? $format : $other;
                $formatter = static function ($row, $column, $format) use ($renderer) {
                    return $renderer->newField($row, $column, $format);
                };
            }

            $columns[] = [
                'db'        => $columnName,
                'dt'        => $columnN,
                'format'    => $format,
                'formatter' => $formatter,
            ];

            $columnN++;
        }

        return $columns;
    }

    /**
     * Run the server-side processing query and return the SSP result array.
     *
     * @param array<string,mixed> $request DataTables request (same shape as $_POST)
     */
    public static function fetch(array $request, object $name, object $text, object $user, object $page, $path): array
    {
        $custom = (object) [];
        $custom->query        = base64_decode($request['config']['query'] ?? '');
        $custom->query_filter = base64_decode($request['config']['query_filter'] ?? '');
        $custom->query_all    = base64_decode($request['config']['query_custom'] ?? '');
        $custom->search_field = isset($request['config']['search_columns'])
            ? json_decode(base64_decode($request['config']['search_columns']), true)
            : [];
        if (!is_array($custom->search_field)) {
            $custom->search_field = [];
        }

        $custom->arrow = (($request['default']['order'] ?? '') === 'position');
        if (isset($request['search']) && ($request['search']['value'] ?? '') !== '') {
            $custom->arrow = false;
        }
        if (!empty($custom->query_filter)) {
            $custom->arrow = false;
        }

        $custom->order_column    = $request['order'][0]['name'] ?? ($request['default']['order'] ?? '');
        $custom->order_direction = $request['order'][0]['dir']  ?? ($request['default']['order_direction'] ?? '');

        $lineCount = (int) sqlCount($name->table, $custom->query, 'id', true);

        $renderer = new Field($name, $path, $text, $user, $page);
        $columns  = self::buildColumns((array) $request['fields'], $custom->arrow, $lineCount, $renderer);

        $credentials = Credentials::database();
        $sqlDetails  = [
            'user' => $credentials->username,
            'pass' => $credentials->password,
            'db'   => $name->database,
            'host' => $credentials->hostname,
        ];

        return SSP::complex(
            $request,
            $sqlDetails,
            $name->table,
            'id',
            $columns,
            $custom->search_field,
            $custom->query_filter,
            $custom->query_all,
            $custom->order_column,
            $custom->order_direction
        );
    }
}
```

- [ ] **Step 2: Refactor the endpoint to delegate**

In `app/api/backend/list/table.php`, keep the request-assembly that builds `$NAME`, `$TEXT`, `$USER`, `$CUSTOM->*` inputs that depend on legacy globals (`$TABLE`, `AppTable::$list`, `$PAGE`, `$PATH`, `$DB`), but **replace** the column loop + SSP call (current lines ~119-232) with:

```php
    use Wonder\Backend\Table\ListProvider;

    // ... existing $NAME / $TEXT / $USER assembly stays ...
    // ... existing $PAGE redirect computation stays (lines ~83-117) ...

    echo json_encode(
        ListProvider::fetch($_POST, $NAME, $TEXT, $USER, $PAGE, $PATH)
    );
```

Delete the now-unused inline `$COLUMNS` loop, the `$CUSTOM->query_ln`/`query_all_ln` lines if only used by columns, and the `$sql_details` block (moved into provider). Keep `$NAME->page`/`$NAME->length` and the redirect block (still used elsewhere / by `Field` via `$PAGE`).

- [ ] **Step 3: Dump autoload + lint**

Run:
```bash
composer dumpautoload
php -l class/Backend/Table/ListProvider.php
php -l app/api/backend/list/table.php
```
Expected: autoload regenerated; both files "No syntax errors detected".

- [ ] **Step 4: Manual end-to-end regression (endpoint unchanged behavior)**

From a site that installs this framework (Herd), open a backend list page and confirm the table still loads via AJAX exactly as before (Network tab → POST to `.../backend/list/table.php` returns the same JSON shape: `draw`, `recordsTotal`, `recordsFiltered`, `data`). Verify a table that uses `position` ordering (arrows) and a table with a `menu` action column both render.

- [ ] **Step 5: Commit**

```bash
git add class/Backend/Table/ListProvider.php app/api/backend/list/table.php
git commit -m "refactor(table): extract ListProvider; endpoint delegates to it"
```

---

## Task 6: Pre-render the first page in `Table::generate()`

Use `ListProvider::fetch()` to render the first page into `<tbody>`, and add `deferLoading` counts to the script config.

**Files:**
- Modify: `class/Backend/Table/Table.php` (`script()` ~670-714, `rowTable()` ~656-668, `generate()` ~716-738)

**Interfaces:**
- Consumes: `ListProvider::fetch()` (Task 5).
- Produces: `<tbody>` pre-filled with the first page; `config.default.deferLoading = [recordsFiltered, recordsTotal]`.

Key reuse: `script()` already assembles `$JSON` (id, url, fields, custom, default{page,length,search,order,order_direction,link}, config{table,database,query,query_filter,query_custom,search_columns}, text). Extract that assembly into a private `buildConfig(): array` so both the `<script>` and the pre-render request use one source.

- [ ] **Step 1: Extract `buildConfig()` from `script()`**

In `class/Backend/Table/Table.php`, refactor `script()` so the `$JSON` array is produced by a new private method `buildConfig()` returning the array, and `script()` calls it. (Pure move — no behavior change yet.)

- [ ] **Step 2: Add a private `prerender()` that returns [rowsHtml, deferLoading]**

Add to `Table`:

```php
        private function prerender(array $config): array
        {
            // Synthesize the request DataTables would POST for the initial page.
            $page   = (int) ($config['default']['page'] ?? 0);
            $length = (int) ($config['default']['length'] ?? 10);

            $request = $config;
            $request['draw']   = 1;
            $request['start']  = $page * $length;
            $request['length'] = $length;
            $request['search'] = ['value' => (string) ($config['default']['search'] ?? ''), 'regex' => false];
            $request['order']  = [[
                'name' => (string) ($config['default']['order'] ?? ''),
                'dir'  => (string) ($config['default']['order_direction'] ?? 'desc'),
            ]];

            $name = (object) [
                'id'         => $this->id['table'],
                'table'      => $this->table,
                'database'   => $this->database,
                'connection' => $this->mysqli,
                'field'      => [],
                'schema'     => (string) ($this->endpointValues['schema'] ?? ''),
                'link'       => $this->link,
                'page'       => $page,
                'length'     => $length,
            ];

            $text = (object) $this->text;
            $user = (object) [
                'area'      => $this->endpointValues['user_area'] ?? '',
                'authority' => $this->endpointValues['user_authority'] ?? '',
            ];
            $page_o = (object) [
                'redirect'       => '',
                'redirectBase64' => '',
                'domain'         => $_SERVER['HTTP_HOST'] ?? '',
            ];

            try {
                $result = \Wonder\Backend\Table\ListProvider::fetch($request, $name, $text, $user, $page_o, new \Wonder\App\Path);
            } catch (\Throwable $e) {
                // Pre-render is an optimization: on any failure, fall back to AJAX-only.
                return ['', null];
            }

            $rows = '';
            foreach (($result['data'] ?? []) as $row) {
                $rows .= '<tr>';
                foreach ($row as $cell) {
                    $rows .= '<td>'.$cell.'</td>';
                }
                $rows .= '</tr>';
            }

            $defer = [ (int) ($result['recordsFiltered'] ?? 0), (int) ($result['recordsTotal'] ?? 0) ];

            return [$rows, $defer];
        }
```

Note: `$name->field` is left empty here because the standalone `Table` path has no legacy `$TABLE`/`AppTable` merge; `Field` reads per-column `format` from `config.fields` (the `other` payload), which is sufficient for backend resource tables. If a specific `Field` formatter needs the merged `field` map, populate it the same way the endpoint does (legacy + `AppTable::$list` + resource schema) — verify during Step 5.

- [ ] **Step 3: Wire pre-render into `generate()` / `rowTable()`**

Change `generate()` to compute the config once, run pre-render, inject rows, and pass `deferLoading` into the script. Concretely, make `rowTable(string $rowsHtml = '')` interpolate rows into the `<tbody>`:

```php
        private function rowTable( string $rowsHtml = '' ) {
            $RETURN  = '<div class="col-12">';
            $RETURN .= '<table id="'.$this->id['table'].'" class="table table-hover w-100">';
            $RETURN .= '<thead></thead>';
            $RETURN .= '<tbody class="table-group-divider">'.$rowsHtml.'</tbody>';
            $RETURN .= '</table>';
            $RETURN .= '</div>';
            return $RETURN;
        }
```

And in `generate()`:

```php
            // (after the existing $this->query assembly)

            $config = $this->buildConfig();
            [$rowsHtml, $defer] = $this->prerender($config);
            if ($defer !== null) {
                $config['default']['deferLoading'] = $defer;
            }

            $CONTENT  = $this->rowHeader();
            $CONTENT .= $this->rowTable($rowsHtml);
            $CONTENT .= $this->script($config);
```

Update `script()` to accept the prepared config: `private function script(array $config)` and use `$config` instead of recomputing `$JSON` (it now just wraps `$config` in the `<script>` + `createDataTables(...)` call). Ensure `createDataTables` still receives the full config object including `default.deferLoading`.

- [ ] **Step 4: Lint**

Run: `php -l class/Backend/Table/Table.php`
Expected: No syntax errors.

- [ ] **Step 5: Manual end-to-end verification**

On a Herd site backend list page (before updating lib):
- View source / DOM: `<tbody>` now contains the first page `<tr>` rows server-side.
- Network tab: DataTables (old lib) still fires the initial POST and replaces the rows — **no visual regression** (graceful degradation).
- Confirm arrows/menu/formatted cells in the pre-rendered rows match what the AJAX render produces (compare a couple of cells).
- If any `Field` formatter errors during pre-render, confirm the try/catch fell back to empty rows (page still works) and decide whether to populate `$name->field` (Step 2 note).

- [ ] **Step 6: Commit**

```bash
git add class/Backend/Table/Table.php
git commit -m "feat(table): pre-render first page with deferLoading counts"
```

---

## Task 7: lib — consume `deferLoading` and rebuild dist

Make `createDataTables` pass `deferLoading` to DataTables so the pre-rendered rows are used and the initial AJAX is skipped.

**Files:**
- Modify: `/Users/andreamarinoni/Developer/packages/lib/src/build/backend/js/list.js` (`createDataTables`, ~line 43)
- Rebuild: `dist/backend/body-end.js` (compiled bundle)

**Interfaces:**
- Consumes: `config.default.deferLoading` (Task 6) — `[recordsFiltered, recordsTotal]` or absent.

- [ ] **Step 1: Add `deferLoading` to the DataTable init**

In `src/build/backend/js/list.js`, inside the `new DataTable('#'+id, { ... })` options object, add (e.g. right after `serverSide: true,`):

```js
        serverSide: true,
        ...(config.default && config.default.deferLoading ? { deferLoading: config.default.deferLoading } : {}),
```

Leave the `ajax`, `columns`, `order`, `displayStart`, `pageLength`, `search` options as-is — they already match the pre-rendered initial state coming from the framework.

- [ ] **Step 2: Rebuild the dist bundle**

Run the lib build from the lib repo root:
```bash
cd /Users/andreamarinoni/Developer/packages/lib && npm run build
```
(If the script name differs, inspect `package.json` `scripts` and run the one that compiles `src/build/backend` → `dist/backend/body-end.js`.)
Expected: `dist/backend/body-end.js` updated; `grep -c deferLoading dist/backend/body-end.js` ≥ 1.

- [ ] **Step 3: Manual end-to-end verification (aligned app + lib)**

On a site updated to the new app **and** new lib:
- Network tab on first paint: **no** initial POST to `.../backend/list/table.php`. The pre-rendered rows are shown directly.
- Trigger paging / sort / search / length-change: each fires a POST and updates correctly.
- Cross-table search: type a term that only matches a related-table column (e.g. a user's email on a list whose `searchFields` includes `user.email`) and confirm rows filter correctly and the result count is consistent.

- [ ] **Step 4: Commit (lib repo)**

```bash
cd /Users/andreamarinoni/Developer/packages/lib
git add src/build/backend/js/list.js dist/backend/body-end.js dist/backend/body-end.js.map
git commit -m "feat(list): use deferLoading to skip initial DataTables AJAX"
```

---

## Self-Review

**Spec coverage:**
- Shared `ListProvider` → Task 5. ✓
- Pre-render `deferLoading` (app) → Task 6; (lib) → Task 7. ✓
- Cross-table search config (dotted) → Task 2; descriptor pass-through → Task 3; SSP `IN` subquery → Task 1. ✓
- Security validation of descriptors → Task 4. ✓
- Graceful degradation → Task 6 (rows always emitted) + Task 7 (optional consume) + try/catch fallback. ✓
- One-hop only / no JOIN → Task 1 builder uses `IN (SELECT ...)`. ✓
- Testing: unit (Tasks 1, 2), `php -l` everywhere, manual e2e (Tasks 5, 6, 7). ✓

**Type consistency:** descriptor shape `['table','local_key','foreign_key','columns'[]]` is identical across Tasks 1–4. `resolveSearchFields`/`buildSearchWhere`/`buildColumns`/`fetch` signatures referenced consistently.

**Open verification points flagged inline:** `sqlColumnExists` signature (Task 4 Step 3); whether `Field` needs a populated `$name->field` during pre-render (Task 6 Steps 2/5); lib build script name (Task 7 Step 2). These are explicit verification steps, not placeholders.

**Ordering note:** Tasks 1–4 (cross-table search) are independent of Tasks 5–7 (pre-render) except that Task 6 depends on Task 5. They can be implemented in the listed order; the search feature is fully testable after Task 4, the pre-render after Task 7.
