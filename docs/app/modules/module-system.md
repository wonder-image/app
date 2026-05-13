# Sistema Moduli

## Obiettivo

Permettere a `wonder-image/app` di caricare moduli esterni senza copiare codice nel consumer.

## Formato canonico

Il formato canonico non e' una sottocartella del core.

Il formato canonico e' un package Composer, per esempio:

```text
vendor/wonder-image/blog/
```

Supporto secondario:

```text
ROOT/modules/blog/
```

Questo supporto locale serve solo per sviluppo o prototipi.

## Bootstrap reale

I moduli abilitati vengono integrati in questi punti:

- `class/App/ModelRegistry.php`
- `class/App/ResourceRegistry.php`
- `app/config/routes/route.frontend.php`
- `app/config/routes/route.backend.php`
- `app/config/routes/route.api.php`
- `app/config/app/permission.php`
- `app/service/lang.php`
- `app/config/config.php`

La discovery Composer del core legge prima `vendor/composer/installed.php` e usa `vendor/composer/installed.json` come fallback e sorgente metadata aggiuntiva. Se i metadata Composer runtime non bastano, il core effettua anche una scansione fisica di `vendor/wonder-image/*/module.json`. Questo evita differenze tra ambienti locali e deploy finali.

Prima della discovery dei `Model` il core pre-inizializza anche un contesto traduzioni minimo. Questo permette ai moduli di usare `__t()` in extension, field definitions e schema dinamici gia' nelle fasi iniziali del bootstrap.

## Stato moduli nel consumer

Il consumer dichiara i moduli in:

```php
custom/config/modules.php
```

Esempio:

```php
<?php

return [
    'blog' => [
        'enabled' => true,
        'config' => [
            'posts_per_page' => 12,
        ],
    ],
    'rsvp' => false,
];
```

Regole:

- se un modulo non e' dichiarato, il core non lo abilita;
- se un modulo e' abilitato ma non viene scoperto, il bootstrap fallisce con errore chiaro;
- se un modulo dipende da un altro modulo non abilitato, il bootstrap fallisce.
- i package legacy `wonder-image/*` con `module.json` restano supportati anche se il deploy espone solo metadata Composer parziali.

## Comandi disponibili

Dal consumer:

```bash
php forge status:modules
php forge validate:module blog
```

## Cosa non fa ancora il core

Questa prima implementazione non include ancora:

- install/uninstall lifecycle tracciato in DB
- runner migrazioni modulo con tracking file/hash
- publish asset modulo
- generatori `make:module`
- moduli esempio inclusi

Questi punti restano previsti ma non ancora chiusi.
