<?php

    /**
     * Helper globali
     */

    // Testi
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

        __log($error, 'fatture-in-cloud', $action, 'ERROR', 'error/stripe');

    }
