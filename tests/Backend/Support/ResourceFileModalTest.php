<?php
/**
 * Bottone «File»: finestra con le schede Esporta e Importa.
 *
 *   php tests/Backend/Support/ResourceFileModalTest.php
 */
declare(strict_types=1);

ob_start(); // l'output dei check non deve bloccare session_start()

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

if (!function_exists('__t')) {
    function __t(string $key): string
    {
        return $key;
    }
}

ini_set('session.use_cookies', '0');
ini_set('session.save_path', sys_get_temp_dir());
session_id('t');
@session_start();
$_SESSION = [];

use Wonder\Backend\Support\ResourceFileModal as F;

$export = static fn (array $over = []): array => $over + [
    'action' => '/backend/products/export/',
    'label' => 'Esporta',
    'formats' => ['xlsx' => 'Excel', 'csv' => 'CSV'],
    'profiles' => [3 => 'Meta'],
    'layout_formats' => null,
];
$import = static fn (array $over = []): array => $over + [
    'action' => '/backend/import/',
    'layout' => 'products',
    'profiles' => [5 => 'Fornitore'],
    'default' => 5,
    'sample' => '/backend/import/sample/products/',
];
$modal = static fn (?array $e, ?array $i): string => F::build('products', $e, $i)['modal']->render('bootstrap');

check('export e import: due schede', function () use ($export, $import, $modal) {
    $built = F::build('products', $export(), $import());
    $html = $built['modal']->render('bootstrap');
    $button = $built['button']->render('bootstrap');

    return str_contains($html, 'nav-tabs')
        && (bool) preg_match('/class="tab-pane fade show active" id="wi-file-products-export"/', $html)
        && str_contains($html, 'wi-file-products-import')
        && str_contains($html, 'id="wi-file-products"')
        && str_contains($button, 'File')
        && str_contains($button, 'bi-arrow-down-up')
        && str_contains($button, '#wi-file-products');
});

check('solo export: una scheda, niente barra, niente upload', function () use ($export, $modal) {
    $html = $modal($export(), null);

    return !str_contains($html, 'nav-tabs') && !str_contains($html, 'name="file')
        && !str_contains($html, 'Continua') && str_contains($html, 'Scarica');
});

check('solo import: una scheda senza Scarica', function () use ($import, $modal) {
    $html = $modal(null, $import());

    return !str_contains($html, '>Scarica<') && str_contains($html, 'Continua')
        && str_contains($html, 'name="file');
});

check('né export né import: null', fn () => F::build('products', null, null) === null);

check('preselect: ultima scelta valida', fn () => F::preselect(['xlsx' => 'Excel', 'csv' => 'CSV'], [3 => 'Meta'], ['format' => 'csv', 'layout' => 3])
    === ['format' => 'csv', 'layout' => 3]);

check('preselect: tracciato sparito e formato tolto tornano ai valori di base', fn () => F::preselect(['xlsx' => 'Excel', 'csv' => 'CSV'], [3 => 'Meta'], ['format' => 'xml', 'layout' => 9])
    === ['format' => 'xlsx', 'layout' => null]);

check('preselect: senza ultima scelta', fn () => F::preselect(['csv' => 'CSV'], [], null) === ['format' => 'csv', 'layout' => null]);

check('il form di Esporta è GET verso export.file, senza spinner', function () use ($export, $modal) {
    $html = $modal($export(), null);

    return (bool) preg_match('/method="get"/i', $html)
        && str_contains($html, 'action="/backend/products/export/"')
        && !str_contains($html, 'loadingSpinner');
});

check('il testo d\'aiuto compare se un formato non accetta i tracciati', function () use ($export, $modal) {
    $html = $modal($export(['formats' => ['xlsx' => 'Excel', 'csv' => 'CSV', 'meta' => 'Meta'], 'layout_formats' => ['xlsx', 'csv']]), null);

    return str_contains($html, 'I tracciati valgono per Excel e CSV');
});

check('niente testo d\'aiuto se tutti i formati accettano i tracciati', function () use ($export, $modal) {
    return !str_contains($modal($export(), null), 'I tracciati valgono per');
});

check('niente select tracciato senza tracciati salvati', function () use ($export, $modal) {
    return !str_contains($modal($export(['profiles' => []]), null), 'name="layout"');
});

check('con tracciati il select layout c\'è', function () use ($export, $modal) {
    $html = $modal($export(), null);

    return str_contains($html, 'name="layout"') && str_contains($html, 'Tutte le colonne');
});

check('nomi con HTML escapati', function () use ($export, $import, $modal) {
    $html = $modal($export(['profiles' => [3 => '<b>X</b>'], 'label' => '<i>E</i>']), $import());

    return str_contains($html, '&lt;b&gt;X&lt;/b&gt;') && !str_contains($html, '<b>X</b>')
        && str_contains($html, '&lt;i&gt;E&lt;/i&gt;') && !str_contains($html, '<i>E</i>');
});

