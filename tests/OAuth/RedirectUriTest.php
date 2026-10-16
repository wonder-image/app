<?php
/** php tests/OAuth/RedirectUriTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\OAuth\RedirectUri;

check('https ammesso', fn () => RedirectUri::isAllowed('https://claude.ai/api/mcp/auth_callback'));
check('loopback http ammessi', fn () =>
    RedirectUri::isAllowed('http://localhost:53123/callback')
    && RedirectUri::isAllowed('http://127.0.0.1:8080/callback')
    && RedirectUri::isAllowed('http://[::1]:9000/callback'));
check('http non loopback rifiutato', fn () => !RedirectUri::isAllowed('http://evil.example/cb'));
check('fragment rifiutato', fn () => !RedirectUri::isAllowed('https://claude.ai/cb#x'));
check('userinfo rifiutato', fn () => !RedirectUri::isAllowed('https://user:pw@claude.ai/cb'));
check('schema custom rifiutato', fn () => !RedirectUri::isAllowed('myapp://cb'));
check('relativo rifiutato', fn () => !RedirectUri::isAllowed('/cb'));

check('https: confronto esatto', fn () =>
    RedirectUri::matches('https://claude.ai/api/mcp/auth_callback', 'https://claude.ai/api/mcp/auth_callback')
    && !RedirectUri::matches('https://claude.ai/api/mcp/auth_callback', 'https://claude.ai/api/mcp/auth_callback/')
    && !RedirectUri::matches('https://claude.ai/api/mcp/auth_callback', 'https://claude.ai:8443/api/mcp/auth_callback'));
check('loopback: porta ignorata', fn () => RedirectUri::matches('http://localhost:1111/callback', 'http://localhost:2222/callback'));
check('loopback: host diverso rifiutato', fn () => !RedirectUri::matches('http://localhost:1111/callback', 'http://127.0.0.1:1111/callback'));
check('loopback: path diverso rifiutato', fn () => !RedirectUri::matches('http://localhost:1111/callback', 'http://localhost:1111/other'));
check('host diverso rifiutato', fn () => !RedirectUri::matches('https://claude.ai/cb', 'https://claude.com/cb'));
check('matchesAny', fn () => RedirectUri::matchesAny(['https://a.it/cb', 'http://localhost/cb'], 'http://localhost:9/cb'));
check('host() per la UI', fn () => RedirectUri::host('https://claude.ai/api/mcp/auth_callback') === 'claude.ai');

summary();
