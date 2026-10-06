<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Wonder\Auth\Frontend\AccountPassword;

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

function __t(string $key): string { return $key; }

// Database finto in memoria: basta a AccountPassword::change().
$GLOBALS['FAKE_DB'] = [];
function sqlSelect($table, $condition = null, $limit = null)
{
    foreach ($GLOBALS['FAKE_DB'][$table] ?? [] as $row) {
        if (array_intersect_assoc((array) $condition, $row) === (array) $condition) {
            return (object) [ 'exists' => true, 'row' => $row ];
        }
    }
    return (object) [ 'exists' => false, 'row' => null ];
}
function sqlModify($table, $values, $column, $value)
{
    foreach ($GLOBALS['FAKE_DB'][$table] as $key => $row) {
        if ($row[$column] == $value) {
            $GLOBALS['FAKE_DB'][$table][$key] = array_merge($row, $values);
        }
    }
    return (object) [ 'success' => true, 'query' => '' ];
}

require dirname(__DIR__).'/app/function/string/password.php';

$reset = static function (string $password): void {
    $GLOBALS['FAKE_DB'] = [
        'user' => [ 7 => [ 'id' => 7, 'password' => $password === '' ? '' : hashPassword($password) ] ],
        'auth_remember' => [
            [ 'id' => 1, 'user_id' => 7, 'deleted' => 'false' ],
            [ 'id' => 2, 'user_id' => 8, 'deleted' => 'false' ],
        ],
    ];
};
$remembered = static fn (int $userId): bool => in_array('false', array_column(array_filter(
    $GLOBALS['FAKE_DB']['auth_remember'], static fn (array $row): bool => $row['user_id'] === $userId
), 'deleted'), true);
$stored = static fn (): string => (string) $GLOBALS['FAKE_DB']['user'][7]['password'];

// Campi: la password attuale si chiede solo se l'account ne ha una.
$check(array_keys(AccountPassword::fields(true)) === ['current_password', 'password', 'password_confirmation'], 'Il form non chiede la password attuale.');
$check(array_keys(AccountPassword::fields(false)) === ['password', 'password_confirmation'], 'Un account senza password locale deve indicare una password attuale.');

// Cambio riuscito.
$reset('vecchia-password');
$result = AccountPassword::change(7, ['current_password' => 'vecchia-password', 'password' => 'nuova-password', 'password_confirmation' => 'nuova-password']);
$check($result->success === true && $result->errors === [], 'Il cambio con dati corretti non riesce.');
$check(checkPassword('nuova-password', $stored()) && !checkPassword('vecchia-password', $stored()), 'La nuova password non viene salvata come hash.');
$check(!$remembered(7), 'Dopo il cambio password i token "ricordami" dell\'utente restano validi.');
$check($remembered(8), 'Il cambio password revoca i token "ricordami" di un altro utente.');

// Password attuale sbagliata o mancante: nessuna scrittura.
$reset('vecchia-password');
$before = $stored();
$result = AccountPassword::change(7, ['current_password' => 'sbagliata', 'password' => 'nuova-password', 'password_confirmation' => 'nuova-password']);
$check($result->success === false && ($result->errors['current_password'] ?? '') === 'wrong', 'Una password attuale sbagliata non viene segnalata.');
$check($stored() === $before, 'Con la password attuale sbagliata la password cambia comunque.');
$check($remembered(7), 'Un tentativo fallito revoca i token "ricordami".');
$result = AccountPassword::change(7, ['password' => 'nuova-password', 'password_confirmation' => 'nuova-password']);
$check(($result->errors['current_password'] ?? '') === 'required' && $stored() === $before, 'Senza password attuale la password cambia comunque.');

// Nuova password: lunghezza, conferma e differenza dall'attuale.
$result = AccountPassword::change(7, ['current_password' => 'vecchia-password', 'password' => 'corta', 'password_confirmation' => 'corta']);
$check(($result->errors['password'] ?? '') === 'too_short' && $stored() === $before, 'Una nuova password corta viene accettata.');
$result = AccountPassword::change(7, ['current_password' => 'vecchia-password', 'password' => 'nuova-password', 'password_confirmation' => 'altra-password']);
$check(($result->errors['password_confirmation'] ?? '') === 'mismatch' && $stored() === $before, 'Una conferma diversa viene accettata.');
$result = AccountPassword::change(7, ['current_password' => 'vecchia-password', 'password' => 'vecchia-password', 'password_confirmation' => 'vecchia-password']);
$check(($result->errors['password'] ?? '') === 'same' && $stored() === $before, 'La nuova password può essere uguale all\'attuale.');

// Account creato con Google, senza password locale: la imposta senza quella attuale.
$reset('');
$result = AccountPassword::change(7, ['password' => 'nuova-password', 'password_confirmation' => 'nuova-password']);
$check($result->success === true && checkPassword('nuova-password', $stored()), 'Un account senza password locale non riesce a impostarla.');

// Utente inesistente.
$result = AccountPassword::change(99, ['password' => 'nuova-password', 'password_confirmation' => 'nuova-password']);
$check($result->success === false && !isset($GLOBALS['FAKE_DB']['user'][99]), 'Un utente inesistente riceve una password.');

// Messaggi: ogni nuovo errore ha la sua chiave.
$check(\Wonder\Auth\Frontend\AuthValidationAlert::messageKeys(['current_password' => 'wrong']) === ['auth.validation.errors.current_password_wrong'], 'La password attuale sbagliata non ha un messaggio.');
$check(\Wonder\Auth\Frontend\AuthValidationAlert::messageKeys(['current_password' => 'required']) === ['auth.validation.errors.current_password_required'], 'La password attuale mancante non ha un messaggio.');
$check(\Wonder\Auth\Frontend\AuthValidationAlert::messageKeys(['password' => 'same']) === ['auth.validation.errors.password_same'], 'La password uguale all\'attuale non ha un messaggio.');

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures)."\n");
    exit(1);
}

echo "Account password: OK\n";
