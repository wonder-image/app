# Accesso con Apple (Classi)

Questa guida mostra come usare le classi federate per il login Apple.

## Classi coinvolte
- `Wonder\\Auth\\Federated\\AppleIdTokenVerifierStub`
- `Wonder\\Auth\\Federated\\FederatedClaimMapper`
- `Wonder\\Auth\\Federated\\FederatedLoginService`
- `Wonder\\Auth\\Federated\\LocalPasswordPolicyService`
- `Wonder\\Auth\\Federated\\Bridge\\LegacyUserAccountGateway`
- `Wonder\\Auth\\Federated\\Bridge\\LegacySessionLoginAdapter`

## Flusso tecnico
1. Verifica ID token Apple con implementazione concreta di `FederatedIdTokenVerifierInterface`.
2. Mappa i claims in `FederatedIdentityPayload` (`FederatedClaimMapper::fromAppleClaims`).
3. Esegui linking/login con `FederatedLoginService::authenticate(...)`.
4. Se necessario, usa `LocalPasswordPolicyService` per gestire set password su account nati social.

## Esempio d'uso (scheletro)
```php
<?php

use Wonder\Auth\Federated\FederatedClaimMapper;
use Wonder\Auth\Federated\FederatedIdentityRepository;
use Wonder\Auth\Federated\FederatedLoginService;
use Wonder\Auth\Federated\Bridge\LegacySessionLoginAdapter;
use Wonder\Auth\Federated\Bridge\LegacyUserAccountGateway;

$claims = [
    'sub' => 'apple-subject-id',
    'email' => 'utente@example.com',
    'email_verified' => 'true',
    'given_name' => 'Mario',
    'family_name' => 'Rossi',
];

$identity = FederatedClaimMapper::fromAppleClaims($claims);
$repo = new FederatedIdentityRepository('auth_federated');
$users = new LegacyUserAccountGateway('client');
$session = new LegacySessionLoginAdapter(true);

$service = new FederatedLoginService($users, $repo, $session);
$result = $service->authenticate($identity, 'frontend', [ 'client' ]);

if ($result->success) {
    // login ok
}
```

## Helper frontend semplificati
Render bottoni (unica chiamata):
```php
<?= inputFederatedLoginButtons([
    'google_url' => '/account/auth/google/start/',
    'apple_url' => '/account/auth/apple/start/'
]); ?>
```

Verifica callback (stile `verifyRecaptcha()`):
```php
$ok = verifyFederatedLogin($_POST, 'frontend', [ 'client' ], [
    'default_authority' => 'client',
    'alert_map' => [
        'local_password_missing_for_federated_account' => 924,
        'invalid_federated_request' => 900,
    ],
    'apple_verifier' => function (string $idToken): Wonder\Auth\Federated\FederatedIdentityPayload {
        // Implementazione progetto: verifica token e ritorna payload
        throw new RuntimeException('apple_id_token_verification_not_implemented');
    }
]);
```

## Configurazione chiavi
Impostare in Backend > Credentials:
- `apple_oauth_client_id`
- `apple_oauth_team_id`
- `apple_oauth_key_id`
- `apple_oauth_private_key`
- `apple_oauth_redirect_uri`

Per recupero credenziali: [Sign in with Apple](../altro/servizi/configurazione/sign-in-with-apple.md)
