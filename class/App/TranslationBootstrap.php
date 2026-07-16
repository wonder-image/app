<?php

namespace Wonder\App;

use Wonder\Localization\LanguageContext;
use Wonder\Localization\TranslationProvider;

final class TranslationBootstrap
{
    private static bool $preloaded = false;

    public static function preload(string $rootApp, string $root): void
    {
        if (self::$preloaded) {
            return;
        }

        self::$preloaded = true;

        try {
            // Ordine di precedenza: core → moduli → sito. In loadFiles() i
            // path successivi sovrascrivono i valori ridichiarati, quindi il
            // sito va registrato per ultimo per poter fare override dei testi
            // dei moduli (e del core).
            $paths = [];

            $coreLangPath = rtrim($rootApp, '/').'/../resources/lang';
            if (is_dir($coreLangPath)) {
                $paths[] = $coreLangPath;
            }

            foreach (Module\Registry::languagePaths() as $path) {
                if (is_dir($path)) {
                    $paths[] = $path;
                }
            }

            $consumerLangPath = rtrim($root, '/').'/lang';
            if (is_dir($consumerLangPath)) {
                $paths[] = $consumerLangPath;
            }

            foreach (array_values(array_unique($paths)) as $path) {
                LanguageContext::addLangPath($path);
            }

            LanguageContext::defaultLang(LanguageContext::getDefaultLang());

            if (LanguageContext::getLangs() === []) {
                LanguageContext::addLanguage('it', 'Italiano', '/', 'it', ['IT']);
            }

            TranslationProvider::init();
        } catch (\Throwable) {
            // Best-effort bootstrap: il contesto completo viene comunque
            // reinizializzato più avanti in app/service/lang.php.
        }
    }
}
