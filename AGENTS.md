# AGENTS.md

## Project overview

`wonder-image/app` is the core package for the Wonder framework. It is a PHP library, not a standalone app. It provides:

- bootstrap/runtime glue in [wonder-image.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/wonder-image.php)
- backend/frontend legacy helpers under `app/`
- the newer `Model / Resource / CustomPageSchema / Repeater` architecture under `class/App/`
- Symfony Console commands used from consumer projects via `php forge ...`

Important: `forge` commands are meant to run in a consumer project such as `wonder-image/new-site`, where this package is installed under `vendor/wonder-image/app`.
There is no `forge` executable in this package root.

## Directory structure

- `app/`: legacy runtime, routes, HTTP handlers, helpers, build tasks, views, middleware
- `class/`: PHP classes, including the new architecture and console commands
- `resources/`: static package resources and geo datasets
- `docs/`: GitBook/manual docs
- `storage/`: package-local storage placeholders
- `vendor/`: Composer dependencies

Key subareas:

- `class/App/Models`: SQL/data definitions
- `class/App/Resources`: backend/API modules
- `class/App/Schema/Extensions`: reusable schema fragments for `Model`, `Resource`, `CustomPageSchema`
- `class/App/Module`: module-system discovery, manifest, state, config, registry
- `class/App/PageSchema`: custom backend pages
- `class/App/ResourceSchema`: high-level form/table/repeater DSL (used by `Resource::formSchema()`)
- `class/App/Support/Repeater.php`: repeater request + relation sync
- `class/App/Support/FormFieldElementFactory.php`: bridge from `ResourceSchema/FormField` DSL to `Elements/Form/Components/*` (low-level objects)
- `class/App/SeedDefaults.php`: canonical default payloads for `build/row`, singleton bootstrap, and empty seed-backed backend forms
- `class/Elements/Concerns/HasLinkAttributes.php`: concern condiviso per `Link`, `Button`, `Badge` e link inline di `Text`; salva `href`, `target`, `rel`, `title`, `onclick`, `download` dentro `attributes`
- `class/Elements/Components`: non-form UI components rendered via theme resolver (`Card`, `Alert`, `Text`, `Link`, `Button`, `Badge`, `ButtonGroup`, `Dropdown`, ...)
- `class/Elements/Form`: low-level form Element objects (config layer, fluent API, no HTML). `Field`, `Form`, `Components/{InputText,Select,Repeater,...}`
- `class/Themes/Form/AbstractFieldRenderer.php`: base condivisa per i Field renderer di tutti i temi (schema/error/value/label helpers, render+renderField hook)
- `class/Themes/Wonder/Form`: HTML rendering for the public-facing site (frontend theme, classes `wi-*`)
- `class/Themes/Bootstrap/Form`: HTML rendering for the admin backend (Bootstrap 5 with `form-control` / `form-floating`)
- `class/Themes/{Registry,Resolver}.php`: theme registration + per-theme lookup (no cross-theme fallback; only parent-class chain within the requested theme)
- `class/App/Theme.php`: runtime theme switcher (`Theme::set('bootstrap')` / `Theme::get()`)
- `app/config/routes`: Symfony routing definitions
- `app/http`: backend/API handlers
- `app/build/cli`, `app/build/update`, `app/build/row`, `app/build/stubs`: update/provisioning/build assets

## Setup commands

Documented package-root commands:

```bash
composer update
composer dumpautoload
```

Consumer project flow (from docs; run in the app that requires this package):

```bash
php forge config
php forge provision
php forge db:init --admin-host=127.0.0.1 --admin-port=3306 --admin-username=root --admin-password=secret
php forge update --local
php forge start
```

## Development commands

Package root:

```bash
composer dumpautoload
php -l path/to/file.php
phpDocumentor run -d ./class -t docs/class
```

Consumer project commands for integration validation:

```bash
php forge update --local
php forge start
php forge start --driver=herd --php-version=8.4
php forge start --driver=php
```

## Build / test / lint

There is no configured PHPUnit or automated test suite in this repo.

Use:

```bash
php -l path/to/file.php
composer dumpautoload
```

When touching console/bootstrap/routing/runtime behavior, also validate in a consumer project:

```bash
php forge update --local
php forge start
```

## Coding conventions

