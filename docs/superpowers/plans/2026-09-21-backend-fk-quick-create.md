# Backend FK quick-create Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an opt-in "+ Aggiungi" on backend FK inputs that opens a modal to create the related resource inline and injects/selects the new option.

**Architecture:** A declaration concern (`HasQuickCreate`) records target-slug + subset fields on the input; the Bootstrap theme's base `Field` renders a "+" and a modal (subset fields reused from the target resource) when the current user may create the target; a session-gated backend proxy creates the row by calling the target's API store server-side as `@system` and returns `{id,label}`; inline JS injects+selects the option per input family.

**Tech Stack:** PHP 8.4, `Wonder\App\ResourceSchema\*` (FormField/Input hierarchy), `Wonder\Themes\Bootstrap\*` renderers, `Wonder\Backend\Support\*` controllers, `curlJson`, Bootstrap 5 modal, jsTree (checkTree).

**Spec:** `docs/superpowers/specs/2026-09-21-backend-fk-quick-create-design.md`

## Global Constraints

- Framework repo `wonder-image/app`; new logic under `class/App/*` or `class/Backend/*`, API/handlers under `app/http/*`, renderers under `class/Themes/Bootstrap/*` (Core Rules, wi-app skill).
- Every input goes through the `FormField`/`Input` hierarchy; no bespoke `<input>` HTML at call sites.
- `php -l` on every touched PHP file; `composer dump-autoload` after adding classes.
- `tests/` is gitignored but tracked: new test files need `git add -f`.
- The `@system` (`api_internal_user`) token is used **server-side only**; never emitted to the browser.
- Form-submit errors surface as **alerts** (backend rule), never inline text.
- `wonder-image/lib` is NOT modified (backend-only, Bootstrap theme, inline JS). If a shared design-system asset emerges, stop and coordinate a separate lib change.
- Full runtime validation (modal → create → option refresh) requires a site (`php forge start`); it cannot run in the framework repo alone.

---

## File Structure

- Create `class/App/ResourceSchema/Inputs/Concerns/HasQuickCreate.php` — declaration + config storage/reading.
- Modify `class/App/ResourceSchema/Inputs/{InputSelect,InputSelectSearch,InputCheckbox,InputCheckTree,InputSearchRemote,InputDynamicCheck}.php` — `use HasQuickCreate;`.
- Create `class/Backend/Support/QuickCreateAuthorizer.php` — single source of truth for "may current user create target".
- Create `class/Backend/Support/QuickCreateController.php` — proxy: authorize, whitelist subset, call target API store as `@system`, derive label, return array.
- Create `app/http/backend/resource/quick-create.php` — thin entry → controller, echoes JSON.
- Modify `app/config/routes/route.backend.php` — register `resource.quick-create`.
- Modify `class/Themes/Bootstrap/Form/Field.php` — after the input, render "+" + modal + emit the shared script once, when quick-create is set and authorized.
- Create `docs/app/concetti/form/quick-create.md` + add to `docs/app/SUMMARY.md`.
- Create `tests/quick-create.php` — unit-ish for concern, authorizer, controller pure methods, rendered HTML.

---

### Task 1: `HasQuickCreate` concern + input wiring

**Files:**
- Create: `class/App/ResourceSchema/Inputs/Concerns/HasQuickCreate.php`
- Modify: `class/App/ResourceSchema/Inputs/InputSelect.php`, `InputSelectSearch.php`, `InputCheckbox.php`, `InputCheckTree.php`, `InputSearchRemote.php`, `InputDynamicCheck.php`
- Test: `tests/quick-create.php`

**Interfaces:**
- Consumes: `Input::context(string $key, mixed $value)` (stores under `schema.context.<key>`), `Resource::slug()`.
- Produces: `quickCreate(string $resourceClass, array $fields, ?string $label = null): static`; config readable at `->get()['context']['quick_create']` = `['resource' => <class>, 'slug' => <slug>, 'fields' => [...], 'label' => <field|null>]`.

- [ ] **Step 1: Write the failing test** (append to `tests/quick-create.php`)

