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

    // Video
    function __v()
    {

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

    if (!function_exists('props')) {
        /**
         * Normalizza i dati di un componente: applica i default e valida le chiavi
         * obbligatorie. I valori passati in $data vincono sui $defaults.
         *
         * @param array<string,mixed> $data
         * @param array<string,mixed> $defaults
         * @param array<int,string>   $required
         * @return array<string,mixed>
         */
        function props(array $data, array $defaults = [], array $required = []): array
        {
            foreach ($required as $key) {
                if (!array_key_exists($key, $data)) {
                    throw new \InvalidArgumentException("props: chiave obbligatoria mancante: {$key}");
                }
            }

            return array_merge($defaults, $data);
        }
    }
