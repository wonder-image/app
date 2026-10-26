<?php
/** php tests/Backend/Table/BackendInputValidationTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Table\TableAuthorization;

$registry = [
    'category' => ['name' => ['input' => []], 'icon' => ['input' => []]],
    'news' => ['title' => ['input' => []]],
];

check('iconTable: solo tabella registrata con colonna icon', function () use ($registry) {
    return TableAuthorization::iconTable('category', $registry) === 'category'
        && TableAuthorization::iconTable('CATEGORY', $registry) === 'category'
        && TableAuthorization::iconTable('news', $registry) === null
        && TableAuthorization::iconTable('user', $registry) === null
        && TableAuthorization::iconTable("category`;--", $registry) === null
        && TableAuthorization::iconTable(['category'], $registry) === null;
});

check('uploadFolder: rifiuta la risalita', function () {
    return TableAuthorization::uploadFolder('/category/icon/') === 'category/icon'
        && TableAuthorization::uploadFolder('../../config') === null;
});

check('M9: symlink interno alla root: si cancella il link, non il file a cui punta', function () {
    $base = sys_get_temp_dir() . '/wi-icon-' . bin2hex(random_bytes(4));
    mkdir($base . '/upload/category', 0777, true);
    mkdir($base . '/upload/other');
    file_put_contents($base . '/upload/other/b.svg', 'x');
    symlink($base . '/upload/other/b.svg', $base . '/upload/category/alias.svg');
    try {
        $root = $base . '/upload';
        $path = TableAuthorization::uploadFile($root, 'category', 'alias.svg');
        $ok = $path === realpath($root) . '/category/alias.svg' && is_link((string) $path);
        if ($path !== null) {
            @unlink($path);
        }
        clearstatcache();
        $ok = $ok && is_file($base . '/upload/other/b.svg') && !is_link($base . '/upload/category/alias.svg');
    } finally {
        exec('rm -rf ' . escapeshellarg($base));
    }
    return $ok;
});

check('uploadFile: file sotto upload sì; risalita, symlink esterni e cartelle no', function () {
    $base = sys_get_temp_dir() . '/wi-icon-' . bin2hex(random_bytes(4));
    mkdir($base . '/upload/category', 0777, true);
    mkdir($base . '/outside');
    file_put_contents($base . '/upload/category/a.svg', 'x');
    file_put_contents($base . '/outside/secret.txt', 'x');
    symlink($base . '/outside/secret.txt', $base . '/upload/category/link.svg');
    try {
        $root = $base . '/upload';
        $ok = TableAuthorization::uploadFile($root, 'category', 'a.svg') === realpath($root . '/category/a.svg')
            // Il nome in colonna vale solo come basename.
            && TableAuthorization::uploadFile($root, 'category', '../../outside/secret.txt') === null
            && TableAuthorization::uploadFile($root, 'category', 'link.svg') === null
            && TableAuthorization::uploadFile($root, 'category', '..') === null
            && TableAuthorization::uploadFile($root, '', 'category') === null
            && TableAuthorization::uploadFile($root . '/missing', 'category', 'a.svg') === null;
    } finally {
        exec('rm -rf ' . escapeshellarg($base));
    }
    return $ok;
});

/** selectDatabase esce con 422: si esegue in un sottoprocesso con sqlDatabase() finto. */
function selectDatabaseIn(string $database): string {
    $autoload = escapeshellarg(realpath(__DIR__ . '/../../../vendor/autoload.php'));
    $code = 'require ' . $autoload . ';'
        . 'function sqlDatabase($d) { echo "SELECTED:" . $d; }'
        . '$GLOBALS["DB"] = (object) ["database" => ["main" => "db_main", "shop" => "db_shop", "information_schema" => "INFORMATION_SCHEMA"]];'
        . 'echo \Wonder\App\Support\ApiRequest::selectDatabase(' . var_export($database, true) . ');';
    return (string) shell_exec('php -d display_errors=0 -r ' . escapeshellarg($code) . ' 2>/dev/null');
}

check('selectDatabase: main e alias configurati accettati', function () {
    return selectDatabaseIn('main') === 'main'
        && selectDatabaseIn('') === 'main'
        && selectDatabaseIn('shop') === 'SELECTED:shopshop';
});

// Regressione: Field::ajaxRequest() invia il nome reale (SELECT DATABASE()), non l'alias.
check('selectDatabase: nome reale di un database configurato => suo alias', function () {
    return selectDatabaseIn('db_main') === 'main'
        && selectDatabaseIn('db_shop') === 'SELECTED:shopshop';
});

check('selectDatabase: alias sconosciuto o information_schema => 422 senza selezione', function () {
    $expected = '{"success":false,"status":422,"response":"Parametri non validi."}';
    return selectDatabaseIn('db_other') === $expected
        && selectDatabaseIn('DB_SHOP') === $expected
        && selectDatabaseIn('information_schema') === $expected
        && selectDatabaseIn('INFORMATION_SCHEMA') === $expected
        && selectDatabaseIn('mysql') === $expected;
});

summary();
