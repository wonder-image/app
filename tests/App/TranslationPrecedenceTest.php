<?php
/** php tests/App/TranslationPrecedenceTest.php */
declare(strict_types=1);

/**
 * Verifica il contratto di precedenza delle traduzioni: in
 * TranslationProvider::loadFiles() i path registrati dopo sovrascrivono i
 * valori ridichiarati, quindi l'ordine finale prodotto dal bootstrap deve
 * essere core → moduli → sito (il sito fa override di tutto).
 *
 * Replica l'ordine reale di registrazione post-fix per un sito con
 * custom/config/lang.php caricato pre-routing dal RouteDispatcher:
 *   1. custom/config/lang.php  → sito
 *   2. TranslationBootstrap    → core, moduli, sito
 *   3. app/service/lang.php    → core, moduli, sito (re-append) + init()
 */

require __DIR__.'/../../vendor/autoload.php';

use Wonder\Localization\LanguageContext;
use Wonder\Localization\TranslationProvider;

$sandbox = sys_get_temp_dir().'/lang-precedence-test-'.uniqid();

$fail = 0;
function same(string $label, mixed $actual, mixed $expected): void
{
    global $fail;
    if ($actual === $expected) {
        echo "ok: $label\n";
    } else {
        $fail++;
        echo "FAIL: $label\n  expected: ".var_export($expected, true)."\n  actual:   ".var_export($actual, true)."\n";
    }
}

// Tre sorgenti: core, modulo e sito ridichiarano `pages.demo.text`;
// ogni sorgente dichiara anche una chiave solo propria (il merge non deve
// perdere le chiavi non ridichiarate).
$makeLangDir = static function (string $name, array $content) use ($sandbox): string {
    $dir = $sandbox.'/'.$name.'/it';
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/pages.json', json_encode($content));

    return $sandbox.'/'.$name;
};

$core = $makeLangDir('core', ['demo' => ['text' => 'core', 'only_core' => 'core']]);
$module = $makeLangDir('module', ['demo' => ['text' => 'module', 'only_module' => 'module']]);
$site = $makeLangDir('site', ['demo' => ['text' => 'site', 'only_site' => 'site']]);

// Ordine di registrazione del bootstrap reale (con i duplicati che produce).
LanguageContext::addLangPath($site);      // custom/config/lang.php (pre-routing)
LanguageContext::addLangPath($core);      // TranslationBootstrap::preload
LanguageContext::addLangPath($module);    //
LanguageContext::addLangPath($site);      //
LanguageContext::addLangPath($core);      // app/service/lang.php
LanguageContext::addLangPath($module);    //
LanguageContext::addLangPath($site);      // app/service/lang.php (re-append sito)

LanguageContext::defaultLang('it');
TranslationProvider::init();

same('il sito sovrascrive core e moduli', TranslationProvider::get('pages.demo.text'), 'site');
same('le chiavi solo-core sopravvivono al merge', TranslationProvider::get('pages.demo.only_core'), 'core');
same('le chiavi solo-modulo sopravvivono al merge', TranslationProvider::get('pages.demo.only_module'), 'module');
same('le chiavi solo-sito sopravvivono al merge', TranslationProvider::get('pages.demo.only_site'), 'site');

// Pulizia sandbox.
foreach ([$core, $module, $site] as $base) {
    @unlink($base.'/it/pages.json');
    @rmdir($base.'/it');
    @rmdir($base);
}
@rmdir($sandbox);

if ($fail > 0) {
    echo "\n$fail assertion(s) failed\n";
    exit(1);
}

echo "\nTranslation precedence tests passed\n";
