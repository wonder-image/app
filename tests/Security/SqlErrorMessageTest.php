<?php
/** php tests/Security/SqlErrorMessageTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Sql\Utility\Error;
use Wonder\Sql\Utility\QueryException;

$fakeMysqli = new class {
    public int $errno = 1146;
    public string $error = "Table 'db.missing' doesn't exist";
};

function sqlError(object $mysqli): QueryException {
    try {
        new Error('Select', 'missing', "SELECT * FROM missing WHERE email = 'mario@example.com'", $mysqli);
    } catch (QueryException $e) {
        return $e;
    }
    throw new RuntimeException('Error non ha lanciato QueryException');
}

check('il messaggio non contiene la query', function () use ($fakeMysqli) {
    $m = sqlError($fakeMysqli)->getMessage();
    return !str_contains($m, 'Query:') && !str_contains($m, 'mario@example.com') && !str_contains($m, 'WHERE');
});

check('il messaggio conserva azione, tabella, numero e testo dell\'errore', function () use ($fakeMysqli) {
    $m = sqlError($fakeMysqli)->getMessage();
    return str_contains($m, 'Action: Select') && str_contains($m, 'Table: missing')
        && str_contains($m, 'Error N°1146') && str_contains($m, "doesn't exist");
});

check('la query resta disponibile per il log; resta un Exception (catch esistenti invariati)', function () use ($fakeMysqli) {
    $e = sqlError($fakeMysqli);
    return $e instanceof Exception && str_contains($e->query(), 'mario@example.com')
        && $e->errno() === 1146 && $e->table() === 'missing' && $e->action() === 'Select';
});

check('Logger scrive la query nel contesto del log', function () use ($fakeMysqli) {
    $dir = sys_get_temp_dir() . '/wi-sql-log-' . bin2hex(random_bytes(4));
    mkdir($dir);
    $prev = getcwd();
    chdir($dir);
    $GLOBALS['ROOT'] = $dir;
    try {
        \Wonder\App\Logger::log(sqlError($fakeMysqli), 'test', 'sql', 'ERROR', 'error/sql', [], false);
        $line = json_decode(trim((string) @file_get_contents($dir . '/storage/logs/error/sql.log')), true);
    } finally {
        unset($GLOBALS['ROOT']);
        chdir((string) $prev);
        exec('rm -rf ' . escapeshellarg($dir));
    }
    return is_array($line) && str_contains((string) ($line['context']['query'] ?? ''), 'FROM missing')
        && !str_contains((string) $line['message'], 'FROM missing');
});

summary();
