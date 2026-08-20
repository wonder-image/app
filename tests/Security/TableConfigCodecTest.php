<?php
/**
 * Standalone test (no phpunit in repo):
 *   php tests/Security/TableConfigCodecTest.php
 *
 * ConfigCodec signs the server-generated DataTables config values (raw SQL
 * fragments: query / query_filter / query_custom / search_columns) that are
 * round-tripped through the untrusted client. A tampered value must fail
 * verification so it can never be used to inject SQL.
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Wonder\Backend\Table\ConfigCodec;

$fail = 0;
function check(string $label, $got, $expected) {
    global $fail;
    if ($got !== $expected) {
        $fail++;
        echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  got:      " . var_export($got, true) . "\n";
    } else {
        echo "ok: $label\n";
    }
}

$key = 'test-app-key-0123456789abcdef';

// 1) round-trip: a signed payload decodes back to the exact same string.
$payload = "visible = '1' AND category_id = '7'";
$encoded = ConfigCodec::encode($payload, $key);
check('round-trip non-empty', ConfigCodec::decode($encoded, $key), $payload);

// 2) an empty payload is still a valid signed blob (legit "no filter" case),
//    and decodes back to '' (NOT null).
$encodedEmpty = ConfigCodec::encode('', $key);
check('round-trip empty -> empty string', ConfigCodec::decode($encodedEmpty, $key), '');

// 3) tampering with the payload invalidates the signature -> null.
$rawTampered = base64_encode(hash_hmac('sha256', $payload, $key) . '.' . $payload . " OR 1=1");
check('tampered payload rejected', ConfigCodec::decode($rawTampered, $key), null);

// 4) a totally attacker-crafted value (no valid signature) -> null.
check(
    'unsigned attacker value rejected',
    ConfigCodec::decode(base64_encode("1=1 UNION SELECT password FROM admin-- "), $key),
    null
);

// 5) empty transmitted string (missing / stripped) -> null (reject, not '').
check('empty transmitted string rejected', ConfigCodec::decode('', $key), null);

// 6) invalid base64 -> null.
check('invalid base64 rejected', ConfigCodec::decode('!!!not base64!!!', $key), null);

// 7) a signature made with a different key does not verify.
$foreign = ConfigCodec::encode($payload, 'a-different-key');
check('foreign-key signature rejected', ConfigCodec::decode($foreign, $key), null);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
