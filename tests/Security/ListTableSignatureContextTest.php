<?php
/**
 * php tests/Security/ListTableSignatureContextTest.php
 *
 * I frammenti firmati della config di Table valgono solo per la tabella, lo
 * schema e il campo con cui sono stati emessi: cambiare `config[table]` o
 * `custom[schema]` (o spostare un blob su un altro campo) dà l'errore di firma
 * prima di qualsiasi query, COUNT compreso.
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Table as AppTable;
use Wonder\Backend\Table\ConfigCodec;
use Wonder\Backend\Table\ListProvider;
use Wonder\Backend\Table\ListTableRequest;
use Wonder\Backend\Table\Table;

$_ENV['APP_KEY'] = 'test-app-key-0123456789abcdef';

// Registro come al bootstrap: chiavi minuscole.
AppTable::$list = [
    'contacts'           => [],
    'gst_product_models' => [],
    'api_users'          => [],
    'customers'          => [],
    'suppliers'          => [],
];

// Stub della prima query di fetch() (app/function/sql.php non è caricato):
// registra la chiamata e si ferma lì.
$GLOBALS['__queries'] = [];
function sqlCount($table, $query = null, $column = '*', $distinct = false)
{
    $GLOBALS['__queries'][] = [$table, $query];
    throw new RuntimeException('QUERY_REACHED');
}

/** Config emessa da Table::buildConfig() per tabella/schema/query dati. */
function emitted(string $table, string $schema, string $query): array
{
    $ref = new ReflectionClass(Table::class);
    $t   = $ref->newInstanceWithoutConstructor();
    $set = function (string $prop, $value) use ($ref, $t): void {
        $ref->getProperty($prop)->setValue($t, $value);
    };

    $set('id', ['table' => 't', 'page' => 'p', 'length' => 'l', 'search' => 's', 'order' => 'o', 'order_dir' => 'od']);
    $set('query', $query);
    $set('queryCustom', $query);
    $set('filterSearch', ['fields' => ['name', 'surname']]);
    $t->table = $table;
    $t->database = 'main';
    $t->endpointValues = $schema === '' ? [] : ['schema' => $schema];

    return $ref->getMethod('buildConfig')->invoke($t);
}

/** Simula list-table.php: allow-list, poi fetch(). Restituisce [esito, query]. */
function replay(array $request): array
{
    $GLOBALS['__queries'] = [];
    $table = ListTableRequest::table($request['config']['table'] ?? null);
    if ($table === null) {
        return ['422', []];
    }

    $name = (object) [
        'table'    => $table,
        'database' => 'main',
        'schema'   => ListTableRequest::schema($request['custom']['schema'] ?? null),
    ];
    $stub = (object) [];
    $request += ['draw' => 3, 'fields' => []];

    try {
        $result = ListProvider::fetch($request, $name, $stub, $stub, $stub, null);
    } catch (RuntimeException $e) {
        return [$e->getMessage(), $GLOBALS['__queries']];
    }

    return [isset($result['error']) ? 'signature' : 'ok', $GLOBALS['__queries'], $result];
}

$customerQuery = "deleted = 'false' AND is_customer = 'true'";
$contacts      = emitted('contacts', 'customers', $customerQuery);

echo "Replay su altra tabella / altro schema\n";

check('(a) frammenti di contacts con config[table]=gst_product_models: errore di firma, nessuna query', function () use ($contacts) {
    $r = $contacts;
    $r['config']['table'] = 'gst_product_models';
    [$outcome, $queries, $result] = replay($r) + [2 => []];
    return $outcome === 'signature' && $queries === [] && ($result['data'] ?? null) === []
        && ($result['draw'] ?? null) === 3 && !str_contains((string) json_encode($result), 'is_customer');
});

check('(a) lista senza query firmata per contacts riusata su api_users: errore di firma', function () {
    $r = emitted('contacts', '', '');
    $r['config']['table'] = 'api_users';
    [$outcome, $queries] = replay($r);
    return $outcome === 'signature' && $queries === [];
});

check('(b) custom[schema] diverso e registrato: errore di firma, nessuna query', function () use ($contacts) {
    $r = $contacts;
    $r['custom']['schema'] = 'suppliers';
    [$outcome, $queries] = replay($r);
    return $outcome === 'signature' && $queries === [];
});

check('(b) custom[schema] tolto o non registrato: errore di firma', function () use ($contacts) {
    $r = $contacts;
    unset($r['custom']['schema']);
    $r2 = $contacts;
    $r2['custom']['schema'] = 'non_registrato';
    return replay($r)[0] === 'signature' && replay($r2)[0] === 'signature';
});

check('blob spostato su un altro campo (query_filter -> query): errore di firma', function () {
    $r = emitted('contacts', 'customers', "is_customer = 'true'");
    $r['config']['query'] = $r['config']['query_custom'];
    return replay($r)[0] === 'signature';
});

check('blob firmato senza contesto (formato precedente): errore di firma', function () use ($contacts) {
    $r = $contacts;
    $r['config']['query'] = ConfigCodec::encode('');
    return replay($r)[0] === 'signature';
});

echo "\nConfig intatta\n";

check('(c) config emessa per contacts: arriva alla query con tabella e WHERE firmati', function () use ($contacts, $customerQuery) {
    [$outcome, $queries] = replay($contacts);
    return $outcome === 'QUERY_REACHED' && $queries === [['contacts', $customerQuery]];
});

check('(c) tabella senza schema: arriva alla query', function () {
    [$outcome, $queries] = replay(emitted('api_users', '', ''));
    return $outcome === 'QUERY_REACHED' && $queries === [['api_users', '']];
});

check('(d) config[table] in maiuscolo: la chiave canonica firmata combacia', function () use ($contacts) {
    $r = $contacts;
    $r['config']['table'] = 'CONTACTS';
    $r['custom']['schema'] = 'Customers';
    [$outcome, $queries] = replay($r);
    return $outcome === 'QUERY_REACHED' && ($queries[0][0] ?? null) === 'contacts';
});

check('(d) Table con nome non canonico firma comunque la chiave canonica', function () use ($customerQuery) {
    [$outcome, $queries] = replay(emitted('Contacts', 'CUSTOMERS', $customerQuery));
    return $outcome === 'QUERY_REACHED' && ($queries[0][0] ?? null) === 'contacts';
});

echo "\nContesto\n";

check('context() distingue tabella, schema e campo, ignora il casing', function () {
    $base = ConfigCodec::context('contacts', 'customers', 'query');
    return $base === ConfigCodec::context(' CONTACTS ', 'Customers', 'query')
        && $base !== ConfigCodec::context('gst_product_models', 'customers', 'query')
        && $base !== ConfigCodec::context('contacts', '', 'query')
        && $base !== ConfigCodec::context('contacts', 'customers', 'query_filter');
});

check('decode con contesto diverso da encode: null', function () {
    $key  = 'k';
    $blob = ConfigCodec::encode('x = 1', $key, ConfigCodec::context('contacts', '', 'query'));
    return ConfigCodec::decode($blob, $key, ConfigCodec::context('contacts', '', 'query')) === 'x = 1'
        && ConfigCodec::decode($blob, $key, ConfigCodec::context('api_users', '', 'query')) === null
        && ConfigCodec::decode($blob, $key) === null;
});

summary();
