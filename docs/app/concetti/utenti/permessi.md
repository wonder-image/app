---
icon: shield-halved
---

# Builder permessi (Area / Permission)

## Cos'è

I permessi si definiscono con un **builder** a tre classi
(`class/App/Permission/`):

- **`Permissions`** — il registro: aggiunge aree e permessi, fa il merge,
  esporta.
- **`Area`** — un ambito di accesso (`frontend`, `backend`, `api`) con link e
  funzioni associate.
- **`Permission`** — una singola **authority** (es. `admin`) con metadati.

## A cosa serve

Dichiarare quali ruoli esistono e quali link/funzioni hanno, così che le Resource
possano referenziarli in `permissionSchema()` e il backend possa assegnarli agli
utenti.

## Dove si trova nel codice

- Classi: `class/App/Permission/{Permissions,Area,Permission}.php`
- Registro base: `app/config/app/permission.php`
- Estensione del sito: `custom/config/permissions.php`

## Ordine di costruzione (verificato)

`app/config/app/permission.php` esegue, in quest'ordine:

```php
use Wonder\App\Permission\{Permissions, Permission, Area};

// 1. aree + permessi base
Permissions::reset()
    ->addArea(Area::make('backend'))
    ->addArea(Area::make('frontend'))
    ->addArea(Area::make('api'))
    ->addPermission(
        Permission::make('admin', 'backend')
            ->name('Admin')
            ->icon("<i class='bi bi-arrow-through-heart'></i>")
            ->bg('bg-primary')->tx('text-light')->color('primary')
            ->creator(['admin'])
    );

// 2. link/funzioni a livello di area
Permissions::area('backend')
    ->route('home', 'backend.home')
    ->route('login', 'backend.account.login');

// 3. merge dei permessi dei MODULI
Permissions::replace(\Wonder\App\Module\Registry::mergePermissions(Permissions::instance()));

// 4. estensione del SITO (se presente)
$customPermissionsFile = $ROOT."/custom/config/permissions.php";
if (is_file($customPermissionsFile)) {
    require $customPermissionsFile;
}

// 5. struttura finale esportata
$PERMITS = Permissions::toArray();
```

La precedenza è quindi **core → moduli → custom**: il sito può estendere o
sovrascrivere quanto definito da core e moduli.

## API del builder

### `Permissions` (registro)

| Metodo | Cosa fa |
|---|---|
| `reset($schema = [])` | azzera e ricomincia il registro |
| `replace($schema)` | sostituisce il registro corrente |
| `instance()` | il registro corrente |
| `addArea(Area)` | aggiunge un'area |
| `area($area, $definition = null)` | ottiene/definisce un'area |
| `addPermission(Permission)` | aggiunge un permesso |
| `permission($area, $key, $definition = null)` | ottiene/definisce un permesso |
| `merge($definitions)` / `mergeFile($file)` | merge da array/callable/file |
| `toArray()` | struttura finale → `$PERMITS` |

### `Permission` (authority)

`Permission::make($key, $area)` poi: `->name()`, `->icon()`, `->bg()`, `->tx()`,
`->color()`, `->creator([...])` (chi può creare/assegnare questa authority),
`->route($key, $routeName)`, `->link($key, $value)`, `->function($key, $callback)`,
`->verification($key, $config)`, `->extra([...])`.

### `Area` (ambito)

`Area::make($key)` poi: `->route($key, $routeName)`, `->link($key, $value)`,
`->function($key, $callback)`, `->verification($key, $config)`, `->extra([...])`.

## Come aggiungere una nuova authority

Esempio: un ruolo backend `segretaria`.

1. **Definiscila nel builder**. Se è di progetto, in `custom/config/permissions.php`
   (eseguito dopo core e moduli):

   ```php
   use Wonder\App\Permission\{Permissions, Permission};

   Permissions::addPermission(
       Permission::make('segretaria', 'backend')
           ->name('Segretaria')
           ->icon("<i class='bi bi-person-badge'></i>")
           ->bg('bg-secondary')->tx('text-light')->color('secondary')
           ->creator(['admin'])
   );
   ```

2. **Usala nelle Resource** che deve gestire:

   ```php
   public static function permissionSchema(): PermissionSchema
   {
       return PermissionSchema::for(static::class)
           ->backendCrud(['admin', 'administrator', 'segretaria']);
   }
   ```

3. **Limita la navigazione** se serve:
   `navigationSchema()->authority(['admin', 'segretaria'])`.

4. **Verifica** che la gestione utenti possa assegnare la nuova authority (il
   `->creator([...])` controlla chi può crearla).

## Runtime

A runtime le authority si leggono dalla struttura `$PERMITS` tramite gli helper
`permissions()`, `permissionsBackend()`, `permissionsApi()`.

## Errori comuni

- **Authority ignorata in `permissionSchema()`** → la chiave non esiste nel
  builder. Va prima definita in `app/config/app/permission.php` o
  `custom/config/permissions.php`.
- **Aggiungere ruoli con array ad-hoc altrove** → non funziona: l'unica fonte è
  il builder.
- **Custom che non sovrascrive** → assicurati che `custom/config/permissions.php`
  esista e venga eseguito dopo il merge dei moduli (lo è per costruzione).

## Checklist

- [ ] authority definita nel builder (core o custom)
- [ ] referenziata in `permissionSchema()` delle Resource interessate
- [ ] gate di navigazione aggiornato se serve
- [ ] gestione utenti in grado di assegnarla (`creator`)
