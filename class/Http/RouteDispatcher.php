<?php

namespace Wonder\Http;

use Throwable;
use Wonder\App\LegacyGlobals;
use Wonder\App\Logger;
use Wonder\Localization\LanguageContext;
use Wonder\Localization\UrlTranslator;

class RouteDispatcher
{
    private string $area = 'frontend';
    private ?string $runtimeRoot = null;
    private ?array $currentRoute = null;

    public function __construct(
        private readonly string $root,
    ) {}

    public function handleRequest(): never
    {
        $this->ensureRequestIdentifiers();

        try {
            $runtimeRoot = $this->runtimeRoot();

            // Pre-routing: dichiara lingue + urls path se il consumer lo prevede.
            // Necessario perché Route::expandTranslatableRoutes() ha bisogno
            // della lista di lingue ai loadDirectories.
            $this->bootLanguages($runtimeRoot);

            $routes = Route::loadDirectories([
                $runtimeRoot.'/config/routes',
                $this->root.'/custom/routes',
            ], [
                'ROOT' => $this->root,
                'ROOT_APP' => $runtimeRoot,
            ]);

            // Espande le route translatable in N varianti per lingua.
            // Se il consumer non ha registrato lingue / urls.json è no-op.
            Route::expandTranslatableRoutes(LanguageContext::getLangs());
            $routes = Route::all();

            $router = new Router($routes);
            $pathRoute = $router->matchByPath((string) ($_SERVER['REQUEST_URI'] ?? '/'));

            if (is_array($pathRoute) && !empty($pathRoute['area'])) {
                $this->area = trim((string) $pathRoute['area']);
            }

            if ($this->area === 'api') {
                $this->prepareApiResponse();
            }

            $route = $router->match(
                (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                (string) ($_SERVER['REQUEST_URI'] ?? '/')
            );
            $this->currentRoute = $route;

            if (!$this->isValidRoute($route)) {
                $this->notFound();
            }

            $routeArea = trim((string) ($route['area'] ?? ''));

            if ($routeArea !== '') {
                $this->area = $routeArea;
            }

            if (!in_array($this->area, [ 'api', 'backend', 'frontend' ], true)) {
                $this->fail(500, 'Route area non valida.');
            }

            if (!empty($route['redirect_to']) && is_string($route['redirect_to'])) {
                $this->redirect($route);
            }

            $this->bootApplication($route);

            // Override lingua post-bootApplication se la route matched è una
            // variante tradotta: l'URL vince sulla preferenza utente.
            if (!empty($route['_locale']) && is_string($route['_locale'])) {
                LanguageContext::setLang($route['_locale']);
                LanguageContext::setLangSource('translation');
            }

            // 301 canonical → variante tradotta della lingua corrente,
            // se il consumer è in modalità 'translation' e c'è una traduzione
            // disponibile per la lingua corrente.
            $this->maybeRedirectToTranslated($route);

            extract($this->runtimeScope(), EXTR_SKIP);
            include (string) $route['handler'];
            exit();
        } catch (Throwable $throwable) {
            $this->serverError($throwable);
        }
    }

    private function isValidRoute(?array $route): bool
    {
        if (!is_array($route)) {
            return false;
        }

        if (!empty($route['redirect_to']) && is_string($route['redirect_to'])) {
            return true;
        }

        return !empty($route['handler']) && file_exists((string) $route['handler']);
    }

    private function redirect(array $route): never
    {
        $location = (string) $route['redirect_to'];

        foreach ((array) ($route['parameters'] ?? []) as $key => $value) {
            $location = str_replace('{'.$key.'}', rawurlencode((string) $value), $location);
        }

        header('Location: '.$location, true, (int) ($route['redirect_status'] ?? 302));
        exit();
    }

    private function prepareApiResponse(): void
    {
        error_reporting(0);
        ini_set('display_errors', '0');

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Content-Type: application/json; charset=utf-8');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(200);
            exit(0);
        }
    }

