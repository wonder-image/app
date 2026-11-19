<?php
/** php tests/Sql/CreateTableDropGuardTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

ini_set('error_log', '/dev/null');

use Wonder\App\Environment;
use Wonder\Sql\CreateTable;

/**
 * Database finto: una tabella `articoli` già esistente con le colonne `id`,
 * `titolo`, `vecchia`, `deleted`, `last_modified`, `creation`, l'indice
 * `ind_titolo` (dichiarato), `ind_a_mano` (aggiunto a mano) e la chiave
 * primaria. Registra gli ALTER emessi.
 */
$fake = static fn (bool $withForeign = false) => new class($withForeign) extends mysqli {
    /** @var string[] */
    public array $alters = [];

    public function __construct(private bool $withForeign = false) {}

    #[\ReturnTypeWillChange]
    public function real_escape_string($string) { return addslashes($string); }

    #[\ReturnTypeWillChange]
    public function character_set_name() { return 'utf8mb4'; }

    #[\ReturnTypeWillChange]
    public function query($query, $resultMode = MYSQLI_STORE_RESULT)
    {
        if (str_starts_with($query, 'ALTER TABLE `articoli` ') && !str_contains($query, 'ENGINE =') && !str_contains($query, 'DEFAULT CHARACTER')) {
            $this->alters[] = $query;
        }

        $rows = match (true) {
            str_starts_with($query, 'SHOW TABLES LIKE') => [['articoli']],
            str_starts_with($query, 'SHOW COLUMNS FROM') => [['x']],
            str_starts_with($query, 'SELECT DATABASE()') => [['db' => 'test']],
            str_contains($query, 'key_column_usage') && $this->withForeign => str_contains($query, "`column_name` = 'cliente'")
                ? [['cName' => 'articoli_ibfk_1']]
                : [['cName' => 'articoli_ibfk_1'], ['cName' => 'fk_a_mano']],
            str_contains($query, 'key_column_usage') => [],
            str_contains($query, 'statistics') => [['iName' => 'PRIMARY'], ['iName' => 'ind_titolo'], ['iName' => 'ind_a_mano']],
            str_contains($query, 'columns') && str_contains($query, 'cName') => array_map(
                fn ($c) => ['cName' => $c],
                $this->withForeign
                    ? ['id', 'titolo', 'vecchia', 'cliente', 'deleted', 'last_modified', 'creation']
                    : ['id', 'titolo', 'vecchia', 'deleted', 'last_modified', 'creation']
            ),
            default => [],
        };

        return new class($rows) {
            public int $num_rows;
            public function __construct(private array $rows) { $this->num_rows = count($rows); }
            public function fetch_assoc(): ?array { return array_shift($this->rows); }
            public function fetch_all($mode = null): array { return $this->rows; }
            public function fetch_row(): ?array { return array_shift($this->rows); }
            public function free(): void {}
        };
    }
};

$schema = [
    'titolo' => ['type' => 'VARCHAR', 'length' => 200],
    'ind_titolo' => ['index' => 'titolo'],
];

$schemaWithForeign = $schema + [
    'cliente' => ['type' => 'INT', 'length' => 11, 'foreign_table' => 'clienti'],
];

$run = function (?string $env, ?bool $force = null, bool $withForeign = false) use ($fake, $schema, $schemaWithForeign): array {
    $previous = $_ENV['APP_ENV'];
    $_ENV['APP_ENV'] = $env ?? 'production';
    putenv('APP_ENV='.$_ENV['APP_ENV']);
    Environment::reset();

    try {
        $mysqli = $fake($withForeign);
        $table = new CreateTable($mysqli);
        $table->allowDestructive = $force;
        $result = $table->Table('articoli', $withForeign ? $schemaWithForeign : $schema);
    } finally {
        $_ENV['APP_ENV'] = $previous;
        putenv('APP_ENV='.$previous);
        Environment::reset();
    }

    return [$mysqli->alters, $table->skipped, $result];
};

// Senza ambiente indicato si usa `production`: niente DROP COLUMN, l'indice
// a mano resta, quelli dichiarati vengono ricreati nella stessa query.
check('production: la colonna non dichiarata non viene eliminata', function () use ($run) {
    [$alters, $skipped] = $run(null);

    return count($alters) === 1
        && !str_contains($alters[0], 'DROP COLUMN')
        && in_array(['table' => 'articoli', 'kind' => 'column', 'name' => 'vecchia'], $skipped, true);
});

check('production: l\'indice non dichiarato resta, quello dichiarato e la PK si ricreano', function () use ($run) {
    [$alters, $skipped] = $run(null);

    return str_contains($alters[0], 'DROP INDEX `ind_titolo`')
        && str_contains($alters[0], 'DROP PRIMARY KEY')
        && !str_contains($alters[0], 'ind_a_mano`, ')
        && !preg_match('/DROP INDEX `ind_a_mano`/', $alters[0])
        && in_array(['table' => 'articoli', 'kind' => 'index', 'name' => 'ind_a_mano'], $skipped, true);
});

check('production: la segnalazione arriva nel risultato di Table()', function () use ($run) {
    [, , $result] = $run(null);

    return is_object($result) && count($result->skipped) === 2;
});

check('allowDestructive=true (migrazione esplicita): DROP COLUMN e DROP INDEX partono', function () use ($run) {
    [$alters, $skipped] = $run(null, true);

    return str_contains($alters[0], 'DROP COLUMN vecchia')
        && str_contains($alters[0], 'DROP INDEX `ind_a_mano`')
        && $skipped === [];
});

check('APP_ENV non valido: nel dubbio nessun DROP', function () use ($run) {
    [$alters, $skipped] = $run('boh');

    return !str_contains($alters[0], 'DROP COLUMN') && $skipped !== [];
});

check('local: comportamento invariato (DROP COLUMN emesso)', function () use ($run) {
    [$alters, $skipped] = $run('local');

    return str_contains($alters[0], 'DROP COLUMN vecchia') && $skipped === [];
});

check('staging: come production (nessun DROP COLUMN, voce segnalata)', function () use ($run) {
    [$alters, $skipped] = $run('staging');

    return !str_contains($alters[0], 'DROP COLUMN') && $skipped !== [];
});

check('production: la FK dichiarata si ricrea, quella messa a mano resta e si segnala', function () use ($run) {
    [$alters, $skipped] = $run(null, null, true);

    return str_contains($alters[0], 'DROP FOREIGN KEY `articoli_ibfk_1`')
        && !str_contains($alters[0], 'fk_a_mano')
        && in_array(['table' => 'articoli', 'kind' => 'foreign_key', 'name' => 'fk_a_mano'], $skipped, true);
});

check('local: con le FK si droppano tutte (invariato)', function () use ($run) {
    [$alters, $skipped] = $run('local', null, true);

    return str_contains($alters[0], 'DROP FOREIGN KEY `articoli_ibfk_1`')
        && str_contains($alters[0], 'DROP FOREIGN KEY `fk_a_mano`')
        && $skipped === [];
});

summary();
