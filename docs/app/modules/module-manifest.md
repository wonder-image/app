# Manifest Modulo

## File

Ogni modulo deve esporre:

```text
module.json
```

## Campi minimi

- `name`
- `slug`
- `version`
- `description`
- `namespace`
- `entrypoint`
- `author`
- `frameworkCompatibility`
- `dependencies`
- `paths`

## Esempio

```json
{
  "name": "Wonder Blog",
  "slug": "blog",
  "version": "1.0.0",
  "description": "Modulo blog con frontend, backend e API.",
  "namespace": "Wonder\\Plugin\\Blog\\",
  "entrypoint": "Wonder\\Plugin\\Blog\\Blog",
  "author": {
    "name": "Wonder Image",
    "email": "info@wonderimage.it"
  },
  "frameworkCompatibility": {
    "wonder-app": "^2.1",
    "php": "^8.2"
  },
  "dependencies": {
    "modules": []
  },
  "paths": {
    "src": "src",
    "handlers": "handlers",
    "views": "views",
    "assets": "resources/assets",
    "lang": "lang",
    "tests": "tests"
  },
  "routes": {
    "frontend": "config/routes/route.frontend.php",
    "backend": "config/routes/route.backend.php",
    "api": "config/routes/route.api.php"
  },
  "permissions": {
    "definitions": "config/permissions.php"
  },
  "database": {
    "models": "src/Models",
    "update": "build/update",
    "row": "build/row",
    "install": "build/install.php",
    "uninstall": "build/uninstall.php"
  }
}
```

## Validazioni implementate

Il core valida almeno:

- JSON valido
- slug kebab-case lowercase
- versione semver
- namespace sotto `Wonder\\Plugin\\`
- entrypoint autoloadabile
- entrypoint che implementa `ModuleInterface`
- presenza di `frameworkCompatibility.wonder-app`
- presenza di `frameworkCompatibility.php`
- path dichiarati interni al root modulo
- esistenza dei file route dichiarati

## Note

- i file opzionali possono mancare se non dichiarati
- `config/permissions.php` puo' restituire un array compatibile con `$PERMITS` oppure una `Wonder\App\Permission\PermissionRegistry`
- `composer.json` e' raccomandato per sviluppo e distribuzione del package, ma non e' richiesto a runtime dal bootstrap del modulo
- un modulo scoperto ma non abilitato non viene caricato nel runtime
- il manifest e' il contratto tecnico, non il posto dove mettere configurazione runtime del consumer
