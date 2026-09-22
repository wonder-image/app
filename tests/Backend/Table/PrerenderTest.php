<?php
/** php tests/Backend/Table/PrerenderTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Table\Prerender;

function baseConfig(array $default = []): array {
    return [
        'id' => 'articoli__table',
        'fields' => [ ['name' => 'titolo', 'id' => 0] ],
        'config' => [ 'table' => 'articoli', 'database' => 'main' ],
        'custom' => [ 'schema' => 'articoli' ],
        'default' => array_replace([
            'page' => 0,
            'length' => 10,
            'search' => '',
            'order' => 'creation',
            'order_direction' => 'desc',
        ], $default),
    ];
}

check('il primo draw parte da draw 1, riga 0', function () {
    $request = Prerender::request(baseConfig());

    return $request['draw'] === 1 && $request['start'] === 0 && $request['length'] === 10;
});

check('pagina e lunghezza determinano start', function () {
    $request = Prerender::request(baseConfig(['page' => 2, 'length' => 25]));

    return $request['start'] === 50 && $request['length'] === 25;
});

check('ricerca e ordinamento arrivano dal config', function () {
    $request = Prerender::request(baseConfig(['search' => 'rossi', 'order' => 'nome', 'order_direction' => 'asc']));

    return $request['search'] === ['value' => 'rossi', 'regex' => false]
        && $request['order'] === [['name' => 'nome', 'dir' => 'asc']];
});

check('una lunghezza non valida torna al default', function () {
    $request = Prerender::request(baseConfig(['length' => 0]));

    return $request['length'] === 10 && $request['start'] === 0;
});

check('la request conserva fields e config firmati', function () {
    $config = baseConfig();
    $request = Prerender::request($config);

    return $request['fields'] === $config['fields']
        && $request['config'] === $config['config']
        && $request['custom'] === $config['custom'];
});

check('encode neutralizza la chiusura di script', function () {
    $json = Prerender::encode(['data' => [['</script><script>alert(1)</script>']]]);

    return stripos($json, '</script') === false && stripos($json, '<script') === false;
});

check('encode resta JSON valido e fedele', function () {
    $payload = ['data' => [['<i class="bi bi-x"></i>', 'città']], 'recordsTotal' => 3];

    return json_decode(Prerender::encode($payload), true) === $payload;
});

check('encode non scappa gli slash degli url', function () {
    return str_contains(Prerender::encode(['link' => 'https://www.example.it/backend/view.php']), 'https://www.example.it/backend/view.php');
});

check('lo script consegna config e payload a createDataTables', function () {
    $config  = baseConfig();
    $payload = ['draw' => 1, 'recordsTotal' => 3, 'recordsFiltered' => 3, 'data' => [['Titolo']]];

    $html = Prerender::script('articoli__table', '/api/backend/list-table/', $config, $payload);

    return str_contains($html, "createDataTables('articoli__table', '/api/backend/list-table/', ")
        && str_contains($html, Prerender::encode($config) . ', ' . Prerender::encode($payload) . ')');
});

check('senza payload lo script resta a tre argomenti', function () {
    $config = baseConfig();

    $html = Prerender::script('articoli__table', '/api/backend/list-table/', $config, null);

    return str_contains($html, Prerender::encode($config) . ')')
        && !str_contains($html, 'null)');
});

check('il payload non viaggia dentro la config della richiesta', function () {
    $payload = ['draw' => 1, 'data' => [['Titolo']]];

    $html = Prerender::script('articoli__table', '/api/backend/list-table/', baseConfig(), $payload);

    return !str_contains(Prerender::encode(baseConfig()), 'initialData')
        && substr_count($html, '"data":[["Titolo"]]') === 1;
});

check('un payload ostile non chiude il tag script', function () {
    $payload = ['data' => [['</script><script>alert(1)</script>']]];

    $html = Prerender::script('articoli__table', '/api/backend/list-table/', baseConfig(), $payload);

    return substr_count(strtolower($html), '<script') === 1 && substr_count(strtolower($html), '</script') === 1;
});

check('lo script aspetta l\'evento loaded', function () {
    $html = Prerender::script('articoli__table', '/api/backend/list-table/', baseConfig(), null);

    return str_contains($html, "window.addEventListener('loaded'");
});

summary();
