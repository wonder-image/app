<?php
/** php tests/Security/ListTableGuardTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Backend\Table\ListTableRequest;

$registry = ['users' => [], 'gst_product_models' => [], 'resource_page' => []];
$handler = realpath(__DIR__.'/../../app/http/api/backend/list-table.php');

/**
 * Esegue l'handler in un processo separato con connessione finta:
 * stampa lo status HTTP, l'output e le chiavi chieste al pool.
 */
$runHandler = static function (array $post) use ($handler): string {
    $autoload = var_export(__DIR__.'/../../vendor/autoload.php', true);
    $file = var_export($handler, true);
    $postExport = var_export($post, true);
    $script = tempnam(sys_get_temp_dir(), 'wi-list-table-');

    if (!is_string($script)) {
        return '';
    }

    $source = <<<PHP
<?php
require {$autoload};
\Wonder\App\Table::\$list = ['users' => []];
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST = {$postExport};
\$GLOBALS['wiPoolKeys'] = [];
\$MYSQLI_CONNECTION = new class implements ArrayAccess {
    public function offsetExists(mixed \$offset): bool { return true; }
    public function offsetGet(mixed \$offset): mixed {
        \$GLOBALS['wiPoolKeys'][] = \$offset;
        exit;
    }
    public function offsetSet(mixed \$offset, mixed \$value): void {}
    public function offsetUnset(mixed \$offset): void {}
};
register_shutdown_function(function (): void {
    echo '|status='.http_response_code().'|pool='.implode(',', \$GLOBALS['wiPoolKeys']);
});
require {$file};
PHP;

    file_put_contents($script, $source);
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1', $output);
    unlink($script);

    return implode("\n", $output);
};

check('tabella registrata: accettata anche con maiuscole', function () use ($registry) {
    return ListTableRequest::table('users', $registry) === 'users'
        && ListTableRequest::table('USERS', $registry) === 'USERS'
        && ListTableRequest::table('gst_product_models', $registry) === 'gst_product_models';
});

check('tabella fuori da Table::$list: rifiutata', function () use ($registry) {
    return ListTableRequest::table('mysql.user', $registry) === null
        && ListTableRequest::table('users` UNION SELECT 1 -- ', $registry) === null
        && ListTableRequest::table('', $registry) === null
        && ListTableRequest::table(null, $registry) === null
        && ListTableRequest::table(['users'], $registry) === null;
});

check('schema sconosciuto ignorato, schema registrato conservato', function () use ($registry) {
    return ListTableRequest::schema('resource_page', $registry) === 'resource_page'
        && ListTableRequest::schema('non_esiste', $registry) === ''
        && ListTableRequest::schema(['x'], $registry) === ''
        && ListTableRequest::schema(null, $registry) === '';
});

check('registro di default: Table::$list', function () {
    $previous = \Wonder\App\Table::$list;
    \Wonder\App\Table::$list = ['orders' => []];
    $ok = ListTableRequest::table('orders') === 'orders' && ListTableRequest::table('users') === null;
    \Wonder\App\Table::$list = $previous;

    return $ok;
});

check('handler: tabella non registrata risponde 422 senza toccare connessioni', function () use ($runHandler) {
    $output = $runHandler(['config' => ['table' => 'mysql.user', 'database' => 'mysql']]);

    return str_contains($output, '"status":422')
        && str_contains($output, '|status=422|pool=')
        && !preg_match('/pool=\S/', $output);
});

check('handler: con tabella valida usa sempre la connessione main', function () use ($runHandler) {
    $output = $runHandler(['config' => ['table' => 'users', 'database' => 'altro_db']]);

    return str_ends_with($output, '|pool=main');
});

check('handler: database, area e autorità utente non arrivano dal POST', function () use ($handler) {
    $source = (string) file_get_contents($handler);

    return !str_contains($source, "['config']['database']")
        && !str_contains($source, 'user_area')
        && !str_contains($source, 'user_authority')
        && !str_contains($source, '$MYSQLI_CONNECTION[$name->database]');
});

check('handler: allow-list prima di connessione e campi', function () use ($handler) {
    $source = (string) file_get_contents($handler);
    $guard = strpos($source, 'ListTableRequest::table(');
    $connection = strpos($source, '$MYSQLI_CONNECTION');
    $fields = strpos($source, 'ListProvider::fields(');

    return $guard !== false && $connection !== false && $fields !== false
        && $guard < $connection && $guard < $fields;
});

summary();
