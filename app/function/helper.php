<?php

    /**
     * Helper globali
     */

    // Testi
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (!is_scalar($value) && !$value instanceof \Stringable) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $value = $encoded === false ? '' : $encoded;
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function js_e(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? 'null' : $encoded;
    }
    
    function __t(string $key, array $replacements = []): mixed
    {
        return Wonder\Localization\TranslationProvider::get($key, $replacements);
    }

    // Lingua corrente
    function __l(): string
    {
        return Wonder\Localization\LanguageContext::getLang();
    }

    // Lingue disponibili
    function __ls(): array
    {
        return Wonder\Localization\LanguageContext::getLangs();
    }

    // Lingua default
    function __dl(): string
    {
        return Wonder\Localization\LanguageContext::getDefaultLang();
    }
    
    // Url
    function __u(string $path = ''): string
    {
        return Wonder\Localization\LanguageContext::createLangUrl($path);
    }

    // Routes
    function __r(string $name, array $parameters = []): string {
        $url = \Wonder\Http\Route::url($name, $parameters);

        if ($url !== '') {
            return $url;
        }

        global $ROOT;
        global $ROOT_APP;

        if (!isset($ROOT, $ROOT_APP) || !is_string($ROOT) || !is_string($ROOT_APP)) {
            return '';
        }

        \Wonder\Http\Route::loadDirectories([
            $ROOT_APP.'/config/routes',
            $ROOT.'/custom/routes'
        ], [
            'ROOT' => $ROOT,
            'ROOT_APP' => $ROOT_APP,
        ]);

        return \Wonder\Http\Route::url($name, $parameters);

    }

    // API endpoint path (without domain and /api prefix)
    function __e(string $name, array $parameters = []): string {
        $path = \Wonder\Http\Route::resolvePath($name, $parameters);

        if ($path === '') {
            global $ROOT;
            global $ROOT_APP;

            if (!isset($ROOT, $ROOT_APP) || !is_string($ROOT) || !is_string($ROOT_APP)) {
                return '';
            }

            \Wonder\Http\Route::loadDirectories([
                $ROOT_APP.'/config/routes',
                $ROOT.'/custom/routes'
            ], [
                'ROOT' => $ROOT,
                'ROOT_APP' => $ROOT_APP,
            ]);

            $path = \Wonder\Http\Route::resolvePath($name, $parameters);
        }

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, '/api/')) {
            $path = substr($path, 4);
        }

        return $path;
    }

    // Asset di un modulo (css/js/img): copia pubblicata del sito se esiste,
    // altrimenti il file del modulo. Stringa vuota se il file non esiste.
    function module_asset(string $slug, string $file): string
    {
        return Wonder\App\Module\Assets::url($slug, $file);
    }

    // URL versionato (?v=filemtime) di un file dentro assets/{ASSETS_VERSION}/.
    // Es. __asset('css/main.css') → {APP_URL}/assets/{v}/css/main.css?v=1721035200
    function __asset(string $file): string
    {
        return Wonder\App\Support\Asset::url($file);
    }

    // Appende ?v=filemtime a un URL locale già costruito (prefisso APP_URL o
    // path relativo /...). URL esterni o file inesistenti restano invariati.
    function __asset_version(string $url): string
    {
        return Wonder\App\Support\Asset::version($url);
    }

    // Url Parser
    function __url(?string $url = null): Wonder\Http\UrlParser
    {
        return new Wonder\Http\UrlParser($url);
    }

    // Cambio lingua url
    function __su(string $url, string $lang): string
    {
        return Wonder\Localization\LanguageContext::switchLangUrl($url, $lang);
    }

    // Immagini
    function __i(string $image)
    {
        
        return Wonder\Elements\Media\Image::src($image)
            ->skeleton()
            ->notDraggable()
            ->loading();

    }
    
    // Immagine Responsive
    function __ri(string $image)
    {

        return __i($image)
            ->sizes(RESPONSIVE_IMAGE_SIZES)
            ->hasWebP();

    }

    // Gallery responsive con lightbox Fancybox
    function __gallery(array $images = [])
    {

        return \Wonder\Elements\Media\Gallery::make($images);

    }

    // Swiper: carosello con thumbnails + zoom (Panzoom) o lightbox (Fancybox)
    function __swiper(array $images = [])
    {

        return \Wonder\Elements\Media\Swiper::make($images);

    }

    // Video
    function __v(string $video)
    {

        return \Wonder\Elements\Media\Video::src($video);

    }

    // Log
    function __log( Throwable $exception, string $service, string $action, string $level = 'ERROR', string $file = 'error', array $context = [] ) {

        \Wonder\App\Logger::log($exception, $service, $action, $level, $file, $context);

    }

    function logStripeError(string $action, \Stripe\Exception\ApiErrorException $error) {

        __log($error, 'stripe', $action, 'ERROR', 'error/stripe');

    }

    function logFattureInCloudError(string $action, \FattureInCloud\ApiException $error) {

        __log($error, 'fatture-in-cloud', $action, 'ERROR', 'error/fatture-in-cloud');

    }
