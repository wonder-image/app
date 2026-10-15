<?php
/** php tests/OAuth/HtaccessTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

$sources = [
    'Build.php' => (string) file_get_contents(__DIR__.'/../../class/Console/Commands/Build.php'),
    'configuration_file.php' => (string) file_get_contents(__DIR__.'/../../app/build/update/configuration_file.php'),
];

// Valore a runtime di un frammento di stringa PHP (heredoc o doppi apici) per gli escape in gioco.
$unescape = static fn (string $raw): string => strtr($raw, ['\\\\' => '\\', '\\$' => '$']);

foreach ($sources as $name => $src) {
    preg_match('#RewriteCond %\{REQUEST_URI\} (\^/\(backend\|api\S*) \[NC\]#', $src, $m);
    $pattern = isset($m[1]) ? $unescape($m[1]) : null;
    $hit = static fn (string $uri): bool => $pattern !== null && preg_match('#'.$pattern.'#i', $uri) === 1;

    check("$name: regola router trovata", fn () => $pattern !== null);
    check("$name: pattern de-escapato è una regex valida", fn () => $pattern !== null && @preg_match('#'.$pattern.'#', '') !== false);
    check("$name: /oauth/token senza slash va all'handler", fn () => $hit('/oauth/token'));
    check("$name: /oauth/authorize va all'handler", fn () => $hit('/oauth/authorize'));
    check("$name: .well-known oauth va all'handler", fn () =>
        $hit('/.well-known/oauth-authorization-server') && $hit('/.well-known/oauth-protected-resource/api/gestionale/mcp'));
    check("$name: il punto di .well-known è letterale", fn () => !$hit('/xwell-known/oauth-authorization-server'));
    check("$name: ACME e apple-pay restano file statici", fn () =>
        !$hit('/.well-known/acme-challenge/abc') && !$hit('/.well-known/apple-developer-merchantid-domain-association'));
    check("$name: backend e api invariati", fn () => $hit('/backend/') && $hit('/api/states/'));
    check("$name: /oauthfoo non catturato", fn () => !$hit('/oauthfoo'));
}

summary();