- Prefer PSR-4 classes in `class/` for new logic.
- Autoload is `Wonder\\ => class/` from `composer.json`.
- Use `apply_patch` for manual edits.
- Default to ASCII unless the file already uses non-ASCII.
- Keep comments rare and high-signal.
- Prefer `rg` / `rg --files` for search.
- This framework must remain highly extensible and customizable for consumer projects and external modules; avoid closed designs that solve only the local case.
- Code reuse is a primary design goal, especially across classes; before duplicating behavior, prefer extracting shared abstractions or reusable components.
- `Concerns` and `Contracts` are strongly preferred when they improve reuse, consistency, and extension points across the framework.
- When the same domain bundle must stay aligned across `Model::dataSchema()`, `Model::tableSchema()`, `Resource::labelSchema()`, and `Resource::formSchema()`, prefer a dedicated schema extension under `class/App/Schema/Extensions/*` instead of duplicating arrays in each class.
- Prefer expressing reusable bundle-level constraints (default country, allowed countries, required fields, derived row decorators) inside the schema extension itself instead of reapplying them ad hoc in each consuming Model/Resource/Page.
- If a schema extension also exposes helpers like `decorate(array $row): array`, keep them pure: they may enrich read rows with derived values, but must not run queries or perform persistence side effects.
- When writing or changing view/components, first verify whether an existing component can be reused or extended instead of duplicating markup or creating a new ad-hoc component.
- The canonical module format is a Composer package, not a folder embedded in the core package.
- Standard module package naming is `wonder-image/<slug>`.
- Standard module namespace base is `Wonder\\Plugin\\<StudlySlug>\\`.
- For backend forms:
  - `Model::tableSchema()` = SQL structure
  - `Model::dataSchema()` = data treatment / prepare / upload
  - `Resource::formSchema()` = backend inputs (high-level DSL via `ResourceSchema/FormField`)
  - Under the hood, rendering goes through `Elements/Form` + `Themes/Bootstrap/Form/Components/*` via `FormFieldElementFactory`. See the Form / Element / Theme system section below.
- For non-CRUD backend pages, use `CustomPageSchema`.
- For repeatable rows, use `FormField::repeater()`, `RepeaterColumn`, and `Wonder\App\Support\Repeater`.
- When changing architecture, rendering flow, layout structure, bootstrap/runtime setup, or developer-facing conventions, the work is not complete until all three are updated in the same change:
  - the related GitBook docs under `docs/app/*`
  - `AGENTS.md`
  - the relevant AI skill source/fork/prompt that codifies the affected workflow or architecture guidance

## Architecture notes

- The package still contains legacy runtime code under `app/`, but new work should follow the `class/App/*` architecture.
- Architectural choices should favor extension, override, composition, and reuse over one-off implementations tied to a single project need.
- `class/App/Schema/Extensions/*` is the place for reusable compound schema bundles (for example address/contact/fiscal blocks) that must generate coherent fragments for `dataSchema()`, `tableSchema()`, `labelSchema()`, and `formSchema()` without adding automatic registration to the core. When useful, the same extension may also expose pure row decorators for `Model::decorate()`.
- `Wonder\\App\\RuntimeDefaults` is for runtime fallbacks used while rendering or bootstrapping in-memory config; `Wonder\\App\\SeedDefaults` is for idempotent seed/bootstrap payloads used by `build/row` and seed-backed singleton forms.
- `wonder-image.php` bootstraps the package by resolving the consumer project root and loading config/services/middleware.
- `Credentials::loadEnv()` must resolve `.env` from the consumer `ROOT`, never from the package directory under `vendor/`.
- Backend/API routes are driven from `app/config/routes` and the `ResourceRouteRegistrar`.
- External modules are enabled by the consumer in `custom/config/modules.php`.
- External module entrypoints should implement `Wonder\\App\\Module\\Contracts\\ModuleInterface`.
- Module routes should live in `config/routes/route.frontend.php`, `route.backend.php`, `route.api.php` inside the module package and be loaded by the core registrars.
- The core preloads a minimal translation context before model discovery, so module extensions and dynamic schema code may safely call `__t()` during early bootstrap.
- Runtime module validation must not require the package `composer.json`, because some production deploys strip it from installed packages.
- Composer module discovery must remain compatible with both `vendor/composer/installed.php` and `vendor/composer/installed.json`, and must keep a filesystem fallback for `vendor/wonder-image/*/module.json`, because deploy environments may expose different metadata formats.
- `build/src/backend` and `build/table` have been intentionally cleaned out. Do not reintroduce them for new modules.
- `SortableInput` is deprecated. Keep it only for compatibility; do not add new usages.
- Local Herd routing uses a global driver stub:
  - `app/build/stubs/WonderValetDriver.php`
  - generated into `~/Library/Application Support/Herd/config/valet/Drivers/WonderValetDriver.php` by consumer-project Forge commands

## Form / Element / Theme system

Two-layer architecture for building and rendering forms:

- **`class/Elements/Form/`** — config layer. Fluent API objects
  (`InputText`, `Select`, `Repeater`, ...) that describe a field but
  contain **no HTML**. Each Component extends `Elements/Form/Field`
  and exposes builder methods (`label()`, `value()`, `required()`,
  `placeholder()`, etc.).
