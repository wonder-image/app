<?php
/** php tests/Frontend/MetaPixelRemovedTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

$root = dirname(__DIR__, 2);
$head = (string) file_get_contents($root.'/app/view/components/frontend/layout/head.php');

// Testo del sorgente: head.php richiede l'intero bootstrap frontend. Il ramo
// «Pixel attivo» non esiste più, quindi nessun valore di `analytics` lo stampa.
check('head.php: nessun fbq, fbevents.js o facebook.com/tr', fn () =>
    !str_contains($head, 'fbq') && !str_contains($head, 'connect.facebook.net')
    && !str_contains($head, 'facebook.com/tr'));
check('head.php: nessun output legato a $ANALYTICS->pixel->active', fn () =>
    preg_match('/if\s*\(\s*\$ANALYTICS->pixel->active\s*\)/', $head) === 0
    && preg_match('/<\?=[^?]*\$ANALYTICS->pixel/', $head) === 0);

$coreHits = [];
foreach (['app', 'class'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() === 'php' && str_contains((string) file_get_contents($file->getPathname()), 'fbq(')) {
            $coreHits[] = $file->getPathname();
        }
    }
}
check('core: nessun file PHP emette fbq(', fn () => $coreHits === []);

$resource = (string) file_get_contents($root.'/class/App/Resources/Config/AnalyticsResource.php');
check('AnalyticsResource: campo pixel_facebook mantenuto con il testo di aiuto', fn () =>
    str_contains($resource, "FormField::key('pixel_facebook')")
    && str_contains($resource, "__t('analytics.pixel.deprecated')"));

foreach (['it', 'en'] as $locale) {
    check("lang {$locale}: analytics.pixel.deprecated presente", function () use ($root, $locale) {
        $json = json_decode((string) file_get_contents($root."/resources/lang/{$locale}/analytics.json"), true);

        return is_string($json['pixel']['deprecated'] ?? null) && str_contains($json['pixel']['deprecated'], 'Tag Manager');
    });
}

summary();
