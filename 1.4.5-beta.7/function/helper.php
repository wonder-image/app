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

    // Immagini
    function __i()
    {

    }

    // Video
    function __v()
    {

    }