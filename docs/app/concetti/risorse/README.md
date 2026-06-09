---
icon: layer-group
---

# Risorse e Model

## Cos'è

Due classi lavorano in coppia:

- **Model** (`class/App/Model.php`) — rappresenta una **tabella**: struttura SQL,
  validazione, persistenza, query.
- **Resource** (`class/App/Resource.php`) — espone quel Model come **CRUD**
  backend e API: form, lista, permessi, navigazione.

Un Model può esistere senza Resource (solo dati). Una Resource richiede sempre
un Model (`public static string $model = ...`).

## A cosa serve

Dichiarare un Model + una Resource genera automaticamente:

- la tabella SQL (via `php forge update`)
- le pagine backend (lista, crea, modifica, elimina)
- le route API REST
- i gate di permesso per ogni azione
- la voce di menu nel backend

Senza scrivere route, controller o query a mano.

## Dove si trova nel codice

| Elemento | File |
|---|---|
| Base Model | `class/App/Model.php` |
| Base Resource | `class/App/Resource.php` |
| Schema (form, tabella, permessi, ...) | `class/App/ResourceSchema/*.php` |
| Pagine non-CRUD | `class/App/PageSchema/*.php` |
| Generazione route | `ResourceRouteRegistrar` |
| Esempi reali | `class/App/Resources/*` (es. `Css/CssAlertResource.php`) |

Dove vanno le tue classi:

| Contesto | Model | Resource | Namespace |
|---|---|---|---|
| Framework | `class/App/Models` | `class/App/Resources` | `Wonder\App\...` |
| Sito | `app/Models` | `app/Resources` | `App\...` |
| Modulo | PSR-4 del modulo | PSR-4 del modulo | `Wonder\Plugin\<Slug>\...` |

## Le pagine di questa sezione

- [Model e Database](database.md) — `dataSchema()`, `tableSchema()`, query,
  soft-delete, migrazioni.
- [Definire una Resource](resource.md) — tutti gli schema della Resource e gli
  hook del ciclo di vita.
- [Pagine non-CRUD (CustomPageSchema)](custom-page-schema.md) — pagine backend
  che non sono CRUD.
- [Resource Singleton](singleton.md) — risorse a riga unica (es. configurazione).
- [Route e API generate](route-e-api.md) — cosa emette `ResourceRouteRegistrar`.

## Regola d'oro

Dopo aver aggiunto/spostato un Model o Resource esegui sempre
`composer dump-autoload` dal progetto che possiede la classe. I registry
scoprono le classi via PSR-4: namespace e cartella devono coincidere.
