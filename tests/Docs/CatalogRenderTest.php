<?php
/**
 * Ogni scheda del catalogo si carica e ogni suo esempio si rende, senza
 * eccezioni, in ogni tema in cui il componente e l'esempio sono disponibili.
 *
 *   php tests/Docs/CatalogRenderTest.php
 */
declare(strict_types=1);

// Le costanti e le funzioni globali che i renderer si aspettano in una pagina
// vera: senza, un esempio con un'immagine o una Modal non si renderebbe.
if (!defined('ROOT')) { define('ROOT', dirname(__DIR__, 2)); }
if (!defined('APP_URL')) { define('APP_URL', 'http://127.0.0.1:8090'); }
if (!defined('ASSETS_VERSION')) { define('ASSETS_VERSION', 'dev'); }
if (!defined('APP_VERSION')) { define('APP_VERSION', 'dev'); }
if (!defined('RESPONSIVE_IMAGE_SIZES')) { define('RESPONSIVE_IMAGE_SIZES', [240, 480, 620, 960, 1200, 1440, 1920, 2400]); }
if (!defined('RESPONSIVE_IMAGE_WEBP')) { define('RESPONSIVE_IMAGE_WEBP', true); }

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

$ROOT = ROOT;
$ROOT_APP = ROOT.'/app';
require_once $ROOT_APP.'/function/function.php';
\Wonder\App\TranslationBootstrap::preload($ROOT_APP, $ROOT);
// Un utente amministratore finto: i componenti che chiedono un permesso
// (QuickCreateButton) si rendono come nel backend.
\Wonder\App\LegacyGlobals::share(['USER' => (object) ['authority' => ['admin']]]);

use Wonder\Docs\Catalog;
use Wonder\Docs\ExampleRunner;
use Wonder\Docs\ThemeSupport;

echo "CatalogRender\n";

$docs = Catalog::all();

check('il catalogo ha almeno una scheda per categoria', function () use ($docs) {
    $categories = array_unique(array_map(static fn ($doc) => $doc->getCategory(), $docs));

    foreach (array_keys(Catalog::categories()) as $key) {
        if (!in_array($key, $categories, true)) {
            echo "    categoria senza schede: {$key}\n";

            return false;
        }
    }

    return $docs !== [];
});

check('ogni scheda ha titolo, descrizione e almeno un esempio', function () use ($docs) {
    $ok = true;

    foreach ($docs as $slug => $doc) {
        if ($doc->getDescription() === '' || $doc->getExamples() === []) {
            echo "    scheda incompleta: {$slug}\n";
            $ok = false;
        }
    }

    return $ok;
});

check('ogni esempio ha codice', function () use ($docs) {
    $ok = true;

    foreach ($docs as $slug => $doc) {
        foreach ($doc->getExamples() as $index => $example) {
            if (trim($example->getCode()) === '') {
                echo "    esempio senza codice: {$slug} #{$index}\n";
                $ok = false;
            }
        }
    }

    return $ok;
});

foreach ($docs as $slug => $doc) {
    $availability = ThemeSupport::for($doc);

    check("{$slug}: ogni esempio si rende nei temi disponibili", function () use ($doc, $availability) {
        $ok = true;

        foreach ($doc->getExamples() as $index => $example) {
            $rendered = 0;

            foreach ($availability as $theme => $support) {
                if (!$support->available || !$example->supports($theme)) {
                    continue;
                }

                $result = ExampleRunner::render($doc, $example, $theme);

                if ($result->error !== null) {
                    echo "    [{$theme}] esempio #{$index} «{$example->getTitle()}»: {$result->error}\n";
                    $ok = false;
                    continue;
                }

                if (trim($result->html) === '') {
                    echo "    [{$theme}] esempio #{$index} «{$example->getTitle()}»: HTML vuoto\n";
                    $ok = false;
                    continue;
                }

                $rendered++;
            }

            if ($rendered === 0) {
                echo "    esempio #{$index} «{$example->getTitle()}»: nessun tema in cui si renda\n";
                $ok = false;
            }
        }

        return $ok;
    });
}

check('la pagina d\'anteprima Wonder sta nella larghezza dell\'iframe e ancora le label ai campi', function () {
    $page = \Wonder\Docs\PreviewPage::build(['component' => 'form', 'example' => 0, 'theme' => 'wonder'], ROOT.'/app', true);

    ob_start();
    \Wonder\View\View::make(ROOT.'/app/view/pages/docs/preview.php', $page + ['paths' => [], 'token' => '', 'lang' => 'it'])->render();
    $html = (string) ob_get_clean();

    // head.css dà al body width:100%: senza border-box il padding lo allarga oltre lo schermo.
    return str_contains($html, 'box-sizing: border-box !important')
        && str_contains($html, '.wi-input-container { position: relative; }')
        && str_contains($html, 'class="wi-input-container');
});

check('la pagina d\'anteprima avvia gli script della lib come i layout veri', function () {
    $out = [];

    foreach (['wonder', 'bootstrap'] as $theme) {
        $page = \Wonder\Docs\PreviewPage::build(['component' => 'input-price', 'example' => 0, 'theme' => $theme], ROOT.'/app', true);

        ob_start();
        \Wonder\View\View::make(ROOT.'/app/view/pages/docs/preview.php', $page + ['paths' => [], 'token' => '', 'lang' => 'it'])->render();
        $html = (string) ob_get_clean();

        // Il frontend chiama setAos() e setUpPage() al load, il backend solo setUpPage():
        // senza, i campi (AutoNumeric, date, tendine) restano markup statico.
        $out[$theme] = str_contains($html, "['setAos', 'setUpPage']");
    }

    return $out['wonder'] && $out['bootstrap'];
});

summary();