    /**
     * Carica i file che dichiarano lingue e path delle traduzioni URL.
     *
     * Avviene PRIMA del routing per permettere a
     * `Route::expandTranslatableRoutes()` di sapere quali lingue espandere.
     * Convenzione cercata: `custom/config/lang.php` del consumer.
     */
    private function bootLanguages(string $runtimeRoot): void
    {
        $this->bootLanguageHelpers($runtimeRoot);

        $candidates = [
            $this->root.'/custom/config/lang.php',
        ];

        $ROOT = $this->root;
        $ROOT_APP = $runtimeRoot;

        foreach ($candidates as $file) {
            if (is_file($file)) {
                require_once $file;
            }
        }
    }

    private function bootLanguageHelpers(string $runtimeRoot): void
    {
        $GLOBALS['ROOT'] = $this->root;
        $GLOBALS['ROOT_APP'] = $runtimeRoot;

        require_once $runtimeRoot.'/function/helper.php';
    }

    /**
     * 301 redirect dalla canonical alla variante tradotta della lingua
     * corrente, se entrambe le condizioni sono vere:
     *
     * 1. il consumer è in modalità `langSource = 'translation'`;
     * 2. la route matched è la canonical (no `_locale`);
     * 3. esiste una traduzione del path canonical per la lingua corrente.
     *
     * I parametri della route (es. `{slug}`) vengono propagati nel path
     * tradotto. La query string viene preservata.
     */
    private function maybeRedirectToTranslated(array $route): void
    {
        if (LanguageContext::getLangSource() !== 'translation') {
            return;
        }

        // Se è una variante tradotta, niente redirect (siamo già "in lingua").
        if (!empty($route['_locale'])) {
            return;
        }

        $canonicalPath = (string) ($route['_canonical_path'] ?? '');
        if ($canonicalPath === '') {
            return;
        }

        $canonicalKey = trim($canonicalPath, '/');
        if ($canonicalKey === '') {
            return;
        }

        $currentLang = LanguageContext::getLang();
        if ($currentLang === '') {
            return;
        }

        if (!UrlTranslator::has($canonicalKey, $currentLang)) {
            return;
        }

        $translated = UrlTranslator::translate($canonicalKey, $currentLang);
        if ($translated === $canonicalKey) {
            return;
        }

        // Costruisci il path tradotto e sostituisci i parametri della route
        $translatedPath = '/'.$translated.'/';
        foreach ((array) ($route['parameters'] ?? []) as $key => $value) {
            $translatedPath = str_replace('{'.$key.'}', rawurlencode((string) $value), $translatedPath);
        }

        $location = $translatedPath;

        $qs = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
        if (is_string($qs) && $qs !== '') {
            $location .= '?'.$qs;
        }

        header('Location: '.$location, true, 301);
        exit();
    }

    private function bootApplication(array $route): void
    {
        LegacyGlobals::share([
            'ROOT' => $this->root,
            'ROOT_APP' => $this->runtimeRoot(),
            'FRONTEND' => !empty($route['frontend']) || ($route['area'] ?? null) === 'frontend',
            'BACKEND' => !empty($route['backend']) || ($route['area'] ?? null) === 'backend',
            'PRIVATE' => (bool) ($route['private'] ?? false),
            'PERMIT' => is_array($route['permit'] ?? null) ? $route['permit'] : [],
            'ROUTE_PARAMETERS' => is_array($route['parameters'] ?? null) ? $route['parameters'] : [],
            'ROUTE_META' => $route,
        ]);

        require_once $this->appPackageRoot().'/wonder-image.php';
    }

    private function runtimeScope(): array
    {
        return LegacyGlobals::scope();
    }

    private function notFound(): never
    {
        $this->fail(404, $this->area === 'api' ? 'Endpoint non trovato.' : '');
    }

    private function serverError(Throwable $throwable): never
    {
        $this->logThrowable($throwable);

        $this->fail(
            500,
            $this->area === 'api' ? $throwable->getMessage() : '',
            $throwable
        );
    }

