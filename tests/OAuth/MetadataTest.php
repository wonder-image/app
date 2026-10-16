<?php
/** php tests/OAuth/MetadataTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\OAuth\Metadata;
use Wonder\OAuth\OAuth;
use Wonder\OAuth\ResourceRegistry;

$_ENV['APP_URL'] = 'https://sito.it';
OAuth::reset();

check('senza risorse OAuth è spento', fn () => OAuth::enabled() === false);

OAuth::resource('/api/gestionale/mcp/', 'Gestionale');

check('registrare una risorsa lo accende', fn () => OAuth::enabled() === true);

check('URL canonico senza slash finale', function () {
    $r = ResourceRegistry::all()[0];
    return $r['path'] === '/api/gestionale/mcp'
        && $r['url'] === 'https://sito.it/api/gestionale/mcp'
        && $r['metadata_url'] === 'https://sito.it/.well-known/oauth-protected-resource/api/gestionale/mcp';
});

check('findByUrl accetta lo slash finale', fn () =>
    ResourceRegistry::findByUrl('https://sito.it/api/gestionale/mcp/')['name'] === 'Gestionale'
    && ResourceRegistry::findByUrl('https://altro.it/api/gestionale/mcp') === null);

check('PRM', fn () => Metadata::protectedResource(ResourceRegistry::all()[0]) === [
    'resource' => 'https://sito.it/api/gestionale/mcp',
    'authorization_servers' => ['https://sito.it'],
    'bearer_methods_supported' => ['header'],
    'resource_name' => 'Gestionale',
]);

check('AS metadata', function () {
    $m = Metadata::authorizationServer();
    return $m['issuer'] === 'https://sito.it'
        && $m['authorization_endpoint'] === 'https://sito.it/oauth/authorize'
        && $m['token_endpoint'] === 'https://sito.it/oauth/token'
        && $m['registration_endpoint'] === 'https://sito.it/oauth/register'
        && $m['revocation_endpoint'] === 'https://sito.it/oauth/revoke'
        && $m['code_challenge_methods_supported'] === ['S256']
        && $m['token_endpoint_auth_methods_supported'] === ['none']
        && $m['grant_types_supported'] === ['authorization_code', 'refresh_token']
        && $m['scopes_supported'] === [];
});

// Review Focus 3: l'header Host non influenza nulla.
check('issuer da APP_URL anche con Host falsificato', function () {
    $_SERVER['HTTP_HOST'] = 'evil.example';
    $ok = Metadata::authorizationServer()['issuer'] === 'https://sito.it'
        && Metadata::protectedResource(ResourceRegistry::all()[0])['resource'] === 'https://sito.it/api/gestionale/mcp';
    unset($_SERVER['HTTP_HOST']);
    return $ok;
});

check('WWW-Authenticate senza errore', fn () =>
    Metadata::wwwAuthenticate('/api/gestionale/mcp') === 'Bearer resource_metadata="https://sito.it/.well-known/oauth-protected-resource/api/gestionale/mcp"');

check('WWW-Authenticate con invalid_token', fn () =>
    Metadata::wwwAuthenticate('/api/gestionale/mcp', 'invalid_token', 'expired') ===
    'Bearer resource_metadata="https://sito.it/.well-known/oauth-protected-resource/api/gestionale/mcp", error="invalid_token", error_description="expired"');

check('resourceMetadataUrl dalla facade', fn () =>
    OAuth::resourceMetadataUrl('/api/gestionale/mcp/') === 'https://sito.it/.well-known/oauth-protected-resource/api/gestionale/mcp');

summary();
