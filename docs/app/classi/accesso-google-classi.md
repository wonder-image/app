# Accesso con Google (Classi)

Questa guida mostra come usare le classi federate per il login Google.

## Classi coinvolte
- `Wonder\\Auth\\Federated\\GoogleIdTokenVerifierStub`
- `Wonder\\Auth\\Federated\\FederatedClaimMapper`
- `Wonder\\Auth\\Federated\\FederatedLoginService`
- `Wonder\\Auth\\Federated\\LocalPasswordPolicyService`
- `Wonder\\Auth\\Federated\\Bridge\\LegacyUserAccountGateway`
- `Wonder\\Auth\\Federated\\Bridge\\LegacySessionLoginAdapter`

## Flusso tecnico
1. Verifica ID token Google con implementazione concreta di `FederatedIdTokenVerifierInterface`.
2. Mappa i claims in `FederatedIdentityPayload` (`FederatedClaimMapper::fromGoogleClaims`).
3. Esegui linking/login con `FederatedLoginService::authenticate(...)`.
4. Se necessario, usa `LocalPasswordPolicyService` per bloccare email/password su account social senza password locale.

## Esempio d'uso (scheletro)
```php
<?php

use Wonder\Auth\Federated\FederatedClaimMapper;
use Wonder\Auth\Federated\FederatedIdentityRepository;
use Wonder\Auth\Federated\FederatedLoginService;
use Wonder\Auth\Federated\Bridge\LegacySessionLoginAdapter;
use Wonder\Auth\Federated\Bridge\LegacyUserAccountGateway;

$claims = [
    'sub' => 'google-subject-id',
    'email' => 'utente@example.com',
    'email_verified' => true,
    'given_name' => 'Mario',
    'family_name' => 'Rossi',
];

$identity = FederatedClaimMapper::fromGoogleClaims($claims);
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
    'google_verifier' => function (string $idToken): Wonder\Auth\Federated\FederatedIdentityPayload {
        // Implementazione progetto: verifica token e ritorna payload
        throw new RuntimeException('google_id_token_verification_not_implemented');
    }
]);
```

## Configurazione chiavi
Impostare in Backend > Credentials:
- `google_oauth_client_id`
- `google_oauth_client_secret`
- `google_oauth_redirect_uri`

Per recupero credenziali: [Google Sign-In OAuth](../altro/servizi/configurazione/google-sign-in-oauth.md)
