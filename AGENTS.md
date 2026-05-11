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
- `class/App/Module`: module-system discovery, manifest, state, config, registry
- `class/App/PageSchema`: custom backend pages
- `class/App/ResourceSchema`: form/table/repeater DSL
- `class/App/Support/Repeater.php`: repeater request + relation sync
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
- The canonical module format is a Composer package, not a folder embedded in the core package.
- Standard module package naming is `wonder-image/<slug>`.
- Standard module namespace base is `Wonder\\Plugin\\<StudlySlug>\\`.
- For backend forms:
  - `Model::tableSchema()` = SQL structure
  - `Model::dataSchema()` = data treatment / prepare / upload
  - `Resource::formSchema()` = backend inputs
- For non-CRUD backend pages, use `CustomPageSchema`.
- For repeatable rows, use `FormInput::repeater()`, `RepeaterColumn`, and `Wonder\App\Support\Repeater`.

## Architecture notes

- The package still contains legacy runtime code under `app/`, but new work should follow the `class/App/*` architecture.
- `wonder-image.php` bootstraps the package by resolving the consumer project root and loading config/services/middleware.
- Backend/API routes are driven from `app/config/routes` and the `ResourceRouteRegistrar`.
- External modules are enabled by the consumer in `custom/config/modules.php`.
- External module entrypoints should implement `Wonder\\App\\Module\\Contracts\\ModuleInterface`.
- Module routes should live in `config/routes/route.frontend.php`, `route.backend.php`, `route.api.php` inside the module package and be loaded by the core registrars.
- Composer module discovery must remain compatible with both `vendor/composer/installed.php` and `vendor/composer/installed.json`, and must keep a filesystem fallback for `vendor/wonder-image/*/module.json`, because deploy environments may expose different metadata formats.
- `build/src/backend` and `build/table` have been intentionally cleaned out. Do not reintroduce them for new modules.
- `SortableInput` is deprecated. Keep it only for compatibility; do not add new usages.
- Local Herd routing uses a global driver stub:
  - `app/build/stubs/WonderValetDriver.php`
  - generated into `~/Library/Application Support/Herd/config/valet/Drivers/WonderValetDriver.php` by consumer-project Forge commands

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

3. If you changed docs, check links/paths manually.

4. If you changed any of these areas, validate in a consumer project:

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

5. For Herd-specific local routing changes, also validate:

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
