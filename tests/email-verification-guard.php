<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

// Database finto in memoria: basta a confirmUserVerificationToken().
$GLOBALS['FAKE_DB'] = [];
function fakeDbReset(): void
{
    $GLOBALS['FAKE_DB'] = [
        'user' => [ 7 => [ 'id' => 7, 'email_verified' => 0, 'email_verified_at' => null ] ],
        'consent_confirmation_tokens' => [ 1 => [
            'id' => 1, 'token_type' => 'user_email_verification', 'token' => 'tok', 'user_id' => 7,
            'metadata_json' => null, 'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'confirmed_at' => null, 'revoked_at' => null,
        ] ],
    ];
}
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

function __l() { return "it"; }
function sqlInsert($table, $values)
{
    $id = count($GLOBALS['FAKE_DB'][$table] ?? []) + 1;
    $GLOBALS['FAKE_DB'][$table][$id] = [ 'id' => $id, 'confirmed_at' => null, 'revoked_at' => null ] + $values;
    return (object) [ 'success' => true, 'insert_id' => $id ];
}

require dirname(__DIR__).'/app/function/user/email_verification.php';

$tokenConfirmed = static fn (): bool => $GLOBALS['FAKE_DB']['consent_confirmation_tokens'][1]['confirmed_at'] !== null;
$userVerified = static fn (): bool => (int) $GLOBALS['FAKE_DB']['user'][7]['email_verified'] === 1;

fakeDbReset();
$result = confirmUserVerificationToken('tok');
$check($result->success === true && $tokenConfirmed() && $userVerified(), 'Senza guardia il token non conferma l\'utente.');

fakeDbReset();
$seen = null;
$result = confirmUserVerificationToken('tok', static function (int $userId) use (&$seen): bool { $seen = $userId; return true; });
$check($seen === 7, 'La guardia non riceve l\'id dell\'utente del token.');
$check($result->success === true && $tokenConfirmed() && $userVerified(), 'Con guardia favorevole il token non conferma l\'utente.');

fakeDbReset();
$result = confirmUserVerificationToken('tok', static fn (int $userId): bool => false);
$check($result->success === false && ($result->rejected ?? false) === true, 'Una guardia che rifiuta non è segnalata come rifiuto.');
$check(!$tokenConfirmed(), 'Una guardia che rifiuta consuma comunque il token.');
$check(!$userVerified(), 'Una guardia che rifiuta segna comunque l\'email come verificata.');

fakeDbReset();
$called = false;
$GLOBALS['FAKE_DB']['consent_confirmation_tokens'][1]['confirmed_at'] = '2026-01-01 00:00:00';
$result = confirmUserVerificationToken('tok', static function (int $userId) use (&$called): bool { $called = true; return false; });
$check($result->success === false && ($result->rejected ?? false) === false && $called === false, 'Un token già usato non resta "non valido" prima della guardia.');

// C-PWD-I9: il token è salvato solo come hash, conferma atomica.
// La riga 'tok' delle fixture è in chiaro: le conferme sopra passano dal ripiego legacy.
fakeDbReset();
$issued = generateUserVerificationToken(7, 24, [ 'requested_from_url' => 'https://sito.test/registrati/' ]);
$issuedRow = $GLOBALS['FAKE_DB']['consent_confirmation_tokens'][$issued->token_id] ?? [];
$check(($issued->success ?? false) === true && preg_match('/^[0-9a-f]{64}$/', (string) $issued->token) === 1, 'Il token emesso non torna in chiaro al chiamante.');
$check(!array_key_exists('token', $issuedRow) && ($issuedRow['token_hash'] ?? '') === hash('sha256', $issued->token), 'La riga contiene il token in chiaro o un hash sbagliato.');
$check(!in_array($issued->token, array_map('strval', $issuedRow), true), 'Il token in chiaro compare in una colonna della riga.');
$check($issued->requested_from_url === 'https://sito.test/registrati/', 'L\'emissione cambia requested_from_url.');

$result = confirmUserVerificationToken($issued->token);
$check($result->success === true && $userVerified() && $issuedRow['confirmed_at'] === null, 'Un token salvato come hash non conferma l\'utente.');
$check($result->requested_from_url === 'https://sito.test/registrati/', 'La conferma cambia requested_from_url.');
$again = confirmUserVerificationToken($issued->token);
$check($again->success === false && $again->alert_code === 918, 'Una seconda conferma dello stesso token riesce.');

// Conferma concorrente fra la lettura e la scrittura: vince solo la prima.
fakeDbReset();
$issued = generateUserVerificationToken(7);
$result = confirmUserVerificationToken($issued->token, static function () use ($issued): bool {
    $GLOBALS['FAKE_DB']['consent_confirmation_tokens'][$issued->token_id]['confirmed_at'] = '2026-01-01 00:00:00';
    return true;
});
$check($result->success === false && !$userVerified(), 'Una conferma concorrente verifica l\'utente due volte.');

fakeDbReset();
$issued = generateUserVerificationToken(7);
$GLOBALS['FAKE_DB']['consent_confirmation_tokens'][$issued->token_id]['expires_at'] = date('Y-m-d H:i:s', time() - 60);
$result = confirmUserVerificationToken($issued->token);
$check($result->success === false && $result->alert_code === 919 && !$userVerified(), 'Un token scaduto non dà 919.');

fakeDbReset();
$issued = generateUserVerificationToken(7);
$GLOBALS['FAKE_DB']['consent_confirmation_tokens'][$issued->token_id]['revoked_at'] = date('Y-m-d H:i:s');
$result = confirmUserVerificationToken($issued->token);
$check($result->success === false && $result->alert_code === 918 && !$userVerified(), 'Un token revocato non dà 918.');

fakeDbReset();
$first = generateUserVerificationToken(7);
$result = confirmUserVerificationToken(hash('sha256', $first->token));
$check($result->success === false && !$userVerified(), 'L\'hash salvato vale come token.');

// Registrazione con email esistente: si riusa l'account solo per rimandare il
// link a chi non ha ancora verificato l'indirizzo.
require dirname(__DIR__).'/app/function/user/user.php';
fakeDbReset();
$check(userCanReuseForEmailVerification((object) [ 'exists' => true, 'id' => 7 ]) === true, 'Un account non verificato non riceve di nuovo il link.');
$GLOBALS['FAKE_DB']['user'][7]['email_verified'] = 1;
$check(userCanReuseForEmailVerification((object) [ 'exists' => true, 'id' => 7 ]) === false, 'Un account già verificato viene riusato da una nuova registrazione.');
$check(userCanReuseForEmailVerification((object) [ 'exists' => false ]) === false, 'Un account inesistente risulta riusabile.');

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures)."\n");
    exit(1);
}

echo "Email verification guard: OK\n";
