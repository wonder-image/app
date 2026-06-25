---
icon: file-code
---

# Manifest module.json

## Cos'è

`module.json` è il **manifest** del modulo: dichiara identità, namespace,
entrypoint, compatibilità, dipendenze e percorsi. Viene letto da
`class/App/Module/Manifest.php` e validato da
`class/App/Module/ManifestValidator.php`.

## Campi obbligatori (verificati)

Il validatore richiede questi campi non vuoti:

| Campo | Regola |
|---|---|
| `name` | presente, non vuoto |
| `slug` | regex `^[a-z0-9]+(?:-[a-z0-9]+)*$` (minuscolo, trattini) |
| `version` | semver `MAJOR.MINOR.PATCH` (con pre-release/build opzionali) |
| `description` | presente, non vuoto |
| `namespace` | regex `^Wonder\Plugin\[A-Z][A-Za-z0-9]*\\$` |
| `entrypoint` | classe autoloadable che implementa `ModuleInterface` |
| `frameworkCompatibility.wonder-app` | **obbligatorio** |
| `frameworkCompatibility.php` | **obbligatorio** |

Inoltre, i **percorsi** dichiarati devono stare **dentro** la radice del modulo
(altrimenti errore "Path ... fuori dal root modulo").

## Esempio minimo valido

```json
{
  "name": "Blog",
  "slug": "blog",
  "version": "1.0.0",
  "description": "Modulo blog per Wonder.",
  "namespace": "Wonder\\Plugin\\Blog\\",
  "entrypoint": "Wonder\\Plugin\\Blog\\Module",
  "frameworkCompatibility": {
    "wonder-app": ">=2.0.0",
    "php": ">=8.2"
  },
  "paths": {
    "models": "src/Models",
    "resources": "src/Resources",
    "routes": {
      "frontend": "routes/route.frontend.php",
      "backend": "routes/route.backend.php",
      "api": "routes/route.api.php"
    },
    "lang": "lang",
    "views": "views"
  },
  "permissions": "config/permissions.php",
  "dependencies": {
    "modules": []
  }
}
```

## Campi letti dal Manifest

`Manifest.php` espone questi reader (i percorsi hanno default sensati):

| Reader | Default / nota |
|---|---|
| `name()`, `slug()`, `version()`, `description()`, `namespace()`, `entrypoint()` | — |
| `frameworkCompatibility()` | `wonder-app` + `php` |
| `dependencies()` / `dependencySlugs()` | da `dependencies.modules` |
| `modelsPath()` | default `src/Models` |
| `resourcesPath()` | default `src/Resources` |
| `handlersPath()`, `viewsPath()`, `assetsPath()`, `langPath()`, `testsPath()` | da `paths.*` |
| `routeFile($area)` | da `paths.routes.<area>` |
| `permissionsFile()` | file dei permessi del modulo |
| `bootFiles()` | file caricati al boot del modulo |
| `priority()`, `source()`, `composerPackage()` | metadati di scoperta |

## Permessi del modulo

Il file indicato come `permissions` può ritornare un array compatibile con
`$PERMITS` oppure usare il `PermissionRegistry`. Viene unito al registro centrale
da `Registry::mergePermissions(...)` (ordine: core → moduli → custom). Vedi
[Builder permessi](../utenti/permessi.md).

## Dipendenze

`dependencies.modules` elenca gli slug dei moduli richiesti; `dependencySlugs()`
li restituisce. Il framework usa questa informazione per risolvere l'ordine dei
moduli.

{% hint style="warning" %}
**Da verificare / non validato.** Alcuni campi che potresti vedere in esempi
storici (es. dettagli `database.*`, oppure controlli su collisioni di
authority/service/route name) **non** sono validati dal `ManifestValidator`
attuale. Considerali "previsti / da verificare", non garantiti. Il riferimento è
sempre il codice di `ManifestValidator.php` e `Manifest.php`.
{% endhint %}

## Supporto legacy

`Discovery` tollera anche pacchetti Composer senza `module.json` completo,
generando un manifest minimo (entrypoint marcato legacy, che salta il controllo
`is_subclass_of ModuleInterface`). Comportamento da verificare caso per caso in
`Discovery.php`.

## Errori comuni

- **`slug` non valido** → usa solo minuscole e trattini.
- **`version` non semver** → formato `1.0.0`.
- **`namespace` non valido** → deve terminare con `\\` ed essere
  `Wonder\Plugin\<Studly>\`.
- **Path fuori dal root** → tutti i percorsi devono stare dentro la cartella del
  modulo.

## Checklist

- [ ] campi obbligatori presenti e validi
- [ ] `frameworkCompatibility.wonder-app` e `.php` impostati
- [ ] entrypoint autoloadable e che implementa `ModuleInterface`
- [ ] percorsi interni alla radice del modulo
- [ ] `php forge validate:module <slug>` senza errori