check('Importa: POST con CSRF e action=upload', function () use ($import, $modal) {
    $html = $modal(null, $import());

    return str_contains($html, 'name="_csrf"') && str_contains($html, 'value="upload"')
        && str_contains($html, 'name="import_layout_id"')
        && str_contains($html, 'action="/backend/import/"')
        && str_contains($html, '/backend/import/sample/products/');
});

check('Importa senza tracciati: niente select', function () use ($import, $modal) {
    return !str_contains($modal(null, $import(['profiles' => [], 'default' => null])), 'name="import_layout_id"');
});

check('i bottoni occupano la riga intera, le radio sono pillole', function () use ($export, $import, $modal) {
    $html = $modal($export(), $import());

    return !preg_match('/col-1"[^>]*>(?:<div[^>]*>)*<button[^>]*>(Scarica|Continua)/', $html)
        && !preg_match('/col-1"[^>]*>(?:<div[^>]*>)*<a[^>]*>Scarica il modello/', $html)
        && !str_contains($html, 'height: 120px');
});

check('l\'ultima scelta arriva nel markup', function () use ($export) {
    $html = F::build('products', $export(), null, ['format' => 'csv', 'layout' => 3])['modal']->render('bootstrap');

    return (bool) preg_match('/value="csv"[^>]*checked/', $html)
        && !preg_match('/value="xlsx"[^>]*checked/', $html)
        && (bool) preg_match('/<option value="3" selected>/', $html);
});

// Scheda «Feed prodotti»
$feed = static fn (array $over = []): array => $over + [
    'format' => 'meta',
    'label' => 'Catalogo Meta',
    'url' => 'https://negozio.it/shared/feeds/catalogo-meta.csv',
    'updated_at' => mktime(14, 5, 0, 11, 5, 2026),
    'every' => 'Ogni 6 ore',
    'download' => '/backend/products/export/?format=meta',
];
$withFeed = static fn (array $over = []): array => $export([
    'formats' => ['xlsx' => 'Excel', 'csv' => 'CSV', 'meta' => 'Catalogo Meta'],
    'layout_formats' => ['xlsx', 'csv'],
    'feeds' => [$feed($over)],
]);

check('feed: terza scheda con indirizzo, Copia e Scarica ora', function () use ($withFeed, $import, $modal) {
    $html = $modal($withFeed(), $import());

    return str_contains($html, 'wi-file-products-feeds') && str_contains($html, 'Feed prodotti')
        && str_contains($html, 'value="https://negozio.it/shared/feeds/catalogo-meta.csv"')
        && str_contains($html, 'readonly') && str_contains($html, 'copyText(')
        && str_contains($html, 'Aggiornato il 05/11/2026 alle 14:05 · ogni 6 ore')
        && str_contains($html, 'href="/backend/products/export/?format=meta"');
});

check('feed: il formato del feed esce da Esporta, e con lui il testo d\'aiuto', function () use ($withFeed, $modal) {
    $html = $modal($withFeed(), null);

    return !str_contains($html, 'value="meta"') && str_contains($html, 'value="xlsx"')
        && !str_contains($html, 'I tracciati valgono per');
});

check('feed: ultima scelta «meta» ricade su Excel', function () use ($withFeed) {
    $html = F::build('products', $withFeed(), null, ['format' => 'meta', 'layout' => null])['modal']->render('bootstrap');

    return (bool) preg_match('/value="xlsx"[^>]*checked/', $html);
});

check('feed: solo formati feed, resta solo la scheda Feed prodotti', function () use ($export, $feed, $modal) {
    $html = $modal($export(['formats' => ['meta' => 'Catalogo Meta'], 'feeds' => [$feed()]]), null);

    return !str_contains($html, 'nav-tabs') && !str_contains($html, '>Scarica<')
        && str_contains($html, 'Scarica ora');
});

check('feed: senza feed niente scheda', fn () => !str_contains($modal($export(), $import()), 'Feed prodotti'));

check('feed: mai generato e senza dominio', function () use ($withFeed, $modal) {
    $html = $modal($withFeed(['url' => '', 'updated_at' => null, 'every' => null]), null);

    return str_contains($html, 'Non ancora generato') && str_contains($html, 'manca il dominio')
        && !str_contains($html, 'copyText(');
});

check('feed: indirizzo con apici non rompe onclick', function () use ($withFeed, $modal) {
    $html = $modal($withFeed(['url' => "https://x.it/a'b\"c"]), null);

    return !str_contains($html, "a'b") && str_contains($html, 'copyText(');
});

check('feedsFor: scarta i formati non concessi e aggiunge ?format=', function () {
    $feeds = [['format' => 'meta', 'label' => 'M'], ['format' => 'google', 'label' => 'G']];
    $a = \Wonder\Backend\Support\ResourceTableRenderer::feedsFor($feeds, ['xlsx' => 'Excel', 'meta' => 'M'], '/x/export/');
    $b = \Wonder\Backend\Support\ResourceTableRenderer::feedsFor($feeds, ['meta' => 'M'], '/x/export/?a=1');

    return count($a) === 1 && $a[0]['download'] === '/x/export/?format=meta'
        && $b[0]['download'] === '/x/export/?a=1&format=meta';
});

summary();

