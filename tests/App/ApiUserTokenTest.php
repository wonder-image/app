<?php
declare(strict_types=1);

require dirname(__DIR__).'/harness.php';
require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/app/function/sql.php';
require dirname(__DIR__, 2).'/app/function/string/common.php';
require dirname(__DIR__, 2).'/app/function/string/general.php';
require dirname(__DIR__, 2).'/app/function/string/sanitize.php';
require dirname(__DIR__, 2).'/app/function/user/api_user.php';

# Tabella api_users in memoria: il mysqli finto risponde solo alle query
# che apiUser() -> formToArray() -> unique() / sqlModify() producono.
$GLOBALS['apiUsersRows'] = [];
$GLOBALS['apiUsersQueries'] = [];

$GLOBALS['mysqli'] = new class extends mysqli {
    public function __construct() {}

    #[\ReturnTypeWillChange]
    public function real_escape_string($string) { return addslashes($string); }

    #[\ReturnTypeWillChange]
    public function query($query, $resultMode = MYSQLI_STORE_RESULT)
    {
        $GLOBALS['apiUsersQueries'][] = $query;

        if ($query === "SHOW COLUMNS FROM `api_users` LIKE 'deleted'") { return (object) ['num_rows' => 1]; }
        if (str_starts_with($query, 'UPDATE `api_users` SET ')) { return true; }

        if (!preg_match("/^SELECT \\* FROM `api_users` WHERE (?:`id` != '([^']*)' AND )?`deleted` = 'false' AND `token` = '([^']*)'$/", $query, $match)) {
            throw new RuntimeException('Query inattesa: '.$query);
        }

        $rows = array_values(array_filter($GLOBALS['apiUsersRows'], fn (array $row) =>
            ($match[1] === '' || (string) $row['id'] !== $match[1])
            && $row['deleted'] === 'false'
            && $row['token'] === $match[2]));

        return new class($rows) {
            public int $num_rows;
            public function __construct(private array $rows) { $this->num_rows = count($rows); }
            public function fetch_assoc(): ?array { return $this->rows[0] ?? null; }
            public function fetch_all(int $mode = MYSQLI_ASSOC): array { return $this->rows; }
        };
    }
};

function info($table, $column, $value)
{
    $RETURN = (object) ['exists' => false];

    foreach ($GLOBALS['apiUsersRows'] as $row) {
        if ((string) $row[$column] === (string) $value) {
            $RETURN->exists = true;
            foreach ($row as $key => $field) { $RETURN->$key = normalizeDB($field); }
            break;
        }
    }

    return $RETURN;
}

function infoUser($value, $filter = 'id')
{
    return (object) ['id' => $value, 'email' => ''];
}

Wonder\App\Table::key('api_users')->setSchema(Wonder\App\Models\User\ApiUser::rawTableSchema());

$resetTable = function (): void {
    $GLOBALS['ALERT'] = null;
    $GLOBALS['apiUsersQueries'] = [];
    $GLOBALS['apiUsersRows'] = [
        ['id' => '1', 'user_id' => '10', 'allowed_domains' => '[]', 'allowed_ips' => '[]', 'token' => 'token-a', 'active' => 'true', 'deleted' => 'false'],
        ['id' => '2', 'user_id' => '20', 'allowed_domains' => '[]', 'allowed_ips' => '[]', 'token' => 'token-b', 'active' => 'true', 'deleted' => 'false'],
    ];
};

$post = ['area' => 'api', 'authority' => 'api_internal_user', 'allowed_domains' => '[]', 'allowed_ips' => '[]', 'active' => 'true'];

$updateSet = function (): ?string {
    foreach ($GLOBALS['apiUsersQueries'] as $query) {
        if (preg_match('/^UPDATE `api_users` SET (.*) WHERE `user_id` = /', $query, $match)) { return $match[1]; }
    }
    return null;
};

check('Modifica di un utente API che riusa il proprio token: nessun falso duplicato', function () use ($resetTable, $post, $updateSet) {
    $resetTable();
    $user = (object) ['id' => 10, 'email' => '', 'api_internal_user' => info('api_users', 'user_id', 10)];
    $result = apiUser($post, [], $user, 10);
    return empty($GLOBALS['ALERT'])
        && $result->values['token'] === 'token-a'
        && str_contains((string) $updateSet(), "`token` = 'token-a'");
});

check('Modifica con la riga trovata da infoApiUser(): nessun falso duplicato', function () use ($resetTable, $post, $updateSet) {
    $resetTable();
    $result = apiUser($post, [], (object) ['id' => 10, 'email' => ''], 10);
    return empty($GLOBALS['ALERT']) && $result->values['token'] === 'token-a' && $updateSet() !== null;
});

check("L'id della riga api_users non entra nell'UPDATE né nei valori restituiti", function () use ($resetTable, $post, $updateSet) {
    $resetTable();
    $result = apiUser($post, [], (object) ['id' => 10, 'email' => ''], 10);
    $set = (string) $updateSet();
    return $set !== '' && !str_contains($set, '`id` =') && !array_key_exists('id', $result->values);
});

check("Un token già usato da un'altra riga resta un duplicato (ALERT 970)", function () use ($resetTable, $post) {
    $resetTable();
    apiUser($post + ['token' => 'token-b'], [], (object) ['id' => 10, 'email' => ''], 10);
    return $GLOBALS['ALERT'] === 970;
});

summary();
