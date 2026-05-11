# Contratto Modulo

## Contratto minimo

Ogni modulo deve avere:

- `module.json`
- entrypoint PHP che implementa `Wonder\App\Module\Contracts\ModuleInterface`

File raccomandati per sviluppo e distribuzione:

- `composer.json`
- `README.md`
- `CHANGELOG.md`

## Namespace standard

Il namespace base standard e':

```php
Wonder\Plugin\
```

Esempio:

```php
Wonder\Plugin\Blog\Blog
Wonder\Plugin\Blog\Models\Post
Wonder\Plugin\Blog\Resources\PostResource
```

## Struttura consigliata

```text
wonder-image/blog/
  composer.json
  module.json
  src/
    Blog.php
    Models/
    Resources/
    Services/
    Support/
    Hooks/
  config/
    module.php
    permissions.php
    routes/
      route.frontend.php
      route.backend.php
      route.api.php
  build/
    update/
    row/
    cli/
    install.php
    uninstall.php
  handlers/
    frontend/
    backend/
    api/
  views/
    frontend/
    backend/
    components/
  resources/
    assets/
  lang/
  tests/
```

## Entrypoint PHP

L'entrypoint del modulo deve implementare:

```php
<?php

namespace Wonder\Plugin\Blog;

use Wonder\App\Module\Contracts\ModuleInterface;

final class Blog implements ModuleInterface
{
    public static function root(): string {}
    public static function manifestPath(): string {}
    public static function handlerPath(string $path): string {}
    public static function viewPath(string $path): string {}
    public static function langPath(): string {}
    public static function assetPath(string $path = ''): string {}
}
```

## Convenzioni operative

- i `Model` del modulo stanno in `src/Models`
- le `Resource` del modulo stanno in `src/Resources`
- le route custom stanno in `config/routes/route.*.php`
- gli handler custom stanno in `handlers/`
- le view del modulo stanno in `views/`
- le traduzioni stanno in `lang/`
- gli asset statici stanno in `resources/assets/`

## Divieti

Non fare:

- copiare manualmente file del modulo in `custom/...` come integrazione principale
- usare `build/table` per nuovi moduli
- introdurre namespace fuori dallo standard `Wonder\Plugin\`
- hardcodare path consumer dentro il package modulo
