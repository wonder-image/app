<?php
/** php tests/Security/SecretCipherTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Credentials;
use Wonder\App\Models\Config\Security;
use Wonder\App\Security\SecretCipher;
use Wonder\App\Security\SecretCipherException;
use Wonder\App\Support\SecurityEncryptionMigration;

$old = SecretCipher::generateKey();
$new = SecretCipher::generateKey();

function throwsCipher(callable $fn): bool
{
    try {
        $fn();
    } catch (SecretCipherException) {
        return true;
    }

    return false;
}

function withEnvKeys(?string $current, ?string $previous, callable $fn): mixed
{
    $saved = [ $_ENV[SecretCipher::ENV_KEY] ?? null, $_ENV[SecretCipher::ENV_PREVIOUS_KEY] ?? null ];
    $_ENV[SecretCipher::ENV_KEY] = (string) $current;
    $_ENV[SecretCipher::ENV_PREVIOUS_KEY] = (string) $previous;
    Credentials::resetEnv();

    try {
        return $fn();
    } finally {
        foreach ([ SecretCipher::ENV_KEY, SecretCipher::ENV_PREVIOUS_KEY ] as $i => $name) {
            if ($saved[$i] === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $saved[$i];
            }
        }
        Credentials::resetEnv();
    }
}

check('generateKey: 64 caratteri esadecimali, sempre diversa', function () {
    $a = SecretCipher::generateKey();
    return preg_match('/^[0-9a-f]{64}$/', $a) === 1 && $a !== SecretCipher::generateKey();
});

check('round trip: formato enc:v1:<kid>:<payload> e testo in chiaro restituito', function () use ($new) {
    $c = new SecretCipher($new, null, true);
    $e = $c->encrypt('sk_live_123', 'security:stripe_private_key');
    return preg_match('/^enc:v1:[0-9a-f]{8}:[A-Za-z0-9_-]+$/', $e) === 1
        && SecretCipher::kidOf($e) === $c->currentKid()
        && !str_contains($e, 'sk_live_123')
        && $c->decrypt($e, 'security:stripe_private_key') === 'sk_live_123';
});

check('nonce casuale: due cifrature dello stesso valore differiscono', function () use ($new) {
    $c = new SecretCipher($new, null, true);
    return $c->encrypt('x', 'security:a') !== $c->encrypt('x', 'security:a');
});

check('stringa vuota e caratteri non ASCII fanno il round trip', function () use ($new) {
    $c = new SecretCipher($new, null, true);
    return $c->decrypt($c->encrypt('', 'ctx'), 'ctx') === ''
        && $c->decrypt($c->encrypt("pàss\n-----BEGIN KEY-----", 'ctx'), 'ctx') === "pàss\n-----BEGIN KEY-----";
});

check('valore alterato: eccezione, mai testo in chiaro', function () use ($new) {
    $c = new SecretCipher($new, null, true);
    $e = $c->encrypt('segreto', 'security:mail_password');
    $last = substr($e, -1);
    $tampered = substr($e, 0, -1).($last === 'A' ? 'B' : 'A');
    return throwsCipher(fn () => $c->decrypt($tampered, 'security:mail_password'))
        && throwsCipher(fn () => $c->decrypt(SecretCipher::PREFIX.$c->currentKid().':', 'security:mail_password'))
        && throwsCipher(fn () => $c->decrypt(SecretCipher::PREFIX.$c->currentKid().':!!', 'security:mail_password'));
});

check('contesto diverso (valore copiato in un\'altra colonna): eccezione', function () use ($new) {
    $c = new SecretCipher($new, null, true);
    $e = $c->encrypt('segreto', 'security:mail_password');
    return throwsCipher(fn () => $c->decrypt($e, 'security:brevo_api_key'));
});

check('il messaggio d\'errore non contiene valore né testo cifrato', function () use ($new, $old) {
    $e = (new SecretCipher($old, null, true))->encrypt('segretissimo', 'ctx');
    try {
        (new SecretCipher($new, null, true))->decrypt($e, 'ctx');
    } catch (SecretCipherException $x) {
        return !str_contains($x->getMessage(), 'segretissimo') && !str_contains($x->getMessage(), substr($e, 16));
    }
    return false;
});

check('rotazione: la chiave precedente decifra, needsRotation vero, la corrente cifra', function () use ($new, $old) {
    $e = (new SecretCipher($old, null, true))->encrypt('valore', 'ctx');
    $c = new SecretCipher($new, $old, true);
    $fresh = $c->encrypt('valore', 'ctx');
    return $c->knows($e) && $c->needsRotation($e) && $c->decrypt($e, 'ctx') === 'valore'
        && !$c->needsRotation($fresh) && SecretCipher::kidOf($fresh) === $c->currentKid();
});

check('chiave sconosciuta: knows falso, decrypt solleva', function () use ($new, $old) {
    $e = (new SecretCipher($old, null, true))->encrypt('valore', 'ctx');
    $c = new SecretCipher($new, null, true);
    return !$c->knows($e) && !$c->needsRotation($e) && throwsCipher(fn () => $c->decrypt($e, 'ctx'));
});

check('chiave non valida: invalidKeys la elenca e il cifrario non è disponibile', function () use ($new) {
    $c = new SecretCipher('corta', 'zz'.substr($new, 2), true);
    return !$c->available() && $c->currentKid() === null
        && $c->invalidKeys() === [ SecretCipher::ENV_KEY, SecretCipher::ENV_PREVIOUS_KEY ]
        && throwsCipher(fn () => $c->encrypt('x', 'ctx'));
});

check('chiave assente o solo spazi: non disponibile, nessuna chiave non valida', function () {
    $c = new SecretCipher('  ', null, true);
    return !$c->available() && $c->invalidKeys() === [];
});

check('chiave esadecimale maiuscola accettata con lo stesso kid', function () use ($new) {
    return (new SecretCipher(strtoupper($new), null, true))->currentKid() === (new SecretCipher($new, null, true))->currentKid();
});

check('senza sodium: non disponibile, decrypt di un cifrato solleva', function () use ($new) {
    $e = (new SecretCipher($new, null, true))->encrypt('x', 'ctx');
    $c = new SecretCipher($new, null, false);
    return !$c->available() && throwsCipher(fn () => $c->encrypt('x', 'ctx')) && throwsCipher(fn () => $c->decrypt($e, 'ctx'));
});

check('valore già cifrato: encrypt rifiuta la doppia cifratura', function () use ($new) {
    $c = new SecretCipher($new, null, true);
    return throwsCipher(fn () => $c->encrypt($c->encrypt('x', 'ctx'), 'ctx'));
});

check('decrypt di un valore in chiaro lo restituisce com\'è', function () use ($new) {
    return (new SecretCipher($new, null, true))->decrypt('in-chiaro', 'ctx') === 'in-chiaro'
        && (new SecretCipher(null, null, true))->decrypt('in-chiaro', 'ctx') === 'in-chiaro';
});

check('kidOf: null per valori in chiaro o kid malformato', function () {
    return SecretCipher::kidOf('abc') === null
        && SecretCipher::kidOf('enc:v1:XYZ:abc') === null
        && SecretCipher::kidOf('enc:v1:0123abcd:abc') === '0123abcd';
});

check('Credentials: cifra solo le colonne segrete non vuote e le decifra', function () use ($new) {
    return withEnvKeys($new, null, function () {
        $saved = Credentials::encryptSecurityValues([
            'mail_password' => ' pw ',
            'brevo_api_key' => '',
            'mail_username' => 'utente',
            'api_key' => 'pubblica',
        ]);
        $row = Credentials::decryptSecurityRow($saved);
        return SecretCipher::isEncrypted($saved['mail_password'])
            && $saved['brevo_api_key'] === ''
            && $saved['mail_username'] === 'utente'
            && $saved['api_key'] === 'pubblica'
            && $row['mail_password'] === 'pw'
            && Credentials::unreadableSecurityColumns($saved) === [];
    });
});

check('Credentials: un valore già cifrato non si ricifra', function () use ($new) {
    return withEnvKeys($new, null, function () {
        $once = Credentials::encryptSecurityValues([ 'mail_password' => 'pw' ])['mail_password'];
        return Credentials::encryptSecurityValues([ 'mail_password' => $once ])['mail_password'] === $once;
    });
});

check('Credentials senza chiave: salva in chiaro', function () {
    return withEnvKeys('', null, fn () => Credentials::encryptSecurityValues([ 'mail_password' => 'pw' ])['mail_password'] === 'pw');
});

check('Credentials: decifratura fallita = colonna vuota e segnalata come illeggibile', function () use ($new, $old) {
    $foreign = (new SecretCipher($old, null, true))->encrypt('pw', Credentials::secretContext('mail_password'));
    return withEnvKeys($new, null, function () use ($foreign) {
        $row = Credentials::decryptSecurityRow([ 'mail_password' => $foreign, 'mail_username' => 'u' ]);
        return $row['mail_password'] === '' && $row['mail_username'] === 'u'
            && Credentials::unreadableSecurityColumns([ 'mail_password' => $foreign ]) === [ 'mail_password' ];
    });
});

check('Credentials con PREVIOUS: legge i valori scritti con la chiave vecchia', function () use ($new, $old) {
    $legacy = (new SecretCipher($old, null, true))->encrypt('pw', Credentials::secretContext('mail_password'));
    return withEnvKeys($new, $old, fn () => Credentials::decryptSecurityRow([ 'mail_password' => $legacy ])['mail_password'] === 'pw');
});

check('migrazione: cifra il chiaro, ricifra la chiave precedente, lascia la corrente', function () use ($new, $old) {
    $c = new SecretCipher($new, $old, true);
    $current = $c->encrypt('corrente', Credentials::secretContext('brevo_api_key'));
    $previous = (new SecretCipher($old, null, true))->encrypt('vecchio', Credentials::secretContext('nexi_mac_key'));
    $plan = SecurityEncryptionMigration::plan([
        'mail_password' => 'pw',
        'brevo_api_key' => $current,
        'nexi_mac_key' => $previous,
        'gcp_api_key' => '',
        'api_key' => 'pubblica',
    ], $c);
    return $plan['skipped'] === false && $plan['pending'] === 1 && $plan['unreadable'] === []
        && array_keys($plan['updates']) === [ 'mail_password', 'nexi_mac_key' ]
        && !$c->needsRotation($plan['updates']['nexi_mac_key'])
        && $c->decrypt($plan['updates']['mail_password'], 'security:mail_password') === 'pw'
        && $c->decrypt($plan['updates']['nexi_mac_key'], 'security:nexi_mac_key') === 'vecchio';
});

check('migrazione idempotente: un secondo passaggio non scrive nulla', function () use ($new) {
    $c = new SecretCipher($new, null, true);
    $row = [ 'mail_password' => 'pw', 'stripe_private_key' => 'sk' ];
    $row = array_merge($row, SecurityEncryptionMigration::plan($row, $c)['updates']);
    $again = SecurityEncryptionMigration::plan($row, $c);
    return $again['updates'] === [] && $again['pending'] === 0;
});

check('migrazione: kid sconosciuto finisce in unreadable e non si tocca', function () use ($new, $old) {
    $foreign = (new SecretCipher($old, null, true))->encrypt('x', 'security:mail_password');
    $plan = SecurityEncryptionMigration::plan([ 'mail_password' => $foreign ], new SecretCipher($new, null, true));
    return $plan['updates'] === [] && $plan['unreadable'] === [ 'mail_password' => SecretCipher::kidOf($foreign) ];
});

check('migrazione: chiave precedente con contesto sbagliato finisce in unreadable', function () use ($new, $old) {
    $wrong = (new SecretCipher($old, null, true))->encrypt('x', 'security:altro');
    $plan = SecurityEncryptionMigration::plan([ 'mail_password' => $wrong ], new SecretCipher($new, $old, true));
    return $plan['updates'] === [] && array_keys($plan['unreadable']) === [ 'mail_password' ];
});

check('migrazione senza chiave: skipped, conta i valori in chiaro, nessuna scrittura', function () {
    $plan = SecurityEncryptionMigration::plan([ 'mail_password' => 'pw', 'brevo_api_key' => 'k', 'gcp_api_key' => '' ], new SecretCipher(null, null, true));
    return $plan['skipped'] === true && $plan['pending'] === 2 && $plan['updates'] === [];
});

check('migrazione senza sodium: skipped', function () use ($new) {
    return SecurityEncryptionMigration::plan([ 'mail_password' => 'pw' ], new SecretCipher($new, null, false))['skipped'] === true;
});

check('SECRET_COLUMNS: tutte colonne di security, api_key esclusa', function () {
    $schema = array_map(fn ($column) => $column->name, Security::tableSchema());
    foreach (Security::SECRET_COLUMNS as $column) {
        if (!in_array($column, $schema, true)) {
            throw new RuntimeException("colonna mancante: {$column}");
        }
    }
    return !in_array('api_key', Security::SECRET_COLUMNS, true)
        && count(array_unique(Security::SECRET_COLUMNS)) === count(Security::SECRET_COLUMNS);
});

summary();