```php
<?php
require dirname(__DIR__).'/vendor/autoload.php';
use Wonder\App\ResourceSchema\FormField;

$checks = 0;
$check = static function (bool $c, string $m) use (&$checks): void {
    if (!$c) { throw new RuntimeException($m); }
    $checks++;
};

// A tiny fake target resource with a slug.
$fakeResource = new class {
    public static function slug(): string { return 'category'; }
};
$fakeClass = get_class($fakeResource);

$input = FormField::key('category_id')->select(['1' => 'A'])->quickCreate($fakeClass, ['name'], 'name');
$config = ($input->get()['context']['quick_create'] ?? null);

$check(is_array($config), 'quick_create config stored');
$check($config['slug'] === 'category', 'slug resolved from target');
$check($config['fields'] === ['name'], 'subset fields stored');
$check($config['label'] === 'name', 'label field stored');
$check($config['resource'] === $fakeClass, 'target class stored');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/quick-create.php`
Expected: FAIL — `Call to undefined method ...::quickCreate()`.

- [ ] **Step 3: Write the concern**

Create `class/App/ResourceSchema/Inputs/Concerns/HasQuickCreate.php`:

```php
<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Aggiunge a un input FK una "creazione rapida": un "+" apre un modal per
 * creare una riga della risorsa collegata (sottoinsieme di campi) e la aggiunge
 * come opzione. Vedi docs/app/concetti/form/quick-create.md.
 */
trait HasQuickCreate
{
    /**
     * @param string      $resourceClass FQCN della Resource collegata (deve esporre lo store API).
     * @param array       $fields        chiavi del sottoinsieme da mostrare nel modal.
     * @param string|null $label         campo che fa da etichetta dell'opzione (default: label del target).
     */
    public function quickCreate(string $resourceClass, array $fields, ?string $label = null): static
    {
        return $this->context('quick_create', [
            'resource' => $resourceClass,
            'slug'     => $resourceClass::slug(),
            'fields'   => array_values($fields),
            'label'    => $label,
        ]);
    }
}
```

- [ ] **Step 4: Wire the concern into the six inputs**

In each of `InputSelect.php`, `InputSelectSearch.php`, `InputCheckbox.php`, `InputCheckTree.php`, `InputSearchRemote.php`, `InputDynamicCheck.php`: add the import and `use` inside the class body, mirroring the existing trait usage (e.g. `InputSelect` already `use HasOptions;`).

```php
use Wonder\App\ResourceSchema\Inputs\Concerns\HasQuickCreate;
// ... inside class body, alongside the other `use` traits:
use HasQuickCreate;
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer dump-autoload && php tests/quick-create.php`
Expected: PASS.

- [ ] **Step 6: Lint + commit**

```bash
php -l class/App/ResourceSchema/Inputs/Concerns/HasQuickCreate.php
git add -f tests/quick-create.php
git add class/App/ResourceSchema/Inputs/
git commit -m "FK quick-create: declaration concern on FK inputs"
```

---

### Task 2: `QuickCreateAuthorizer` — permission single source of truth

**Files:**
- Create: `class/Backend/Support/QuickCreateAuthorizer.php`
- Test: `tests/quick-create.php`

**Interfaces:**
- Consumes: `TargetResource::permissionSchema()->get('backend')` (array keyed by action → authority list).
- Produces: `QuickCreateAuthorizer::createAuthority(string $resourceClass): array` (the authority list, `backend.create` with fallback to `backend.store`); `QuickCreateAuthorizer::userCanCreate(string $resourceClass, array $userAuthority): bool`.

- [ ] **Step 1: Write the failing test** (append)

```php
use Wonder\Backend\Support\QuickCreateAuthorizer;

$permResource = new class {
    public static function slug(): string { return 'category'; }
    public static function permissionSchema(): object {
        return new class {
            public function get(string $k): array {
                return $k === 'backend' ? ['create' => ['admin', 'editor']] : [];
            }
        };
    }
};
$permClass = get_class($permResource);

$check(QuickCreateAuthorizer::createAuthority($permClass) === ['admin', 'editor'], 'reads backend.create authority');
$check(QuickCreateAuthorizer::userCanCreate($permClass, ['editor']) === true, 'intersecting authority allowed');
$check(QuickCreateAuthorizer::userCanCreate($permClass, ['viewer']) === false, 'non-intersecting denied');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/quick-create.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the authorizer**

Create `class/Backend/Support/QuickCreateAuthorizer.php`:

```php
<?php

