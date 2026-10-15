<?php
/** WI_TEST_URL=https://ecommerce.test WI_TEST_MCP_USER=@mcp WI_TEST_MCP_PASSWORD=… php tests/OAuth/integration-herd.php */
declare(strict_types=1);

require __DIR__ . '/../harness.php';

$base = rtrim(getenv('WI_TEST_URL') ?: 'https://ecommerce.test', '/');
$userName = getenv('WI_TEST_MCP_USER') ?: '@mcp';
$password = getenv('WI_TEST_MCP_PASSWORD') ?: '';
if ($password === '') { echo "WI_TEST_MCP_PASSWORD non impostata: integrazione saltata.\n"; exit(0); }

$jar = tempnam(sys_get_temp_dir(), 'oauthjar');
$req = static function (string $method, string $url, array $opts = []) use ($jar): array {
    $ch = curl_init($url);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => $opts['headers'] ?? [],
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
            $p = explode(':', $line, 2);
            if (count($p) === 2) { $headers[strtolower(trim($p[0]))] = trim($p[1]); }
            return strlen($line);
        },
    ]);
    if (isset($opts['body'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']); }
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['status' => $status, 'headers' => $headers, 'body' => $body, 'json' => json_decode($body, true)];
};
$form = static fn (array $a) => ['headers' => ['Content-Type: application/x-www-form-urlencoded'], 'body' => http_build_query($a)];
// indipendente dall'ordine degli attributi: prima il tag <input> con name="_csrf", poi il suo value
$csrf = static fn (string $html) => preg_match('/<input\b[^>]*\bname=["\']_csrf["\'][^>]*>/i', $html, $tag)
    && preg_match('/\bvalue=["\']([^"\']*)["\']/i', $tag[0], $v) ? $v[1] : '';
$query = static fn (string $loc) => (static function () use ($loc) { parse_str((string) parse_url($loc, PHP_URL_QUERY), $q); return $q; })();
$pkce = static function (): array { $v = bin2hex(random_bytes(32)); return [$v, rtrim(strtr(base64_encode(hash('sha256', $v, true)), '+/', '-_'), '=')]; };
$mcp = $base.'/api/gestionale/mcp';
$rpc = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
$state = [];

check('401 senza token con resource_metadata', function () use ($req, $mcp, $rpc, &$state) {
    $r = $req('POST', $mcp.'/', ['headers' => ['Content-Type: application/json'], 'body' => $rpc]);
    if (!preg_match('/resource_metadata="([^"]+)"/', $r['headers']['www-authenticate'] ?? '', $m)) { return false; }
    $state['prm'] = $m[1];
    return $r['status'] === 401;
});
check('PRM', function () use ($req, $mcp, &$state) {
    $r = $req('GET', $state['prm']);
    $state['as'] = $r['json']['authorization_servers'][0] ?? '';
    return $r['status'] === 200 && ($r['json']['resource'] ?? '') === $mcp;
});
check('AS metadata senza slash, nessun 301', function () use ($req, &$state) {
    $r = $req('GET', $state['as'].'/.well-known/oauth-authorization-server');
    return $r['status'] === 200 && ($r['json']['code_challenge_methods_supported'] ?? []) === ['S256'];
});
check('register', function () use ($req, $base, &$state) {
    $r = $req('POST', $base.'/oauth/register', ['headers' => ['Content-Type: application/json'], 'body' => json_encode(['client_name' => 'integration', 'redirect_uris' => ['http://localhost:9/callback']])]);
    $state['client'] = $r['json']['client_id'] ?? '';
    return $r['status'] === 201 && $state['client'] !== '';
});

$authorize = function () use ($req, $base, $mcp, $pkce, $query, $csrf, $form, $userName, $password, &$state): array {
    [$v, $c] = $pkce();
    $r = $req('GET', $base.'/oauth/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => $state['client'], 'redirect_uri' => 'http://localhost:9/callback',
        'code_challenge' => $c, 'code_challenge_method' => 'S256', 'resource' => $mcp, 'state' => 's1']));
    $loc = $r['headers']['location'] ?? '';
    if (str_contains($loc, '/oauth/login')) {
        $page = $req('GET', $loc);
        $r = $req('POST', $loc, $form(['_csrf' => $csrf($page['body']), 'username' => $userName, 'password' => $password]));
        $loc = $r['headers']['location'] ?? '';
    }
    $page = $req('GET', $loc);
    $r = $req('POST', $loc, $form(['_csrf' => $csrf($page['body']), 'action' => 'approve']));
    $q = $query($r['headers']['location'] ?? '');
    return ['code' => $q['code'] ?? '', 'verifier' => $v, 'iss' => $q['iss'] ?? '', 'state' => $q['state'] ?? ''];
};

