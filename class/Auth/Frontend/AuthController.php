<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\Credentials;
use Wonder\App\Security\RecaptchaGuard;
use Wonder\Auth\Federated\Bridge\LegacySessionLoginAdapter;
use Wonder\Auth\Federated\FederatedIdentityRepository;
use Wonder\Auth\Federated\FederatedLoginService;
use Wonder\Auth\Federated\GoogleIdTokenVerifier;
use Wonder\Auth\Impersonation;
use Wonder\Auth\OneTimeToken;
use Wonder\Auth\PasswordReset;
use Wonder\View\View;

final class AuthController
{
    public function __construct(private readonly AuthProfile $profile) {}

    public function handle(string $action, array $parameters = []): void
    {
        match ($action) {
            'login' => $this->login(),
            'logout' => $this->logout(),
            'signup.request' => $this->signupRequest(),
            'signup.completion' => $this->signupCompletion(),
            'email.sent' => $this->render('email-sent'),
            'email.verify' => $this->verifyEmail(),
            'password.recovery' => $this->passwordRecovery(),
            'password.restore' => $this->passwordRestore(),
            'federated' => $this->federated((string) ($parameters['provider'] ?? '')),
            'impersonation.start' => $this->startImpersonation(),
            'impersonation.stop' => $this->stopImpersonation(),
            default => $this->notFound(),
        };
    }

    private function login(): void
    {
        global $ALERT;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            if ($this->verifyRecaptcha('login')) {
                $errors = $this->profile->validate('login', $_POST);
                if ($errors !== []) {
                    $this->render('login', ['errors' => $errors]);
                    return;
                }
                $users = $this->profile->gateway();
                $user = $users->findUserByEmail((string) ($_POST['email'] ?? ''));
                $provider = is_array($user) && !$users->hasLocalPassword((int) ($user['id'] ?? 0))
                    ? $this->federatedProviderForUser((int) ($user['id'] ?? 0))
                    : null;

                if ($provider !== null) {
                    $this->render('login', [
                        'alert' => $ALERT ?? null,
                        'federated_error' => 'use_federated_login_'.$provider,
                    ]);
                    return;
                }

                if (\authenticateUserLogin($_POST['email'] ?? '', $_POST['password'] ?? '', $this->profile->area(), $this->profile->authorities())) {
                    AuthSession::queueEvent('login', 'password');
                    $this->redirect(SafeRedirect::fromRequest($_POST['continue'] ?? '', $this->profile->home()));
                }
            }
        }

