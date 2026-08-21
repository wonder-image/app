---
name: run-app
description: Build, run, test, and drive the wonder-image/app framework package. Use when asked to run this repo, run its tests, smoke-test it, drive the forge console, or verify a change to the framework core (Sql, Backend Table, Model, Route, Elements) works.
---

`wonder-image/app` is the **Wonder framework core** — a Composer *library*, not a
site. It has no `index.php`, no `.env`, and no runnable `forge` binary; those live
in the sites that install it under `vendor/`. So there is nothing to serve here and
`php forge start` will not boot it (see Gotchas). What you drive instead is the
internal PHP surface — the classes under `class/` and the `tests/` suite, which is
exactly what recent PRs touch (SQL-injection hardening, Backend Table SSP, Model,
Route). The handle is **`.claude/skills/run-app/driver.php`** — a plain PHP harness
that runs the tests, boots the forge console, and directly invokes internal classes.

All paths below are relative to the repo root (`packages/app/`).

## Prerequisites

PHP ≥ 8.2 (verified on **8.5.5**) and Composer. On this macOS machine both were
already present via Homebrew / Herd:

```bash
php -v
composer --version
```

On a clean Ubuntu box the equivalent is `sudo apt-get install -y php-cli composer`.
The driver's `test` and `smoke` paths need no database or extra extensions — all 41
test files pass on the stock CLI PHP.

## Setup

```bash
composer install
```

Confirm the autoloader exists (the driver refuses to run without it):

```bash
ls vendor/autoload.php
```

## Run (agent path)

Everything goes through `driver.php`. Three subcommands:

**Run the whole test suite** (each `tests/**` file is a standalone script run in its
own PHP process — they define top-level constants and cannot share one):

```bash
php .claude/skills/run-app/driver.php test
```

→ ends with `41 file, 41 ok, 0 falliti`, exit `0`. Run one file instead:

```bash
php .claude/skills/run-app/driver.php test tests/Security/SqlQueryHardeningTest.php
```

**Smoke-test the framework core** — directly invokes the `Wonder\Sql\Query`
hardening helpers the last several commits added/patched (pure static methods, no
DB). Proves autoload + the security-critical surface:

```bash
php .claude/skills/run-app/driver.php smoke
```

→ six `✓` lines then `smoke: OK`, exit `0`.

**Drive the forge console** — the framework ships `Wonder\Console\Forge` but no
`forge` executable; the driver stands in for it, so every `php forge …` subcommand
is reachable from this repo:

```bash
php .claude/skills/run-app/driver.php forge list
php .claude/skills/run-app/driver.php forge status:modules
```

`status:modules` prints `{"success":true,"count":0,"modules":[]}` (no modules in the
bare package) and exits `0`.

## Direct invocation (most PRs need only this)

A PR that touches one internal function doesn't need the console at all — autoload
and call it:

```bash
php -r 'require "vendor/autoload.php";
  var_dump(\Wonder\Sql\Query::sanitizeLimit("1; DROP TABLE users-- "));'
```

→ `string(0) ""` (the injection is stripped). Swap in whatever class your change
touches; `class/` is PSR-4 under the `Wonder\` namespace.

## Run (human path)

There isn't one in this repo. `php forge start` (and the site web stack) only works
from a **site** that has installed this package under `vendor/` and has an
`index.php`/`handler/index.php` docroot and a `.env`. To exercise the framework as a
running website, run `forge start` from such a site (e.g. the new-site scaffold),
not from here.

## Gotchas

- **`.claude/` is gitignored** in this repo (`git ls-files .claude` → empty), like
  `tests/` and `AGENTS.md`. This skill and its driver live on disk and are
  discovered fine, but `git add` will ignore them — use `git add -f` if you want
  them tracked (same convention the repo already uses for `tests/`).
- **`php forge start` fails here on purpose.** In this package it prints
  `❌ Nessun front controller trovato nella docroot: manca sia index.php sia
  handler/index.php.` and exits `1` — there's no docroot to serve. That's expected;
  the package is a library.
- **Test files can't be batched into one PHP process.** Each defines top-level
  constants (`ROOT`, `APP_URL`, …) and calls `exit()`. The driver runs each in its
  own child process for that reason — don't try to `require` them together.
- **The security tests are self-contained**; the others `require tests/harness.php`.
  Both kinds are directly runnable as `php tests/<path>` — the driver just wraps the
  discovery and aggregation.
- **PHP 8.5 works** even though `composer.json` pins `config.platform.php` to
  `8.2.30` and requires `^8.2`. The platform pin only constrains what Composer
  *resolves*; the CLI runtime version is independent.

## Troubleshooting

- `vendor/autoload.php missing … run composer install first` — the driver couldn't
  find the autoloader. Run `composer install` at the repo root.
- `PHP Parse error: … unexpected token "*"` while editing the driver — a `**/`
  sequence inside a `/* … */` block comment closes the comment early. Keep glob
  patterns like `tests/**` out of block comments (they're fine in string literals).
- A test fails with `Class "Wonder\…" not found` — the autoloader is stale after
  adding a class; run `composer dump-autoload`.
