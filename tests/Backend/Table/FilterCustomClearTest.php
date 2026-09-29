<?php
/** php tests/Backend/Table/FilterCustomClearTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Table\Table;

// Il costruttore apre la connessione: qui serve solo lo stato dei filtri,
// che comanda la barra sopra la tabella.
$tabella = static function (): Table {
    return (new ReflectionClass(Table::class))->newInstanceWithoutConstructor();
};

$filtri = static function (Table $table): array {
    $property = new ReflectionProperty(Table::class, 'filterCustom');

    return (array) $property->getValue($table);
};

check('addFilter registra il filtro personalizzato', function () use ($tabella, $filtri) {
    $table = $tabella();
    $table->addFilter('Tipo', 'type', ['in' => 'Entrata']);

    return count($filtri($table)) === 1;
});

check('filterCustom(false) toglie tutti i filtri personalizzati', function () use ($tabella, $filtri) {
    $table = $tabella();
    $table->addFilter('Tipo', 'type', ['in' => 'Entrata']);
    $table->addFilter('Causale', 'reason', ['sale' => 'Vendita']);
    $table->filterCustom(false);

    return $filtri($table) === [];
});

check('filterCustom() torna la tabella per incatenare le chiamate', function () use ($tabella) {
    $table = $tabella();

    return $table->filterCustom(false) === $table;
});

check('filterCustom(true) lascia i filtri dove sono', function () use ($tabella, $filtri) {
    $table = $tabella();
    $table->addFilter('Tipo', 'type', ['in' => 'Entrata']);
    $table->filterCustom(true);

    return count($filtri($table)) === 1;
});

summary();
