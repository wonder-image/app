---
icon: file-signature
---

# Contratto modulo (ModuleInterface)

## Cos'è

Ogni modulo espone un **entrypoint**: una classe che implementa
`Wonder\App\Module\Contracts\ModuleInterface`
(`class/App/Module/Contracts/ModuleInterface.php`). È il contratto minimo tra il
modulo e il framework.

## L'interfaccia (verificata)

```php
namespace Wonder\App\Module\Contracts;

interface ModuleInterface
{
    public static function root(): string;

    public static function manifestPath(): string;

    public static function handlerPath(string $path): string;

    public static function viewPath(string $path): string;

    public static function langPath(): string;

    public static function assetPath(string $path = ''): string;
}
```

| Metodo | Cosa deve ritornare |
|---|---|
| `root()` | percorso radice del modulo |
| `manifestPath()` | percorso del `module.json` |
| `handlerPath($path)` | percorso di un handler interno al modulo |
| `viewPath($path)` | percorso di una view del modulo |
| `langPath()` | percorso delle traduzioni |
| `assetPath($path = '')` | percorso degli asset |

## Namespace standard

Il namespace base di un modulo è `Wonder\Plugin\<StudlySlug>\` (validato da una
regex nel `ManifestValidator`: `^Wonder\Plugin\[A-Z][A-Za-z0-9]*\\$`). Esempio:
slug `blog` → namespace `Wonder\Plugin\Blog\`.

## Struttura suggerita

```
modules/blog/                (o un pacchetto Composer wonder-image/blog)
├── module.json              # manifest
├── src/
│   ├── Module.php           # entrypoint (implements ModuleInterface)
│   ├── Models/              # Model del modulo
│   └── Resources/           # Resource del modulo
├── routes/
│   ├── route.frontend.php
│   ├── route.backend.php
│   └── route.api.php
├── lang/
├── views/
└── config/
    └── permissions.php      # permessi del modulo
```

I percorsi effettivi sono letti dal manifest (vedi [Manifest](manifest.md)); le
cartelle di default per Model/Resource sono `src/Models` e `src/Resources`.

## Esempio di entrypoint

```php
<?php

namespace Wonder\Plugin\Blog;

use Wonder\App\Module\Contracts\ModuleInterface;

final class Module implements ModuleInterface
{
    public static function root(): string
    {
        return dirname(__DIR__);
    }

    public static function manifestPath(): string
    {
        return self::root().'/module.json';
    }

    public static function handlerPath(string $path): string
    {
        return self::root().'/src/handlers/'.ltrim($path, '/');
    }

    public static function viewPath(string $path): string
    {
        return self::root().'/views/'.ltrim($path, '/');
    }

    public static function langPath(): string
    {
        return self::root().'/lang';
    }

    public static function assetPath(string $path = ''): string
    {
        return self::root().'/assets/'.ltrim($path, '/');
    }
}
```

## Validazione dell'entrypoint

`ManifestValidator` verifica che la classe entrypoint:

- sia **autoloadable** (`class_exists`);
- **implementi** `ModuleInterface` (a meno che il modulo non sia marcato
  legacy).

## Righe precaricate (`ModuleDefaults`)

```php
namespace Wonder\Plugin\Gestionale\Database;

use Wonder\App\Module\Contracts\ModuleDefaults;
use Wonder\App\Support\DefaultRows;
use Wonder\Plugin\Gestionale\Models\System\Settings;
use Wonder\Plugin\Gestionale\Models\Tax\Tax;

final class Defaults implements ModuleDefaults
{
    public static function seed(DefaultRows $rows): void
    {
        $rows->ensure(Tax::class, 'code', [
            ['code' => 'vat-22', 'name' => '22% - Aliquota ordinaria', 'rate' => '22.00'],
        ]);

        $rows->ensureSingleton(Settings::class, ['return_days' => 14]);
    }
}
```

- `ensure()` inserisce solo le righe il cui valore chiave non esiste, contando anche le righe cancellate, e non modifica mai le righe esistenti;
- `ensureSingleton()` crea la riga `id = 1` se manca;
- i moduli vengono eseguiti in ordine di dipendenza (`dependencies.modules`, `ModuleDependencySorter`);
- dichiarazione nel manifest: [`database.defaults`](manifest.md).

## Errori comuni

- **`Entrypoint non autoloadabile`** → namespace/PSR-4 non allineato; manca
  `composer dump-autoload`.
- **`... deve implementare ModuleInterface`** → la classe non implementa il
  contratto.
- **Namespace non valido** → non rispetta `Wonder\Plugin\<Studly>\`.

## Checklist

- [ ] entrypoint implementa `ModuleInterface`
- [ ] namespace `Wonder\Plugin\<Studly>\` corretto
- [ ] tutti i percorsi ritornati esistono
- [ ] `composer dump-autoload` eseguito
