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