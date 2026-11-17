<?php

/**
 * Il catalogo dei componenti senza un sito.
 *
 *   npm install            # una volta: porta @wonder-image/lib in node_modules (pacchetto privato su GitHub Packages: serve `.npmrc` con
 *                          @wonder-image:registry=https://npm.pkg.github.com e `npm login --scope=@wonder-image`)
 *   php bin/docs.php       # http://127.0.0.1:8090/
 *   php bin/docs.php 0.0.0.0:8080
 *
 * Da riga di comando avvia `php -S` con questo stesso file come router; sotto
 * il server integrato il file serve pagine e asset (vedi Wonder\Docs\Server).
 * Niente shebang: sotto `php -S` finirebbe nell'output.
 */

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

if (PHP_SAPI === 'cli-server') {
    \Wonder\Docs\Server::handle();

    return;
}

exit(\Wonder\Docs\Server::serve($argv ?? []));
