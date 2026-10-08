<?php
/** php tests/App/AppDefaultsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\AppDefaults;
use Wonder\App\Module\Contracts\ModuleDefaults;
use Wonder\App\RuntimeDefaults;
use Wonder\App\Support\CssFontFamily;
use Wonder\App\Support\DefaultRows;

$righe = AppDefaults::fontRows();

check('le righe precaricate di css_font sono i 9 font predefiniti, visibili', function () use ($righe) {
    $nomi = array_column(RuntimeDefaults::defaultFonts(), 'name');

    foreach ($righe as $riga) {
        if (array_keys($riga) !== ['name', 'link', 'font_family', 'visible'] || $riga['visible'] !== 'true') {
            return false;
        }
    }

    return array_column($righe, 'name') === $nomi;
});

check('la famiglia si scrive già ripulita, come la scrive il seed delle righe', function () use ($righe) {
    foreach (RuntimeDefaults::defaultFonts() as $i => $font) {
        if ($righe[$i]['font_family'] !== CssFontFamily::normalize($font['font-family'])
            || $righe[$i]['link'] !== $font['link']) {
            return false;
        }
    }

    return true;
});

check('su un sito che ha solo Roboto e Montserrat mancano i 7 font del pacchetto', fn () =>
    array_column(DefaultRows::missingRows($righe, 'name', ['Roboto', 'Montserrat']), 'name')
        === ['Inter', 'Open Sans', 'Lato', 'Poppins', 'DM Sans', 'Nunito', 'Work Sans']
);

check('si precaricano come quelle dei moduli: dopo l\'import, prima dell\'export', function () {
    $runner = (string) file_get_contents(dirname(__DIR__, 2).'/class/App/UpdateRunner.php');
    $metodo = substr($runner, (int) strpos($runner, 'function runModuleDefaults'));

    return is_subclass_of(AppDefaults::class, ModuleDefaults::class)
        && strpos($metodo, 'AppDefaults::seed($rows)') !== false
        && strpos($metodo, 'AppDefaults::seed($rows)') < strpos($metodo, 'ModuleRegistry::enabled()');
});

summary();