check('due authorize paralleli: entrambi i codici validi', function () use ($authorize, $req, $base, $form, $mcp, &$state) {
    $a = $authorize();
    $b = $authorize();
    $ta = $req('POST', $base.'/oauth/token', $form(['grant_type' => 'authorization_code', 'code' => $a['code'], 'redirect_uri' => 'http://localhost:9/callback', 'client_id' => $state['client'], 'code_verifier' => $a['verifier'], 'resource' => $mcp]));
    $tb = $req('POST', $base.'/oauth/token', $form(['grant_type' => 'authorization_code', 'code' => $b['code'], 'redirect_uri' => 'http://localhost:9/callback', 'client_id' => $state['client'], 'code_verifier' => $b['verifier']]));
    $state['tokens'] = $ta['json'] ?? [];
    $state['tokens_b'] = $tb['json'] ?? [];
    if ($ta['status'] !== 200 || $tb['status'] !== 200) { echo "    token: {$ta['status']} ".($ta['json']['error'] ?? '')." / {$tb['status']} ".($tb['json']['error'] ?? '')."\n"; }
    return $ta['status'] === 200 && $tb['status'] === 200 && $a['iss'] === $base && $a['state'] === 's1';
});
check('tools/list con access token', function () use ($req, $mcp, $rpc, &$state) {
    $r = $req('POST', $mcp.'/', ['headers' => ['Content-Type: application/json', 'Authorization: Bearer '.($state['tokens']['access_token'] ?? '')], 'body' => $rpc]);
    return $r['status'] === 200 && isset($r['json']['result']['tools']);
});
check('refresh ruota; riuso → invalid_grant', function () use ($req, $base, $form, &$state) {
    $old = $state['tokens']['refresh_token'] ?? '';
    $n = $req('POST', $base.'/oauth/token', $form(['grant_type' => 'refresh_token', 'refresh_token' => $old, 'client_id' => $state['client']]));
    $again = $req('POST', $base.'/oauth/token', $form(['grant_type' => 'refresh_token', 'refresh_token' => $old, 'client_id' => $state['client']]));
    return $n['status'] === 200 && $again['status'] === 400 && ($again['json']['error'] ?? '') === 'invalid_grant';
});
check('dopo il riuso il grant è revocato: access → 401', function () use ($req, $mcp, $rpc, &$state) {
    $r = $req('POST', $mcp.'/', ['headers' => ['Content-Type: application/json', 'Authorization: Bearer '.($state['tokens']['access_token'] ?? '')], 'body' => $rpc]);
    return $r['status'] === 401 && str_contains($r['headers']['www-authenticate'] ?? '', 'invalid_token');
});

check('revoke del secondo grant: access → 401 invalid_token', function () use ($req, $base, $form, $mcp, $rpc, &$state) {
    $rv = $req('POST', $base.'/oauth/revoke', $form(['token' => $state['tokens_b']['refresh_token'] ?? '', 'client_id' => $state['client']]));
    $r = $req('POST', $mcp.'/', ['headers' => ['Content-Type: application/json', 'Authorization: Bearer '.($state['tokens_b']['access_token'] ?? '')], 'body' => $rpc]);
    if ($rv['status'] !== 200) { echo "    revoke: {$rv['status']} ".($rv['json']['error'] ?? '')."\n"; }
    return $rv['status'] === 200 && $r['status'] === 401 && str_contains($r['headers']['www-authenticate'] ?? '', 'invalid_token');
});

@unlink($jar);
summary();
