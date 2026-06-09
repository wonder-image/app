---
icon: sitemap
---

# Architettura in 5 minuti

## Cos'è

`wonder-image/app` è una **libreria Composer** (`Wonder\ => class/`). Il runtime
reale gira dentro un **sito** che la installa sotto `vendor/wonder-image/app`.
Tutti i comandi `php forge ...` si eseguono dalla radice del sito.

## Il confine framework / sito

| Cosa | Framework (`wonder-image/app`) | Sito (`new-site`) |
|---|---|---|
| Classi base | `class/App/Model.php`, `class/App/Resource.php`, … | — |
| Model/Resource di default | `class/App/Models`, `class/App/Resources` (`Wonder\App\...`) | `app/Models`, `app/Resources` (`App\...`) |
| Pagine, contenuti, config | — | `custom/` |
| Comandi `forge` | sorgente in `class/Console/Commands/*` | eseguiti dalla radice del sito |

Regola pratica: **non modificare mai `vendor/wonder-image/app/`** da un sito.
La logica di progetto va in `custom/` o in `app/`; le modifiche al core vanno
fatte nel repo del framework.

## Bootstrap (cosa succede all'avvio)

Entrypoint: [`wonder-image.php`](https://github.com/wonder-image/app) nella
radice del pacchetto.

1. **Risolve `ROOT`** (`wonder_resolve_root()`): controlla `$GLOBALS['ROOT']`,
   la working directory, poi i percorsi che contengono `vendor/autoload.php`.
   `ROOT` è la radice del **sito**, non del framework.
2. Carica `vendor/autoload.php` del sito.
3. Ripristina i global legacy con `Wonder\App\LegacyGlobals::scope()`.
4. Carica in ordine:
   - `app/function/function.php`
   - `app/config/config.php` (poi `LegacyGlobals::capture()`)
   - `app/service/service.php`
   - `app/middleware/middleware.php`
   - `app/bootstrap/backend.php` **se** `$BACKEND` è attivo
   - `app/bootstrap/frontend.php` **se** `$FRONTEND` è attivo

Tutto ciò che tocca la risoluzione di `ROOT`, i global iniziali, l'ordine di
caricamento di config/service/middleware è **sensibile al bootstrap**: va
cambiato con cautela.

## Risoluzione ambiente (`.env`)

- `Wonder\App\Credentials::loadEnv()` risolve `.env` dalla `ROOT` del **sito**,
  non dalla cartella del framework dentro `vendor/`.
- Le credenziali DB possono mancare al primo setup: il codice lo tollera finché
  non serve una connessione reale.
- I nomi DB esistono in due varianti storiche (`DB_HOSTNAME`/`DB_HOST`, …):
  `Wonder\App\EnvCompat` li allinea automaticamente. Dettagli in
  [Model e Database](../concetti/risorse/database.md).

## Doppia architettura: `app/` legacy e `class/App/` nuovo

- Sotto `app/` vive ancora il runtime **legacy** (funzioni procedurali, build).
- Sotto `class/App/*` vive l'**architettura nuova** basata su classi
  (Model, Resource, schema, Permission, Module).
- Per il codice nuovo si usa `class/App/*`; il legacy resta come compat layer.

## Scoperta e precedenza (registry)

Il framework scopre Model e Resource via PSR-4 e li registra con una precedenza
ben definita (chi sta più in alto sovrascrive chi sta più in basso):

`ModelRegistry` e `ResourceRegistry` caricano da:

1. core framework (`class/App/Models`, `class/App/Resources`)
2. moduli abilitati
3. sito (`app/Models`, `app/Resources`)
4. sito (`custom/class/Models`, `custom/class/Resources`)
5. liste di risorse dai file di configurazione, quando presenti

{% hint style="warning" %}
Dopo aver aggiunto/spostato un Model o Resource, esegui sempre
`composer dump-autoload` dal progetto che possiede la classe: i registry
scoprono le classi via PSR-4, quindi namespace e cartella devono coincidere.
{% endhint %}

## Routing generato

I file di route di base sono:

- `app/config/routes/route.frontend.php`
- `app/config/routes/route.backend.php`
- `app/config/routes/route.api.php`

`ResourceRouteRegistrar` genera automaticamente le route CRUD backend e API per
ogni Resource registrata. Dettagli in
[Route e API generate](../concetti/risorse/route-e-api.md) e
[Routing](../piattaforma/routing.md).

## Mappa file → area

| Area | File / classe |
|---|---|
| Bootstrap | `wonder-image.php`, `class/App/Credentials.php`, `class/App/LegacyGlobals.php` |
| Model / DB | `class/App/Model.php` |
| Resource | `class/App/Resource.php`, `class/App/ResourceSchema/*` |
| Form | `class/App/ResourceSchema/FormField.php`, `class/App/Support/FormFieldElementFactory.php`, `class/Themes/{Wonder,Bootstrap}/*` |
| Tabelle | `class/App/ResourceSchema/TableColumn.php`, `class/Backend/Support/ResourceTableRenderer.php` |
| Permessi | `class/App/Permission/{Permissions,Area,Permission}.php` |
| Utenti | `class/App/Resources/Support/UserManagementResource.php`, `class/App/Resources/User/*` |
| Moduli | `class/App/Module/{Discovery,Registry,Manifest,ManifestValidator}.php` |
| Console | `class/Console/Forge.php`, `class/Console/Commands/*` |

## File da non toccare / generati

- Mai editare `vendor/`.
- `docs/class/` è output generato da phpDocumentor, non sorgente.
- Non ricreare a mano `app/build/src/backend/*` o `app/build/table/*`.