- **`class/Themes/Wonder/Form/`** — renderer layer for the **public
  frontend** (CSS classes `wi-*`).
- **`class/Themes/Bootstrap/Form/`** — renderer layer for the **admin
  backend** (Bootstrap 5 `form-control`, `form-floating`, ...).

Theme matching is automatic via namespace mirroring done by
`Wonder\Themes\Resolver`:

```
Wonder\Elements\Form\Components\InputText
        ↓ Resolver maps to current theme
Wonder\Themes\{Wonder|Bootstrap}\Form\Components\InputText
```

The Resolver looks up renderers **only inside the requested theme**.
No cross-theme fallback: if a renderer is missing in the active theme,
the Resolver throws `RuntimeException` with the attempted class names.
Within a single theme, the Resolver walks the element-class chain
(concrete class → parent class → ...) so a missing
`Themes/<T>/Form/Components/InputText` falls back to
`Themes/<T>/Form/Field` (or another ancestor) if present — this is
standard OOP, not theme bypass.

Shared base for renderers: `Themes/Form/AbstractFieldRenderer`. It
holds the schema, error/value helpers, label resolution, and the
`render() → renderInput() → renderField()` pipeline. Each theme's
`Field` extends it and overrides only the HTML-specific bits
(`renderLabel`, `renderError`, `inputClass`, optional `renderField`
wrapping).

### Switching theme at runtime

```php
use Wonder\App\Theme;
Theme::set('bootstrap');   // backend pages
Theme::set('wonder');      // public frontend (default)
```

Or per-call without touching global state:

```php
echo $field->render('bootstrap');   // one-off rendering
```

### Bridge with `Resource::formSchema()`

The high-level DSL (`ResourceSchema/FormSchema` + `FormField` /
`FormField`) goes through `Wonder\App\Support\FormFieldElementFactory`:
it converts each `FormField` to the matching `Elements/Form/Components/*`
object, hydrates it (label, value, attributes, error), and calls
`$element->render($theme)`. So consumers writing `Resource::formSchema()`
automatically benefit from the Element + Theme system.

### Adding a new Component

1. Create `class/Elements/Form/Components/<Name>.php` extending
   `Wonder\Elements\Form\Field`. Pure config (no HTML).
2. Create `class/Themes/Wonder/Form/Components/<Name>.php` extending
   `Wonder\Themes\Wonder\Form\Field` (which extends
   `Themes\Form\AbstractFieldRenderer`). Implement `renderInput(): string`.
3. Create `class/Themes/Bootstrap/Form/Components/<Name>.php` extending
   `Wonder\Themes\Bootstrap\Form\Field` (same base). Implement
   `renderInput(): string`.
4. Optional: add a case in `FormFieldElementFactory::make()` so the
   high-level `FormSchema` DSL can build it too.

No registration step needed: the Resolver discovers the new component
by namespace convention. Helpers (`hasError`, `resolvedLabel`,
`hasValue`, schema access) come from `AbstractFieldRenderer` — no
need to re-implement them per theme.

### Adding a new Theme

1. Create `class/Themes/<MyTheme>/Theme.php` implementing
   `Themes\Contracts\Theme` with `key()` (e.g. `"tailwind"`) and
   `namespace()` (e.g. `"Tailwind"`).
2. Register it: `Themes\Registry::register(\Wonder\Themes\Tailwind\Theme::class)`
   in your bootstrap (or in `Registry::boot()` if it should ship core).
3. Create `class/Themes/<MyTheme>/Form/Field.php` extending
   `Themes\Form\AbstractFieldRenderer`; implement `renderLabel()`,
   `renderError()`, `inputClass()`. Override `renderField()` if you
   need a wrapping container.
4. Create the per-Component renderers under
   `class/Themes/<MyTheme>/Form/Components/*.php`. You can leave gaps:
   the parent-class fallback within the same theme will use `Field`
   if a specific renderer is missing.

### Disabling floating labels

Both single Fields and entire Forms expose `noFloating()`:

```php
(new InputText('nick'))->label('Nickname')->noFloating();    // single field

(new Form())                                                  // whole form
    ->noFloating()
    ->components([
        (new InputText('name'))->label('Nome'),               // inherits no-floating
        (new InputEmail('email'))->label('Email')->noFloating(false),  // override → keeps floating
    ]);
```

Theme behavior:
- Wonder: adds `wi-nf` to `.wi-input-container` (frontend CSS removes
  the floating animation).
- Bootstrap: skips the `<div class="form-floating">` wrap.

Precedence: a Form's `noFloating()` is propagated to children only if
the child has not set the flag explicitly (see
`Themes\Wonder\Form\Form::propagateNoFloating()`).

