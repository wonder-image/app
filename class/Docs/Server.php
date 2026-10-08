<?php

namespace Wonder\Docs;

use InvalidArgumentException;
use Throwable;
use Wonder\App\Dependencies;
use Wonder\App\LegacyGlobals;
use Wonder\App\LibVersion;
use Wonder\App\Theme;
use Wonder\App\TranslationBootstrap;
use Wonder\App\Version;
use Wonder\View\View;

/**
 * Il catalogo dei componenti senza un sito: `php bin/docs.php` avvia il
 * server PHP integrato con questo router, che serve le pagine del catalogo e
 * gli asset di `wonder-image/lib` da `node_modules/wonder-image` (installata
 * con `npm install` nella radice del pacchetto).
 *
 * Niente database, sessione o `.env`: le costanti che i renderer si aspettano
 * (`APP_URL`, `ROOT`, ...) vengono definite qui dalla richiesta, e il tema
 * Wonder usa i token CSS di default del framework.
 */
final class Server
{
    public const DEFAULT_ADDRESS = '127.0.0.1:8090';

    private const ASSET_PREFIX = '/assets/lib/wonder-image/';

    private const PACKAGE_ASSET_PREFIX = '/vendor/wonder-image/app/resources/assets/';

    private const MIME = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'mjs' => 'application/javascript; charset=UTF-8',
        'map' => 'application/json; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'html' => 'text/html; charset=UTF-8',
        'txt' => 'text/plain; charset=UTF-8',
    ];

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function libPath(): string
    {
        return self::root().'/node_modules/'.LibVersion::PACKAGE;
    }

    /**
     * Da riga di comando: avvia `php -S` su questo router.
     *
     * @param string[] $argv
     */
    public static function serve(array $argv): int
    {
        $address = trim((string) ($argv[1] ?? self::DEFAULT_ADDRESS));

        if (in_array($address, ['-h', '--help', 'help'], true)) {
            fwrite(STDOUT, "Uso: php bin/docs.php [host:porta]\n\nAvvia il catalogo dei componenti su http://".self::DEFAULT_ADDRESS."/ (o sull'indirizzo dato).\nServe prima `npm install` nella radice del pacchetto, per gli asset di wonder-image/lib.\n");

            return 0;
        }

        if (!preg_match('/^[\w.\-]+:\d{2,5}$/', $address)) {
            fwrite(STDERR, "Indirizzo non valido: {$address}. Usa host:porta, per esempio ".self::DEFAULT_ADDRESS.".\n");

            return 1;
        }

        if (!is_dir(self::libPath().'/dist')) {
            fwrite(STDERR, "Manca node_modules/".LibVersion::PACKAGE.": esegui `npm install` nella radice del pacchetto, poi rilancia.\n");

            return 1;
        }

        $installed = LibVersion::installed(self::root());
        $minimum = LibVersion::minimum();

        if ($installed !== null && $minimum !== null && !LibVersion::satisfies($installed, $minimum)) {
            fwrite(STDERR, "wonder-image/lib {$installed} è più vecchia del minimo {$minimum}: esegui `npm install ".LibVersion::PACKAGE."@^{$minimum}`.\n");
        }

        fwrite(STDOUT, "Catalogo dei componenti su http://{$address}/ (Ctrl+C per fermare)\n");

        $command = escapeshellarg(PHP_BINARY).' -S '.escapeshellarg($address).' '.escapeshellarg(self::root().'/bin/docs.php');
        passthru($command, $status);

        return (int) $status;
    }

    /** Sotto `php -S`: serve asset e pagine. */
    public static function handle(): void
    {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');

        if (str_starts_with($path, self::ASSET_PREFIX)) {
            self::serveAsset(self::libPath(), substr($path, strlen(self::ASSET_PREFIX)));

            return;
        }

        // Gli asset del pacchetto (immagini d'esempio del catalogo), allo
        // stesso URL che hanno in un sito.
        if (str_starts_with($path, self::PACKAGE_ASSET_PREFIX)) {
            self::serveAsset(self::root().'/resources/assets', substr($path, strlen(self::PACKAGE_ASSET_PREFIX)));

            return;
        }

        try {
            self::bootstrap();
        } catch (Throwable $exception) {
            self::fail(500, 'Impossibile avviare il catalogo: '.$exception->getMessage());

            return;
        }

        if (!is_dir(self::libPath().'/dist')) {
            self::fail(500, 'Manca node_modules/'.LibVersion::PACKAGE.': esegui `npm install` nella radice del pacchetto.');

            return;
        }

        $urls = new Urls('');

        try {
            if ($path === '/') {
                self::page(CatalogPage::index($urls), 'Catalogo dei componenti');

                return;
            }

            if ($path === '/preview/' || $path === '/preview') {
                self::preview();

                return;
            }

            if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)$#', $path, $match)) {
                header('Location: /'.$match[1].'/', true, 302);

                return;
            }

            if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)/$#', $path, $match)) {
                $data = CatalogPage::component($match[1], $urls);

                if ($data === null) {
                    self::fail(404, 'Componente non trovato: '.$match[1]);

                    return;
                }

                self::page($data, $data['doc']->getTitle().' · Catalogo dei componenti');

                return;
            }
        } catch (Throwable $exception) {
            self::fail(500, $exception::class.': '.$exception->getMessage()."\n".$exception->getTraceAsString());

            return;
        }

        self::fail(404, 'Pagina non trovata.');
    }

    private static function bootstrap(): void
    {
        $root = self::root();
        $rootApp = $root.'/app';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? self::DEFAULT_ADDRESS);

        if (!defined('APP_URL')) {
            define('APP_URL', $scheme.'://'.$host);
        }

        if (!defined('ROOT')) {
            define('ROOT', $root);
        }

        if (!defined('APP_VERSION')) {
            define('APP_VERSION', Version::get());
        }

        if (!defined('ASSETS_VERSION')) {
            define('ASSETS_VERSION', 'dev');
        }

        // Le stesse costanti di app/config/app/default.php per le immagini responsive.
        if (!defined('RESPONSIVE_IMAGE_SIZES')) {
            define('RESPONSIVE_IMAGE_SIZES', [240, 480, 620, 960, 1200, 1440, 1920, 2400]);
        }

        if (!defined('RESPONSIVE_IMAGE_WEBP')) {
            define('RESPONSIVE_IMAGE_WEBP', true);
        }

        $GLOBALS['ROOT'] = $root;
        $GLOBALS['ROOT_APP'] = $rootApp;
        // Un utente amministratore finto: senza sessione, i componenti che
        // chiedono un permesso (QuickCreateButton) si rendono come nel backend.
        LegacyGlobals::share([
            'ROOT' => $root,
            'ROOT_APP' => $rootApp,
            'USER' => (object) ['authority' => ['admin']],
        ]);

        // Le funzioni globali che i renderer usano (e(), __t(), sanitize(), ...).
        $ROOT = $root;
        require_once $rootApp.'/function/function.php';

        TranslationBootstrap::preload($rootApp, $root);
        Theme::set('bootstrap');
    }

    /** @param array<string, mixed> $data */
    private static function page(array $data, string $title): void
    {
        $rootApp = self::root().'/app';

        // Le pagine del catalogo sono pagine Bootstrap: gli asset dell'area backend.
        Dependencies::reset();
        require $rootApp.'/bootstrap/backend.php';

        header('Content-Type: text/html; charset=UTF-8');

        View::layout('docs.base', [
            'TITLE' => $title,
            'VERSION' => Version::label(),
            'urls' => $data['urls'],
        ]);

        View::make($rootApp.'/view/pages/docs/catalog.php', $data)->render();

        View::end();
    }

    private static function preview(): void
    {
        $rootApp = self::root().'/app';

        try {
            $data = PreviewPage::build($_GET, $rootApp, true);
        } catch (InvalidArgumentException $exception) {
            self::fail(404, $exception->getMessage());

            return;
        }

        $data['paths'] = ['site' => APP_URL, 'app' => APP_URL.'/app', 'api' => APP_URL.'/api'];
        $data['token'] = '';
        $data['lang'] = 'it';

        header('Content-Type: text/html; charset=UTF-8');

        View::make($rootApp.'/view/pages/docs/preview.php', $data)->render();
    }

    private static function serveAsset(string $directory, string $relative): void
    {
        $relative = str_replace('\\', '/', rawurldecode($relative));

        if ($relative === '' || str_contains($relative, "\0") || in_array('..', explode('/', $relative), true)) {
            self::fail(404, 'Asset non trovato.');

            return;
        }

        $file = rtrim($directory, '/').'/'.ltrim($relative, '/');
        $real = realpath($file);
        $base = realpath($directory);

        if ($real === false || $base === false || !str_starts_with($real, $base.DIRECTORY_SEPARATOR) || !is_file($real)) {
            self::fail(404, 'Asset non trovato: '.$relative);

            return;
        }

        $extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));

        header('Content-Type: '.(self::MIME[$extension] ?? 'application/octet-stream'));
        header('Content-Length: '.(string) filesize($real));
        header('Cache-Control: no-cache');
        readfile($real);
    }

    private static function fail(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
    }
}
