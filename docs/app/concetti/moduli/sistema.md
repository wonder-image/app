---
icon: gears
---

# Sistema moduli

## Scoperta (Discovery)

`class/App/Module/Discovery.php` raccoglie i moduli candidati da **quattro
sorgenti**, ciascuna con una **priorità** (numero più alto = vince in caso di
slug duplicato):

| Sorgente | Dove cerca | Priorità |
|---|---|---|
| **bundled** | `<packageRoot>/modules/*` (moduli inclusi nel framework) | 10 |
| **vendor** | `<ROOT>/vendor/wonder-image/*` (scan filesystem) | 18 |
| **composer** | `vendor/composer/installed.php` e `installed.json` | (da metadati) |
| **local** | `<ROOT>/modules/*` (moduli in sviluppo nel sito) | 30 |

Per ogni cartella candidata, Discovery cerca un `module.json` e crea un
`Manifest`. La sorgente **composer** legge i metadati dei pacchetti installati
(con fallback tra `installed.php` e `installed.json`).

## Validazione (ManifestValidator)

`class/App/Module/ManifestValidator.php` valida ogni manifest. Vedi
[Manifest module.json](manifest.md) per i campi obbligatori e le regole reali.
Un manifest non valido fa scartare il modulo (con errori espliciti).

## Registro (Registry)

`class/App/Module/Registry.php` lavora sui moduli **abilitati**
(`Registry::enabled()` legge lo stato di abilitazione). Espone:

| Metodo | Cosa restituisce |
|---|---|
| `enabled()` | i manifest dei moduli abilitati |
| `modelDirectories()` | cartelle dei Model dei moduli |
| `resourceDirectories()` | cartelle delle Resource dei moduli |
| `routeFiles($area)` | file di route per area (`frontend`/`backend`/`api`) |
| `languagePaths()` | percorsi delle traduzioni |
| `mergePermissions($permits)` | unisce i permessi dei moduli al registro |

## Precedenza Model / Resource

I Model e le Resource dei moduli si inseriscono nella catena di scoperta tra il
core e il sito:

```
core framework  →  MODULI abilitati  →  sito (app/)  →  sito (custom/)
```

Una definizione del sito può sovrascrivere quella di un modulo; un modulo può
sovrascrivere il core. Dettagli in
[Architettura](../../introduzione/architettura.md).

## Permessi dei moduli

Nel registro permessi (`app/config/app/permission.php`) il merge dei moduli
avviene **dopo** il core e **prima** del custom del sito:

```php
Permissions::replace(\Wonder\App\Module\Registry::mergePermissions(Permissions::instance()));
```

Quindi: **core → moduli → custom**. Vedi [Builder permessi](../utenti/permessi.md).

## Route dei moduli

`Registry::routeFiles($area)` fornisce i file di route per area, caricati
insieme alle route di base del framework
(`app/config/routes/route.<area>.php`).

## Publish view dei moduli

`php forge publish:module <slug>` copia tutte le view dichiarate dal modulo in
`paths.views` verso il sito, sotto:

```text
custom/modules/<slug>/view/
```

Esempio:

```bash
php forge publish:module rsvp
```

Se il modulo `rsvp` dichiara `paths.views = "view"`, il comando copia, ad
esempio:

```text
vendor/wonder-image/rsvp/view/components/form.php
custom/modules/rsvp/view/components/form.php
```

Il comando non sovrascrive file già pubblicati. Usa `--force` per sovrascrivere,
`--dry-run` per vedere cosa verrebbe copiato, `--list` per elencare i file
publishabili, oppure un path relativo per pubblicare solo una parte:

```bash
php forge publish:module rsvp components/form.php
php forge publish:module rsvp --components
php forge publish:module rsvp --layouts
php forge publish:module rsvp --pages
```

Il publish è solo una copia nel sito. Perché l'override venga usato a runtime,
il modulo deve risolvere le proprie view passando prima dal path
`custom/modules/<slug>/view/...` e poi dal fallback del package.

## Errori comuni

- **Modulo non caricato** → non è abilitato in `custom/config/modules.php`, o il
  manifest non passa la validazione.
- **Slug duplicato** → vince la sorgente con priorità più alta (local > vendor >
  bundled); attenzione a moduli con lo stesso slug in più posti.
- **Permessi del modulo non visibili** → il merge avviene nel registro permessi:
  verifica che il modulo dichiari `permissions` nel manifest.
- **View pubblicata ma non usata** → verifica che il modulo risolva le view con
  fallback su `custom/modules/<slug>/view/...`.

## Checklist

- [ ] `module.json` valido (vedi [manifest](manifest.md))
- [ ] modulo abilitato in `custom/config/modules.php`
- [ ] `php forge status:modules` lo mostra abilitato
- [ ] `php forge validate:module <slug>` senza errori
- [ ] `php forge publish:module <slug> --list` mostra le view attese, se il modulo espone view
