<?php
/**
 * Standalone test (no phpunit in repo):
 *   php tests/Security/ListProviderSignatureTest.php
 *
 * ListProvider::fetch() must reject a request whose signed config values
 * (query / query_filter / query_custom / search_columns) fail HMAC
 * verification, BEFORE it touches the database. This proves a tampered
 * client cannot smuggle raw SQL into the query builder.
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Wonder\Backend\Table\ListProvider;
use Wonder\Backend\Table\ConfigCodec;

// ConfigCodec::decode() falls back to Credentials::appKey() when no key is
// passed; provide one so the signing key is deterministic in the test.
$_ENV['APP_KEY'] = 'test-app-key-0123456789abcdef';

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

$name = (object) ['table' => 'users', 'database' => 'main'];
$stub = (object) [];

// Attacker crafts an unsigned query_filter carrying raw SQL. fetch() must
// return a DataTables error response without ever running a query.
$tampered = [
    'draw'   => 7,
    'fields' => [],
    'config' => [
        'query'          => ConfigCodec::encode(''),                                   // valid
        'query_filter'   => base64_encode("1=1 UNION SELECT password FROM admin-- "),  // FORGED
        'query_custom'   => ConfigCodec::encode(''),                                   // valid
        'search_columns' => ConfigCodec::encode(json_encode([])),                      // valid
    ],
];

try {
    $result = ListProvider::fetch($tampered, $name, $stub, $stub, $stub, null);
    check('tampered request rejected (draw preserved)', $result['draw'] ?? null, 7);
    check('tampered request rejected (no rows)', $result['data'] ?? null, []);
    check('tampered request flagged as error', isset($result['error']), true);
} catch (\Throwable $e) {
    // Reaching the DB / throwing means the reject guard is missing.
    $fail++;
    echo "FAIL: tampered request must be rejected before DB access\n  threw: " . $e->getMessage() . "\n";
}

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
