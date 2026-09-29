<?php
/** php tests/Backend/Support/ResourceTableColumnsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Support\ResourceTableRenderer;

$schema = [
    'photo' => ['name' => 'photo', 'type' => 'image'],
    'name' => ['name' => 'name', 'type' => 'text'],
    'sku' => ['name' => 'sku', 'type' => 'text'],
    'price' => ['name' => 'price', 'type' => 'price'],
    'actions' => ['name' => 'actions', 'type' => 'button'],
];

check('senza elenco le colonne restano tutte, nel loro ordine', function () use ($schema) {
    return ResourceTableRenderer::pickColumns($schema, []) === $schema;
});

check('con l\'elenco restano solo le colonne chieste, nell\'ordine chiesto', function () use ($schema) {
    $scelte = ResourceTableRenderer::pickColumns($schema, ['sku', 'name', 'actions']);

    return array_keys($scelte) === ['sku', 'name', 'actions']
        && $scelte['name'] === $schema['name'];
});

check('una colonna che lo schema non ha viene saltata', function () use ($schema) {
    return array_keys(ResourceTableRenderer::pickColumns($schema, ['sku', 'peso', 'price'])) === ['sku', 'price'];
});

check('una colonna chiesta due volte compare una volta sola', function () use ($schema) {
    return array_keys(ResourceTableRenderer::pickColumns($schema, ['sku', 'sku'])) === ['sku'];
});

check('un elenco di sole colonne sconosciute torna vuoto', function () use ($schema) {
    return ResourceTableRenderer::pickColumns($schema, ['peso']) === [];
});

summary();
