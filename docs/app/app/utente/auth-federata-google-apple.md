# Modulo Auth Federata (Standalone)

## Obiettivo
Implementare in modo standalone:
- Google Sign-In
- Sign in with Apple
- mapping account federato -> utente interno
- policy di linking account
- gestione `set password` per account nati social

Vincoli rispettati:
- nessuna integrazione attiva sui flussi di produzione correnti
- nessuna tabella cliente/fatturazione/spedizione
- nessun token provider salvato in chiaro
- mapping reale basato su `provider + provider_user_id`

## Posizionamento nel progetto
- Schema tabella runtime: `app/build/table/user.php` -> `$TABLE->AUTH_FEDERATED`
- Configurazione chiavi backend: `app/build/src/backend/app/config/credentials/index.php`
- Lettura chiavi applicative: `class/App/Credentials.php` -> `Credentials::api()`

Nota:
- `AUTH_FEDERATED` viene creata dal sistema tabelle del framework (`$TABLE->...`), non tramite SQL manuale.

## Artefatti creati

### Classi core
- `Wonder\Auth\Federated\FederatedProvider`
- `Wonder\Auth\Federated\FederatedIdentityPayload`
- `Wonder\Auth\Federated\FederatedIdentityRepository`
- `Wonder\Auth\Federated\FederatedLoginService`
- `Wonder\Auth\Federated\LocalPasswordPolicyService`
- `Wonder\Auth\Federated\FederatedClaimMapper`
- `Wonder\Auth\Federated\GoogleIdTokenVerifierStub`
- `Wonder\Auth\Federated\AppleIdTokenVerifierStub`
- `Wonder\Auth\Federated\FederatedAuthResult`
- `Wonder\Auth\Federated\FederatedExtensionPipeline`
- `Wonder\Auth\Federated\FederatedOnboardingContext`
- `Wonder\Auth\Federated\FederatedValidationResult`

### Contratti estensione
- `Wonder\Auth\Federated\Contract\UserAccountGatewayInterface`
- `Wonder\Auth\Federated\Contract\FederatedLoginSessionInterface`
- `Wonder\Auth\Federated\Contract\FederatedIdTokenVerifierInterface`
- `Wonder\Auth\Federated\Contract\AdditionalProfileDataHandlerInterface`
- `Wonder\Auth\Federated\Contract\PostFederatedLoginHookInterface`
- `Wonder\Auth\Federated\Contract\FederatedOnboardingDataResolverInterface`

### Adapter legacy opzionali (non attivati)
- `Wonder\Auth\Federated\Bridge\LegacyUserAccountGateway`
- `Wonder\Auth\Federated\Bridge\LegacySessionLoginAdapter`

### Note sicurezza
- In tabella non sono previsti access token/refresh token in chiaro.
- Se un progetto deve salvare token provider, usare storage separato cifrato o solo hash/opaque reference.

## Chiavi richieste (Security/Credentials)
Google:
- `google_oauth_client_id`
- `google_oauth_client_secret`
- `google_oauth_redirect_uri`

Apple:
- `apple_oauth_client_id`
- `apple_oauth_team_id`
- `apple_oauth_key_id`
- `apple_oauth_private_key`
- `apple_oauth_redirect_uri`

Guide setup:
- [Google Sign-In OAuth](../../altro/servizi/configurazione/google-sign-in-oauth.md)
- [Sign in with Apple](../../altro/servizi/configurazione/sign-in-with-apple.md)

Guide tecniche classi:
- [Accesso con Google (Classi)](../../classi/accesso-google-classi.md)
- [Accesso con Apple (Classi)](../../classi/accesso-apple-classi.md)

## Policy Flussi Auth

### 1) `social -> social già collegato`
1. Lookup su `provider + provider_user_id`.
2. Se trovato, login dell’utente collegato.
3. Aggiornamento `last_login_at` + metadata tecnico.

Output atteso:
- `success = true`
- `status = login_success_existing_link`

### 2) `social -> email match`
1. Nessun record in `AUTH_FEDERATED` con `provider + provider_user_id`.
2. Esiste utente locale con stessa email.
3. Creazione link federato verso quell’utente.
4. Login.

Output atteso:
- `success = true`
- `status = login_success_linked_existing_email`

### 3) `social -> nuovo utente`
1. Nessun record federato esistente.
2. Nessun utente locale con quella email.
3. Creazione nuovo utente locale.
4. Creazione record federato.
5. Login.

Output atteso:
- `success = true`
- `status = login_success_created_user`

### 4) `email/password` su account nato social senza password
1. Lookup utente per email.
2. Se l’utente non ha password locale e ha almeno un link in `AUTH_FEDERATED`:
   - blocco login classico
   - motivo esplicito `local_password_missing_for_federated_account`
   - redirect a flusso `set password` (se URL fornita)

Output atteso:
- `success = false`
- `status = email_password_login_blocked`
- `reason = local_password_missing_for_federated_account`

### 5) `set password` successivo
1. Flusso dedicato raccoglie nuova password.
2. Hash password con funzione locale (`hashPassword`).
3. Salvataggio su stesso record `USER`.
4. Il collegamento federato resta invariato.

Output atteso:
- `success = true`
- `status = set_password_success`

## Edge Cases Gestiti
- `provider + provider_user_id` già associato ad altro `user_id`: blocco con reason `federated_identity_already_linked_to_another_user`.
- stesso utente con stesso provider ma subject differente: blocco `federated_provider_already_used_for_different_subject`.
- provider senza email: blocco `federated_email_missing` (in policy standard con email obbligatoria).
- payload provider invalido/non supportato: blocco `invalid_federated_identity_payload`.

## Estensioni project-specific

Questa implementazione **non impone schema dati cliente**.
Per indirizzi, fatturazione, business fields, consensi o metadata custom usare solo hook/contratti.

### Punti del flusso dove raccogliere dati aggiuntivi
- prima del login federato (pre-check form)
- dopo primo login federato (progressive profiling)
- durante onboarding guidato
- prima del checkout (gating business/compliance)

### Contratti da usare
- `AdditionalProfileDataHandlerInterface`
  - validazione e persistenza dati extra per stage specifico
- `FederatedOnboardingDataResolverInterface`
  - definisce quali campi sono richiesti in ogni stage
- `PostFederatedLoginHookInterface`
  - side-effect post login (eventi, CRM, analytics, provisioning)

### Come validare senza sporcare auth
- validare nel `FederatedExtensionPipeline` (handler dedicati)
- mantenere auth federata limitata a identità/login/linking
- evitare regole di dominio cliente dentro `FederatedLoginService`

### Come collegare i dati extra al `user_id` senza schema unico
- ogni progetto salva i propri dati in tabelle proprie
- chiave di collegamento: `user_id`
- naming, vincoli e normalizzazione demandati al progetto
- il modulo auth non conosce né richiede lo schema di tali tabelle

## Integrazione (esplicitamente non attivata)
I componenti sono pronti ma non sono collegati ai controller/endpoint correnti.
Per attivare in un secondo momento:
1. verificare ID token Google/Apple con implementazione progetto di `FederatedIdTokenVerifierInterface`
2. mappare claims in `FederatedIdentityPayload` (`FederatedClaimMapper`)
3. instanziare `FederatedLoginService` con gateway/session adapter progetto
4. intercettare il login email/password con `LocalPasswordPolicyService`
5. introdurre redirect UX verso `password-set` in caso di reason esplicita