### Common pitfalls

- **Do NOT put rendering logic in `Elements/`**. The config layer is
  HTML-agnostic. Some legacy `renderInput()` stubs (`return ''`) exist
  in a few `Elements/Form/Components/*` files — they are dead code and
  safe to remove during cleanup.
- **Repeater is intentionally absent from `Themes/Wonder/Form/`**. The
  frontend never renders repeaters. Calling `->render('wonder')` on a
  `Repeater` raises `RuntimeException` (no silent fallback to
  Bootstrap).
- **No cross-theme fallback**. If you need a Component in a theme that
  doesn't have it, implement it. The Resolver will NOT borrow from
  another theme to fill the gap.
- **`Field` is abstract**. Don't instantiate it directly; use a concrete
  Component (`InputText`, `Select`, ...).

Full docs in `docs/app/elementi/form-system.md`.

## AI skills (Claude Code / Cursor / Codex)

The repo uses **AI skills** managed via `npx skills` — prompt-driven
helpers invoked at *development time* by AI assistants (Claude Code,
Cursor, Codex, etc.) when you chat with them.

Skills are installed under `.agents/skills/<slug>/` (universal folder,
symlinked into `.claude/skills/`, `.cursor/`, etc. by the installer).
Both `.agents/` and `.claude/` are gitignored — the skills are managed
by an external CLI and auto-updated, not source code.

Install / manage:

```bash
php forge skills                      # installs/updates Wonder-recommended skills locally
npx skills add wonder-image/skills    # manual alternative for Wonder skills
npx skills add pbakaus/impeccable     # currently used skill for UI design audit/craft
npx skills list                       # list installed
npx skills update                     # auto-update all installed skills
npx skills remove <slug>
```

`php forge config` prova anche a sincronizzare automaticamente le skill
raccomandate per Wonder (`wonder-image/skills` e `pbakaus/impeccable`)
come tooling locale del developer. In caso di problemi o per
risincronizzarle manualmente, usa `php forge skills`.

The skills are not versioned in this repo (intentional): updates flow
through the external CLI, not through git.

Do not commit `.agents/` or hand-edit files inside it. If a skill needs
project-specific customization, fork it under a different slug rather
than patching the installed copy.

If an architectural change alters conventions, extension points,
directory layout, bootstrap flow, or developer workflow, updating the
relevant skill is mandatory. Treat the change as incomplete until the
skill source/fork has been updated to reflect the new architecture and
the repo documentation has been aligned. Do not patch `.agents/` in
place; update the maintained skill source/fork and then reinstall or
sync it through `npx skills`.

## Files or areas to avoid changing

- `vendor/`: never edit dependencies directly in this repo
- `docs/class/`: generated phpDocumentor output
- `.phpdoc/`: generated/config support area
- `example/`: ignored helper area, not source of truth
- `COMMAND.md`: useful reference, but ignored; do not treat it as canonical architecture documentation
- legacy cleanup targets:
  - do not recreate `app/build/src/backend/*`
  - do not recreate `app/build/table/*`
- do not use `custom/...` copy-paste integration as the primary pattern for new modules; prefer package-based module registration
- avoid changing `wonder-image.php` unless the task is truly bootstrap/runtime-related

## How to validate changes before committing

1. Lint every touched PHP file:

```bash
php -l path/to/file.php
```

2. Refresh autoload if classes moved or were added:

```bash
composer dumpautoload
```

3. If you changed docs or `AGENTS.md`, check links/paths and instruction
   consistency manually.

4. If the change is architectural, confirm in the same work that you
   updated:

- the relevant docs under `docs/app/*`
- `AGENTS.md`
- the relevant AI skill source/fork/prompt

5. If you changed any of these areas, validate in a consumer project:

- `class/Console/*`
- `class/App/Resource*`
- `class/App/Model*`
- `app/config/routes/*`
- `app/http/*`
- `wonder-image.php`

Use:

```bash
php forge update --local
php forge start
```

6. For Herd-specific local routing changes, also validate:

```bash
herd restart
```

## Repo-specific gotchas

- This repo is a package, so some commands in docs only work from a consumer app, not here.
- `php forge ...` examples in docs are integration commands for the consumer project, not package-root commands.
- Some fixes require syncing/testing against a sibling consumer project (for example `new-site`) because the real runtime lives there.
- `app/config/app/table.php` still loads any PHP file under `app/build/table/`; do not add new files there.
- The framework mixes legacy globals/runtime helpers with newer class-based modules. Before refactoring, inspect both `app/` and `class/` paths involved in the flow.
- Minimum supported PHP in `composer.json` is `^8.2`, with Composer platform pinned to `8.2.30`.