namespace Wonder\Backend\Support;

/**
 * Fonte unica di verità per "l'utente backend può creare la risorsa target?".
 * Usata dal renderer (visibilità del "+") e dal controller (check server-side).
 */
final class QuickCreateAuthorizer
{
    /** @return list<string> authority di creazione backend (create, ripiego su store). */
    public static function createAuthority(string $resourceClass): array
    {
        $backend = (array) $resourceClass::permissionSchema()->get('backend');
        $authority = $backend['create'] ?? $backend['store'] ?? [];

        return array_values((array) $authority);
    }

    public static function userCanCreate(string $resourceClass, array $userAuthority): bool
    {
        $required = self::createAuthority($resourceClass);

        // Nessuna authority richiesta = aperta a ogni utente backend autenticato.
        if ($required === []) {
            return true;
        }

        return array_intersect($required, $userAuthority) !== [];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/quick-create.php`
Expected: PASS.

- [ ] **Step 5: Lint + commit**

```bash
php -l class/Backend/Support/QuickCreateAuthorizer.php
git add class/Backend/Support/QuickCreateAuthorizer.php tests/quick-create.php
git commit -m "FK quick-create: authorization helper"
```

---

### Task 3: `QuickCreateController` + route + entry (server proxy)

**Files:**
- Create: `class/Backend/Support/QuickCreateController.php`
- Create: `app/http/backend/resource/quick-create.php`
- Modify: `app/config/routes/route.backend.php`
- Test: `tests/quick-create.php`

**Interfaces:**
- Consumes: `ResourceRegistry::has/resolve`, `QuickCreateAuthorizer::userCanCreate`, `infoUser('@system','username')`, `curlJson`, `Path::$api`.
- Produces: `QuickCreateController::whitelist(array $fields, array $post): array` (keep only declared subset keys); `QuickCreateController::handle(array $post, array $userAuthority): array` returning `['success'=>bool, 'id'=>int, 'label'=>string]` or `['success'=>false, 'error'=>string, 'status'=>int]`.

> **Integration seams to confirm while implementing (read, don't guess):**
> 1. The api store absolute URL: build from `Path::$api` + `'/resource/'.$slug.'/'` (mirror `home.php` which uses `$PATH->api.'/app/update/'`). Confirm `Path::$api` value in `class/App/Path.php`.
> 2. The api store request/response contract in `app/http/api/resource/index.php` (store branch) + `ResourceApiController`: what body it reads and what JSON it returns (the created id key). Align `curlJson` payload + parse the id from the response accordingly.
> 3. Label when not in the subset: read the created row's label field via the target model (`$resourceClass::modelClass()` + a by-id select), else use the submitted subset value.

- [ ] **Step 1: Write the failing test for `whitelist`** (append — the pure, DB-free part)

```php
use Wonder\Backend\Support\QuickCreateController;

$kept = QuickCreateController::whitelist(['name', 'slug'], ['name' => 'Scarpe', 'slug' => 'scarpe', 'evil' => 'x', 'id' => '9']);
$check($kept === ['name' => 'Scarpe', 'slug' => 'scarpe'], 'whitelist keeps only declared subset keys');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/quick-create.php`
Expected: FAIL — class/method not found.

- [ ] **Step 3: Write the controller** (`handle()` orchestrates; keep pure parts static/testable)

Create `class/Backend/Support/QuickCreateController.php`:

```php
<?php

namespace Wonder\Backend\Support;

use Wonder\App\Path;
use Wonder\App\ResourceRegistry;

/**
 * Proxy backend della creazione rapida: autorizza, whitelista il sottoinsieme,
 * chiama lo store API della risorsa target come @system (token solo server-side)
 * e restituisce {id, label}. Vedi docs/app/concetti/form/quick-create.md.
 */
final class QuickCreateController
{
    /** @return array<string,mixed> solo le chiavi del subset dichiarato. */
    public static function whitelist(array $fields, array $post): array
    {
        return array_intersect_key($post, array_flip(array_values($fields)));
    }

    /**
     * @param array<string,mixed> $post          POST del modal (resource, csrf, campi subset).
     * @param list<string>        $userAuthority authority dell'utente backend corrente.
     * @return array<string,mixed> {success,id,label} | {success:false,error,status}
     */
    public static function handle(array $post, array $userAuthority): array
    {
        $slug = trim((string) ($post['resource'] ?? ''));

        if ($slug === '' || !ResourceRegistry::has($slug)) {
            return ['success' => false, 'error' => 'Risorsa non trovata.', 'status' => 404];
        }

        $resourceClass = ResourceRegistry::resolve($slug);

        if (!QuickCreateAuthorizer::userCanCreate($resourceClass, $userAuthority)) {
            return ['success' => false, 'error' => 'Non autorizzato a creare questa risorsa.', 'status' => 403];
        }

        $config = static::declaredConfig($resourceClass, $post);      // vedi Step 3b
        $values = static::whitelist($config['fields'], $post);

        $systemToken = static::systemToken();
        if ($systemToken === '') {
            return ['success' => false, 'error' => 'Token API di sistema non disponibile.', 'status' => 500];
        }

        // URL assoluto dello store API (confermare Path::$api).
        $url = rtrim(Path::$api, '/').'/resource/'.$slug.'/';

        $response = curlJson($url, 'POST', $values, $systemToken);   // token solo server-side

        $id = static::extractId($response);                          // confermare shape risposta
        if ($id <= 0) {
            return ['success' => false, 'error' => static::storeError($response), 'status' => 422];
        }

        return ['success' => true, 'id' => $id, 'label' => static::label($resourceClass, $config, $values, $id)];
    }

    private static function systemToken(): string
    {
        $user = function_exists('infoUser') ? infoUser('@system', 'username') : null;

        return trim((string) ($user->api_internal_user->token ?? ''));
    }

    // --- da rifinire in base al contratto reale (seams sopra) ---

    /** Config quick_create della risorsa target per il campo che ha inviato il modal. */
    private static function declaredConfig(string $resourceClass, array $post): array
    {
        // Il modal invia anche l'elenco chiavi del subset (campo hidden 'quick_fields'),
        // così il controller non deve reintrospezionare da quale campo proviene.
        $fields = array_values(array_filter((array) ($post['quick_fields'] ?? []), 'is_string'));

        return ['fields' => $fields, 'label' => (string) ($post['quick_label'] ?? '')];
    }

    private static function extractId(mixed $response): int
    {
        // Confermare la chiave dell'id nella risposta dello store (es. id / insert_id / data.id).
        if (!is_array($response)) { return 0; }

        return (int) ($response['id'] ?? $response['insert_id'] ?? ($response['data']['id'] ?? 0));
    }

    private static function storeError(mixed $response): string
    {
        $msg = is_array($response) ? (string) ($response['message'] ?? $response['error'] ?? '') : '';

        return $msg !== '' ? $msg : 'Creazione non riuscita.';
    }

    private static function label(string $resourceClass, array $config, array $values, int $id): string
    {
        $field = $config['label'] !== '' ? $config['label'] : null;

        if ($field !== null && array_key_exists($field, $values)) {
            return (string) $values[$field];
        }

        // Ripiego: leggi la riga creata dal model target (confermare API di lettura per id).
        $row = $resourceClass::modelClass()::query()->getById($id);   // adeguare al vero helper del model

        return (string) ($row[$field] ?? $row['name'] ?? $id);
    }
}
```

> Note for the executor: `declaredConfig` reads `quick_fields`/`quick_label` hidden inputs emitted by the modal (Task 4) — this avoids re-introspecting which field opened the modal and keeps the subset authoritative server-side (still re-whitelisted). Adjust `label()`'s model read and `extractId()` to the confirmed store contract.

- [ ] **Step 4: Run the `whitelist` test to verify it passes**

Run: `php tests/quick-create.php`
Expected: PASS (the DB-free `whitelist` assertion; `handle()` full path is integration-tested from a site).

- [ ] **Step 5: Create the thin entry**

Create `app/http/backend/resource/quick-create.php`:

```php
<?php

// Entry http della quick-create (sessione backend): delega al controller e
// risponde JSON. Nessuna logica qui.
use Wonder\Backend\Support\QuickCreateController;

header('Content-Type: application/json; charset=utf-8');

$userAuthority = (array) ($USER->authority ?? []);
$result = QuickCreateController::handle($_POST, $userAuthority);

http_response_code((int) ($result['status'] ?? 200));
unset($result['status']);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
```

- [ ] **Step 6: Register the route** in `app/config/routes/route.backend.php` (inside the backend area group, near the scheduler route)

```php
        Route::post('/resource/quick-create/', $ROOT_APP.'/http/backend/resource/quick-create.php')
            ->name('resource.quick-create')->permit([]);   // authz fine per-target nel controller
```

- [ ] **Step 7: Lint + commit**

```bash
php -l class/Backend/Support/QuickCreateController.php
php -l app/http/backend/resource/quick-create.php
php -l app/config/routes/route.backend.php
git add class/Backend/Support/QuickCreateController.php app/http/backend/resource/quick-create.php app/config/routes/route.backend.php tests/quick-create.php
git commit -m "FK quick-create: backend proxy controller, entry and route"
```

---

### Task 4: Bootstrap renderer — "+" + modal + shared script

**Files:**
- Modify: `class/Themes/Bootstrap/Form/Field.php` (base renderer for all Bootstrap inputs)
- Test: `tests/quick-create.php`

**Interfaces:**
- Consumes: input schema at `$class->schema['context']['quick_create']`; `QuickCreateAuthorizer::userCanCreate`; `Resource::getInput($key)` to render subset fields via the Bootstrap pipeline; `__r('backend.resource.quick-create')`.
- Produces: appended HTML (a `Button` "+" + a Bootstrap modal with `quick_fields`/`quick_label` hidden inputs and the CSRF token) after the input, plus the shared `<script>` emitted once (static guard).

> **Confirm while implementing:** the exact base render seam in `class/Themes/Bootstrap/Form/Field.php` where the input HTML is produced (wrap its return); how the current backend user/authority is available inside the renderer (a global `$USER` or a context accessor); how a backend CSRF token is obtained (mirror how existing backend forms embed it). Reuse the shared `Button` primitive for the "+".

- [ ] **Step 1: Write the failing test** (rendered-HTML level, append)

```php
use Wonder\App\ResourceSchema\FormField;
use Wonder\Themes\Bootstrap\Form\Field as BootstrapField;

// Requires a target resource authorized for the test user; use a fake with open authority ([]).
$openTarget = new class {
    public static function slug(): string { return 'category'; }
    public static function permissionSchema(): object {
        return new class { public function get(string $k): array { return []; } };
    }
    public static function getInput(string $key): object { return FormField::key($key)->text(); }
};
$openClass = get_class($openTarget);

$input = FormField::key('category_id')->select(['1' => 'A'])->quickCreate($openClass, ['name'], 'name');
$html = (new BootstrapField())->render($input);   // adeguare alla vera firma di render()

$check(str_contains($html, 'data-wi-quick-create'), 'renders the quick-create trigger');
$check(str_contains($html, 'quick_fields'), 'emits the subset hidden field');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/quick-create.php`
Expected: FAIL — no quick-create markup yet.

- [ ] **Step 3: Implement rendering** in `class/Themes/Bootstrap/Form/Field.php`

After computing the input HTML in the base `render()`, when `schema.context.quick_create` is set AND `QuickCreateAuthorizer::userCanCreate($config['resource'], $userAuthority)`, append:
- a `Button` (shared `Wonder\Elements\Components\Button`) with `data-wi-quick-create="<modalId>"`;
- a Bootstrap modal (`modal-dialog-centered`, unique `<modalId>`) whose body renders each subset field via `$config['resource']::getInput($key)` through the Bootstrap pipeline, plus hidden inputs `quick_fields[]` (each subset key), `quick_label` (the label field), `resource` (the slug) and the backend CSRF token;
- the shared `<script>` (Task 5) emitted once via a `private static bool $scriptEmitted` guard on the renderer.

Representative structure (adapt to the real `Field` base):

```php
// inside render(), after $inputHtml is built:
$quick = $this->schema['context']['quick_create'] ?? null;
if (is_array($quick) && \Wonder\Backend\Support\QuickCreateAuthorizer::userCanCreate($quick['resource'], (array) ($GLOBALS['USER']->authority ?? []))) {
    $inputHtml .= $this->renderQuickCreate($quick);   // "+" + modal + (once) script
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/quick-create.php`
Expected: PASS.

- [ ] **Step 5: Lint + commit**

```bash
php -l class/Themes/Bootstrap/Form/Field.php
git add class/Themes/Bootstrap/Form/Field.php tests/quick-create.php
git commit -m "FK quick-create: Bootstrap '+' trigger and modal rendering"
```

---

### Task 5: Client JS — submit + per-input-family adapters

**Files:**
- Modify: `class/Themes/Bootstrap/Form/Field.php` (the shared `<script>` string from Task 4, Step 3)

**Interfaces:**
- Consumes: the modal DOM (`data-wi-quick-create`, hidden `resource`/`quick_fields`/`quick_label`/CSRF), the endpoint `__r('backend.resource.quick-create')`.
- Produces: `window.wiQuickCreate` with `submit(modalEl)` and an adapter registry keyed by input family; on `{id,label}` it injects+selects the new option.

- [ ] **Step 1: Author the shared script** (no PHP unit; validated from a site)

The script (emitted once) must:
1. Delegate clicks on `[data-wi-quick-create]` to open the target modal.
2. On modal submit: collect the subset field values + hidden `resource`/`quick_fields`/`quick_label` + CSRF, `fetch` POST to the quick-create endpoint (`credentials: 'same-origin'`).
3. On `{success:true,id,label}`: find the FK input the modal belongs to and call the family adapter; then close the modal and reset it.
4. On `{success:false,error}`: show `error` as an alert (`alertToast("custom","error", "Errore", error)`), not inline.

Adapters (by the FK input's type, read from a `data-wi-input-family` attribute set by the renderer):
- `select` / `selectSearch`: append `new Option(label, id, true, true)`; for selectSearch, refresh the widget instance.
- `checkbox`: append a checked `<input type=checkbox value=id> label` list item.
- `checkTree`: `jstree` → `create_node` under the root then `check_node`.
- `searchRemote`: inject `{id,label}` into the widget's option set and select it.
- `dynamicCheck`: re-fetch the input's `url` (re-render the list), then check the new id.

- [ ] **Step 2: Verify from a site**

From a site (`php forge start`): open a resource add/edit page whose FK field declares `quickCreate(...)`; click "+", fill the subset, submit; confirm the option appears selected for each of select/selectSearch, checkbox/checkTree, searchRemote, dynamicCheck; confirm a store error shows as a toast.

- [ ] **Step 3: Commit**

```bash
git add class/Themes/Bootstrap/Form/Field.php
git commit -m "FK quick-create: client submit and per-input-family adapters"
```

---

### Task 6: Docs

**Files:**
- Create: `docs/app/concetti/form/quick-create.md`
- Modify: `docs/app/SUMMARY.md` (add under the Form section), `docs/app/concetti/form/README.md` (one-line pointer)

- [ ] **Step 1: Write the doc** — cover: the `->quickCreate(TargetResource::class, ['name'], label: 'name')` API; the precondition (target must expose the api store); that create goes through the target's store as `@system` server-side; permission gating; supported input families; the "subset must create a valid row" constraint; that errors show as alerts. Reference the spec.

- [ ] **Step 2: Commit**

```bash
git add docs/app/concetti/form/quick-create.md docs/app/SUMMARY.md docs/app/concetti/form/README.md
git commit -m "Document FK quick-create"
```

---

## Self-Review

**Spec coverage:** declaration (Task 1) · permission gate UI+server (Task 2, 4, 3) · server proxy as @system (Task 3) · modal render with subset reused from target (Task 4) · four input adapters (Task 5) · constraints/errors-as-alert (Task 3 error path + Task 5 toast) · lib not touched (Global Constraints) · docs (Task 6). All spec sections map to a task.

**Placeholder scan:** integration seams (store URL/response shape, model by-id read, real `Field` render seam, CSRF/user accessor) are explicitly flagged as "read the existing code to confirm" with the file to read — not silent TBDs. The executor confirms them against named files while implementing Tasks 3–4.

**Type consistency:** `quickCreate(...)` → config `['resource','slug','fields','label']` used identically in Tasks 3–4; `QuickCreateAuthorizer::userCanCreate(string,array)` used by both renderer (Task 4) and controller (Task 3); `QuickCreateController::handle(array,array)` returns `{success,id,label}|{success:false,error,status}` consumed by the entry (Task 3, Step 5) and the JS (Task 5).