        $this->render('login', ['alert' => $ALERT ?? null]);
    }

    private function logout(): void
    {
        $this->requireCsrf();
        \logoutUser($this->profile->area());
        $this->redirect($this->route('login'));
    }

    private function signupRequest(): void
    {
        global $ALERT, $PAGE;
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            if (!$this->verifyRecaptcha('signup-request')) {
                $this->render('signup-request', ['errors' => [], 'alert' => $ALERT ?? null]);
                return;
            }

            $errors = $this->profile->validate('signup-request', $_POST);

            if ($errors === []) {
                $continue = SafeRedirect::fromRequest($_POST['continue'] ?? '', $this->profile->home());
                if (isset($PAGE) && is_object($PAGE)) {
                    $PAGE->redirectBase64 = base64_encode($continue);
                }
                $payload = array_merge($this->profile->userValues('signup-request', $_POST), [
                    'area' => $this->profile->area(),
                    'authority' => $this->profile->registrationAuthority(),
                    'active' => 'true',
                    'consent_surface' => $this->profile->key().'_signup',
                ]);
                $created = \user($payload);

                if (empty($ALERT) && (int) ($created->user->id ?? 0) > 0) {
                    if ($this->afterUserSaved((int) $created->user->id, 'signup-request', $_POST)) {
                        $this->redirect($this->route('email.sent'));
                    }
                }
            }
        }

        $this->render('signup-request', ['errors' => $errors, 'alert' => $ALERT ?? null]);
    }

    private function verifyEmail(): void
    {
        $verified = \confirmUserVerificationToken((string) ($_GET['token'] ?? ''));

        if (!($verified->success ?? false) || (int) ($verified->user_id ?? 0) <= 0) {
            $this->render('message', ['message_key' => 'auth.email.invalid']);
            return;
        }

        $userId = (int) $verified->user_id;
        if (!$this->profile->gateway()->canAccessArea($userId, $this->profile->area(), $this->profile->authorities())) {
            $this->render('message', ['message_key' => 'auth.email.invalid']);
            return;
        }
        if (!$this->afterUserSaved($userId, 'email.verify', [])) {
            $this->render('message', ['message_key' => 'auth.validation.review', 'alert' => $GLOBALS['ALERT'] ?? null]);
            return;
        }
        $continue = SafeRedirect::fromRequest($verified->redirect_url ?? '', $this->profile->home());
        $token = (new OneTimeToken(
            $this->profile->completionPurpose(),
            (int) $this->profile->completionTtl()
        ))->issue($userId, null, $continue);

        $this->redirect($this->route('signup.completion').'?token='.rawurlencode($token->token));
    }

    private function signupCompletion(): void
    {
        global $ALERT;
        $tokenValue = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
        $tokens = new OneTimeToken($this->profile->completionPurpose(), (int) $this->profile->completionTtl());
        $record = $tokens->inspect($tokenValue);

        if ($record === null || !$this->profile->gateway()->canAccessArea($record->subject_user_id, $this->profile->area(), $this->profile->authorities())) {
            $this->render('message', ['message_key' => 'auth.signup.token_invalid']);
            return;
        }

        $federatedProvider = trim((string) ($record->metadata['federated_provider'] ?? ''));
        $isFederated = $federatedProvider !== '';
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            if (!$this->verifyRecaptcha('signup-completion')) {
                $this->render('signup-completion', [
                    'token' => $tokenValue,
                    'email' => (string) (\infoUser($record->subject_user_id, 'id')->email ?? ''),
                    'errors' => [],
                    'alert' => $ALERT ?? null,
                    'password_required' => !$isFederated,
                ]);
                return;
            }
            $errors = $this->profile->validate('signup-completion', $_POST, !$isFederated);
            $phone = AuthValidator::canonicalPhone($_POST);

            if ($errors === [] && $this->profile->phoneRequired() && !\unique($phone, 'user', 'phone', $record->subject_user_id)) {
                $errors['phone'] = 'not_unique';
            }

            if ($errors === []) {
                try {
                    $updated = $this->completeSignup($tokens, $tokenValue, $record, $_POST);
                } catch (\Throwable $exception) {
                    error_log('Auth completion failed: '.get_class($exception));
                    $errors['token'] = $exception->getMessage() === 'auth_completion_token_invalid' ? 'invalid' : 'save_failed';
                    $updated = null;
                }

                if ($updated !== null && empty($ALERT) && ($updated->user->exists ?? false)) {
                    $loggedIn = $isFederated
                        ? (new LegacySessionLoginAdapter())->loginUser((int) $updated->user->id, $this->profile->area(), ['provider' => $federatedProvider])
                        : \authenticateUser('email', $updated->user->email, $_POST['password'], $this->profile->area(), $this->profile->authorities());

                    if ($loggedIn) {
                        if (!$isFederated || !empty($record->metadata['signup_event'])) {
                            AuthSession::queueEvent('sign_up', $isFederated ? $federatedProvider : 'password');
                        }
                        AuthSession::queueEvent('login', $isFederated ? $federatedProvider : 'password');
                        $this->redirect(SafeRedirect::fromRequest($record->continue_url, $this->profile->home()));
                    }
                }
            }
        }

        $user = \infoUser($record->subject_user_id, 'id');
        $this->render('signup-completion', [
            'token' => $tokenValue,
            'email' => (string) ($user->email ?? ''),
            'errors' => $errors,
            'alert' => $ALERT ?? null,
            'password_required' => !$isFederated,
        ]);
    }

    private function passwordRecovery(): void
    {
        global $ALERT;
        $sent = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            if (!$this->verifyRecaptcha('password-recovery')) {
                $this->render('password-recovery', ['sent' => false, 'alert' => $ALERT ?? null]);
                return;
            }
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $errors = $this->profile->validate('password-recovery', $_POST);
            if ($errors !== []) {
                $this->render('password-recovery', ['sent' => false, 'errors' => $errors]);
                return;
            }
            $user = \infoUser($email, 'email');

            if (($user->exists ?? false) && $this->profile->gateway()->canAccessArea((int) $user->id, $this->profile->area(), $this->profile->authorities())) {
                $issued = (new PasswordReset((int) $this->profile->resetTtl()))
                    ->issueForUser((int) $user->id, $this->route('login'));
                $url = $this->absolute($this->route('password.restore').'?token='.rawurlencode($issued->token));
                $from = (string) ($GLOBALS['SOCIETY']->email ?? '');
                \sendMail($from, $email, (string) __t('auth.email.reset_subject'), (string) __t('auth.email.reset_body', ['url' => $url]));
            }

            $sent = true;
        }

        $this->render('password-recovery', ['sent' => $sent, 'alert' => $ALERT ?? null]);
    }

    private function passwordRestore(): void
    {
        global $ALERT;
        $token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
        $service = new PasswordReset((int) $this->profile->resetTtl());
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            if (!$this->verifyRecaptcha('password-restore')) {
                $this->render('password-restore', [
                    'token' => $token,
                    'errors' => [],
                    'alert' => $ALERT ?? null,
                ]);
                return;
            }
            $errors = $this->profile->validate('password-restore', $_POST);

            if ($errors === []) {
                $record = (new OneTimeToken('password_reset'))->inspect($token);
                if ($record === null || !$this->profile->gateway()->canAccessArea($record->subject_user_id, $this->profile->area(), $this->profile->authorities())) {
                    $this->render('password-restore', ['token' => $token, 'errors' => ['token' => 'invalid']]);
                    return;
                }
                $result = $service->reset($token, (string) $_POST['password']);
                if ($result->success ?? false) {
                    $this->redirect($this->route('login').'?reset=1');
                }
                $errors['token'] = 'invalid';
            }
        }

        $this->render('password-restore', ['token' => $token, 'errors' => $errors, 'alert' => $ALERT ?? null]);
    }

    private function federated(string $provider): void
    {
        $provider = strtolower(trim($provider));
        $surface = ($_POST['auth_surface'] ?? '') === 'signup' ? 'signup-request' : 'login';
        $api = Credentials::api();
        $nonce = (string) ($_SESSION['wonder_oidc_nonce'][$this->profile->key()][$provider] ?? '');
        $idToken = (string) ($_POST['credential'] ?? $_POST['id_token'] ?? '');

        try {
            $this->requireCsrf();
            if (!$this->profile->googleEnabled() || $provider !== 'google' || $nonce === '') {
                throw new \RuntimeException('provider_not_supported');
            }
            unset($_SESSION['wonder_oidc_nonce'][$this->profile->key()][$provider]);
            $verifier = match ($provider) {
                'google' => new GoogleIdTokenVerifier((string) ($api->google_oauth_client_id ?? ''), $nonce ?: null),
                default => throw new \RuntimeException('provider_not_supported'),
            };
            $identity = $verifier->verify($idToken);
            $users = $this->profile->gateway();
            $identities = new FederatedIdentityRepository();
            $linkedIdentity = $identities->findByProviderIdentity($identity->provider, $identity->providerUserId);
            $existing = $users->findUserByEmail($identity->email);
            $newAccount = $linkedIdentity === null && $existing === null;

            $errors = $this->profile->validateFederated($identity, $_POST, $newAccount);
            if ($errors !== []) {
                $this->render($surface, ['errors' => $errors]);
                return;
            }

            if ($linkedIdentity === null && !$identity->emailVerified) {
                throw new \RuntimeException('federated_email_not_verified');
            }

            $result = (new FederatedLoginService($users, $identities))
                ->authenticate($identity, $this->profile->area(), $this->profile->authorities());

            if (!$result->success || !$result->userId) {
                throw new \RuntimeException($result->reason ?: 'federated_login_failed');
            }

            if ($newAccount) {
                $this->profile->afterUserSaved($result->userId, 'federated', $_POST);
            }

            $user = $users->findUserById($result->userId);
            if ($this->profile->requiresCompletion($user ?? [])) {
                $completion = (new OneTimeToken($this->profile->completionPurpose(), (int) $this->profile->completionTtl()))
                    ->issue(
                        $result->userId,
                        null,
                        SafeRedirect::fromRequest($_POST['continue'] ?? '', $this->profile->home()),
                        ['federated_provider' => $provider, 'signup_event' => $newAccount]
                    );
                $this->redirect($this->route('signup.completion').'?token='.rawurlencode($completion->token));
            }

            if (!(new LegacySessionLoginAdapter())->loginUser($result->userId, $this->profile->area(), ['provider' => $provider])) {
                throw new \RuntimeException('federated_login_failed');
            }
            if ($newAccount) { AuthSession::queueEvent('sign_up', $provider); }
            AuthSession::queueEvent('login', $provider);
            $this->redirect(SafeRedirect::fromRequest($_POST['continue'] ?? '', $this->profile->home()));
        } catch (\Throwable $exception) {
            $this->render($surface, ['federated_error' => $exception->getMessage()]);
        }
    }

    private function startImpersonation(): void
    {
        $service = $this->impersonation();
        $result = $service->start((string) ($_GET['token'] ?? ''));
        $this->redirect(($result->success ?? false)
            ? SafeRedirect::fromRequest($result->continue_url, '/')
            : $this->route('login'));
    }

    private function federatedProviderForUser(int $userId): ?string
    {
        foreach ((new FederatedIdentityRepository())->findByUserId($userId) as $identity) {
            $provider = strtolower(trim((string) ($identity['provider'] ?? '')));
            if ($provider !== '') {
                return $provider;
            }
        }

        return null;
    }

    private function stopImpersonation(): void
    {
        $result = $this->impersonation()->stop((string) ($_POST['csrf_token'] ?? ''));
        $this->redirect(($result->success ?? false)
            ? SafeRedirect::fromRequest($result->return_url, '/backend/')
            : '/');
    }

    private function impersonation(): Impersonation
    {
        return new Impersonation(
            (array) $this->profile->impersonationAuthorities(),
            (int) $this->profile->impersonationTtl(),
        );
    }

    private function render(string $page, array $data = []): void
    {
        $this->configureSeo($page, $data);
        $nonce = bin2hex(random_bytes(24));
        $api = Credentials::api();
        $_SESSION['wonder_oidc_nonce'][$this->profile->key()]['google'] = $nonce;

        View::make($this->profile->viewPath($page), $data + [
            'auth_profile' => $this->profile,
            'surface' => $page,
            'fields' => $this->profile->fields($page, array_merge($_POST, isset($data['email']) ? ['email' => $data['email']] : []), $data['password_required'] ?? true),
            'alert' => null,
            'errors' => [],
            'validation_messages' => array_map(static fn ($key) => (string) __t($key), $this->profile->validationMessages((array) ($data['errors'] ?? []))),
            'federated_error' => null,
            'csrf_token' => AuthSession::csrfToken(),
            'oidc_nonce' => $nonce,
            'google_client_id' => $this->profile->googleEnabled() ? (string) ($api->google_oauth_client_id ?? '') : '',
            'values' => $_POST,
        ])->render();
    }

    private function verifyRecaptcha(string $action): bool
    {
        global $ALERT;

        try {
            RecaptchaGuard::for($this->profile->captchaAction($action))
                ->withService('auth-'.$this->profile->key())
                ->withLogAction($action)
                ->verify();

            return true;
        } catch (\RuntimeException $exception) {
            if ($exception->getCode() !== RecaptchaGuard::ERROR_CODE) {
                throw $exception;
            }

            $ALERT = RecaptchaGuard::ERROR_CODE;

            return false;
        }
    }

    private function afterUserSaved(int $userId, string $surface, array $input): bool
    {
        try {
            $this->profile->afterUserSaved($userId, $surface, $input);
            return true;
        } catch (\Throwable $exception) {
            error_log('Auth profile hook failed: '.get_class($exception));
            $GLOBALS['ALERT'] = 900;
            return false;
        }
    }

    private function completeSignup(OneTimeToken $tokens, string $token, object $record, array $input): object
    {
        return \Wonder\Sql\Transaction::run(function () use ($tokens, $token, $record, $input): object {
            if ($tokens->consume($token) === null) {
                throw new \RuntimeException('auth_completion_token_invalid');
            }
            $updated = \user(array_merge($this->profile->userValues('signup-completion', $input), [
                'area' => $this->profile->area(),
                'authority' => $this->profile->registrationAuthority(),
            ]), $record->subject_user_id);
            if (!empty($GLOBALS['ALERT']) || !($updated->user->exists ?? false)) {
                throw new \RuntimeException('auth_completion_write_failed');
            }
            $this->profile->afterUserSaved((int) $updated->user->id, 'signup-completion', $input);
            return $updated;
        });
    }

    private function configureSeo(string $page, array $data): void
    {
        global $SEO;

        $definition = match ($page) {
            'login' => ['auth.login.title', 'auth.seo.login', 'login'],
            'signup-request' => ['auth.signup.title', 'auth.seo.signup', 'signup.request'],
            'signup-completion' => ['auth.signup.complete_title', 'auth.seo.signup_completion', 'signup.completion'],
            'email-sent' => ['auth.email.sent_title', 'auth.seo.email_sent', 'email.sent'],
            'password-recovery' => ['auth.recovery.title', 'auth.seo.password_recovery', 'password.recovery'],
            'password-restore' => ['auth.restore.title', 'auth.seo.password_restore', 'password.restore'],
            default => ['auth.message.title', 'auth.seo.message', 'login'],
        };

        $SEO->title = (string) __t($definition[0]);
        $SEO->description = (string) __t($definition[1]);
        $SEO->url = $this->route($definition[2]);
        $SEO->breadcrumb = [];
        $SEO->robots = 'NOINDEX,FOLLOW';
    }

    private function requireCsrf(): void
    {
        if (!AuthSession::verify($_POST['csrf_token'] ?? '')) {
            http_response_code(419);
            exit('CSRF token invalid');
        }
    }

    private function route(string $name, array $parameters = []): string
    {
        return $this->profile->route($name, $parameters);
    }

    private function absolute(string $path): string
    {
        return rtrim((string) ($_ENV['APP_URL'] ?? ''), '/').'/'.ltrim($path, '/');
    }

    private function redirect(string $url): never
    {
        header('Location: '.$url);
        exit;
    }

    private function notFound(): never
    {
        http_response_code(404);
        exit;
    }
}