    private function fail(int $status, string $message, ?Throwable $throwable = null): never
    {
        http_response_code($status);

        if ($this->area === 'api') {
            echo json_encode([
                'success' => false,
                'status' => $status,
                'response' => $message !== '' ? $message : 'Errore interno.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }

        LegacyGlobals::share([
            'ROOT' => $this->root,
            'ROOT_APP' => $this->runtimeRoot(),
            'FRONTEND' => $this->area === 'frontend',
            'BACKEND' => $this->area === 'backend',
            'PRIVATE' => false,
            'PERMIT' => [],
            'ERROR' => $status,
            'ERROR_MESSAGE' => $this->debugEnabled() && $throwable !== null ? (string) $throwable->getMessage() : '',
            'ERROR_FILE' => $this->debugEnabled() && $throwable !== null ? (string) $throwable->getFile() : '',
            'ERROR_LINE' => $this->debugEnabled() && $throwable !== null ? (int) $throwable->getLine() : 0,
            'ERROR_TRACE' => $this->debugEnabled() && $throwable !== null ? (string) $throwable->getTraceAsString() : '',
        ]);

        require_once $this->appPackageRoot().'/wonder-image.php';
        include $this->runtimeRoot().'/view/error/http.php';
        exit();
    }

    private function ensureRequestIdentifiers(): void
    {
        $requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ($_SERVER['REQUEST_ID'] ?? '')));
        if ($requestId === '') {
            try {
                $requestId = bin2hex(random_bytes(8));
            } catch (Throwable) {
                $requestId = uniqid('req_', true);
            }
        }

        $_SERVER['REQUEST_ID'] = $requestId;

        $traceId = trim((string) ($_SERVER['HTTP_TRACE_ID'] ?? ($_SERVER['TRACE_ID'] ?? '')));
        if ($traceId === '') {
            $_SERVER['TRACE_ID'] = $requestId;
        }
    }

    private function logThrowable(Throwable $throwable): void
    {
        Logger::log(
            $throwable,
            'http',
            'route_dispatch',
            'ERROR',
            'storage/logs/error/http',
            [
                'area' => $this->area,
                'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
                'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                'route' => $this->summarizeCurrentRoute(),
            ],
            false
        );
    }

    private function summarizeCurrentRoute(): array
    {
        if (!is_array($this->currentRoute)) {
            return [];
        }

        return [
            'name' => (string) ($this->currentRoute['name'] ?? ''),
            'area' => (string) ($this->currentRoute['area'] ?? ''),
            'handler' => (string) ($this->currentRoute['handler'] ?? ''),
            'path' => (string) ($this->currentRoute['path'] ?? ''),
            'parameters' => is_array($this->currentRoute['parameters'] ?? null) ? $this->currentRoute['parameters'] : [],
        ];
    }

    private function appPackageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function runtimeRoot(): string
    {
        if (is_string($this->runtimeRoot) && $this->runtimeRoot !== '') {
            return $this->runtimeRoot;
        }

        $appPackageRoot = $this->appPackageRoot();

        if (is_dir($appPackageRoot.'/app/config/routes')) {
            $this->runtimeRoot = $appPackageRoot.'/app';
            return $this->runtimeRoot;
        }

        if (is_dir($appPackageRoot.'/config/routes')) {
            $this->runtimeRoot = $appPackageRoot;
            return $this->runtimeRoot;
        }

        if (!is_string($this->runtimeRoot) || $this->runtimeRoot === '') {
            throw new \RuntimeException('Cartella runtime di wonder-image/app non trovata.');
        }

        return $this->runtimeRoot;
    }

    private function debugEnabled(): bool
    {
        $env = $_ENV['APP_DEBUG'] ?? ($_SERVER['APP_DEBUG'] ?? null);

        if (is_bool($env)) {
            return $env;
        }

        $envValue = strtolower(trim((string) $env));

        if (in_array($envValue, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $serverName = trim((string) ($_SERVER['SERVER_NAME'] ?? ''));

        return in_array($remoteAddr, ['127.0.0.1', '::1'], true)
            || in_array($serverName, ['127.0.0.1', 'localhost'], true);
    }
}
