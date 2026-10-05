<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\ResourceSchema\FormField;
use Wonder\Auth\Federated\Contract\UserAccountGatewayInterface;
use Wonder\View\View;

/** Site/module policy: fields, validation and business hooks, never authentication HTML. */
class AuthProfile
{
    public function key(): string { return 'account'; }
    public function routePrefix(): string { return 'account.auth'; }
    public function pathPrefix(): string { return '/account/auth'; }
    public function area(): string { return 'frontend'; }
    public function authorities(): array { return ['client']; }
    public function registrationAuthority(): string { return $this->authorities()[0]; }
    public function home(): string { return '/account/'; }
    public function documents(): array { return ['privacy_policy']; }
    public function phoneRequired(): bool { return false; }
    public function completionTtl(): int { return 86400; }
    public function resetTtl(): int { return 1800; }
    public function googleEnabled(): bool { return true; }
    public function impersonationEnabled(): bool { return false; }
    public function impersonationAuthorities(): array { return ['admin']; }
    public function impersonationTtl(): int { return 120; }
    public function parentLayout(): string { return 'frontend.minimal'; }

    public function gateway(): UserAccountGatewayInterface
    {
        return new UserAccountGateway($this->authorities());
    }

    public function route(string $action, array $parameters = []): string
    {
        return (string) (\__r($this->routePrefix().'.'.$action, $parameters) ?: $this->home());
    }

    public function captchaAction(string $surface): string
    {
        return $this->key().'_'.str_replace('-', '_', $surface);
    }

    public function completionPurpose(): string { return $this->key().'_signup_completion'; }
    public function columns(string $surface): int
    {
        return match ($surface) { 'signup-request' => 2, 'signup-completion' => 4, default => 1 };
    }
    public function fieldSpan(string $surface, string $key): int
    {
        return match (true) {
            $surface === 'signup-request' && in_array($key, ['name', 'surname'], true) => 1,
            $surface === 'signup-completion' && $key === 'phone_prefix' => 1,
            $surface === 'signup-completion' && $key === 'phone' => 3,
            default => $this->columns($surface),
        };
    }
    public function requiresCompletion(array $user): bool
    {
        return $this->phoneRequired() && empty($user['phone']);
    }

    /** Override in the site/module; additional fields must have server-side validation. */
    public function fields(string $surface, array $values = [], bool $passwordRequired = true): array
    {
        $field = static fn (string $key) => FormField::key($key)->text()->label((string) __t('auth.fields.'.$key))->required()->value($values[$key] ?? '');
        $email = FormField::key('email')->email()->label((string) __t('auth.fields.email'))->required()->value($values['email'] ?? '');
        $password = FormField::key('password')->password()->label((string) __t($passwordRequired ? 'auth.fields.password' : 'auth.fields.password_optional'))->required($passwordRequired);
        $confirmation = FormField::key('password_confirmation')->password()->label((string) __t('auth.fields.password_confirmation'))->required($passwordRequired);

        $fields = match ($surface) {
            'login' => ['email' => $email, 'password' => $password],
            'signup-request' => ['name' => $field('name'), 'surname' => $field('surname'), 'email' => $email],
            'signup-completion' => ['email' => $email->disabled()],
            'password-recovery' => ['email' => $email],
            'password-restore' => ['password' => $password, 'password_confirmation' => $confirmation],
            default => [],
        };

        if ($surface === 'signup-request') {
            foreach ($this->documents() as $document) {
                $fields['accept_'.$document] = FormField::key('accept_'.$document)->acceptDocument($document)->required()->value($values['accept_'.$document] ?? '');
            }
        }
        if ($surface === 'signup-completion') {
            if ($this->phoneRequired()) {
                $fields['phone_prefix'] = FormField::key('phone_prefix')->phonePrefix()->label((string) __t('auth.fields.prefix'))->required()->value($values['phone_prefix'] ?? '+39');
                $fields['phone'] = FormField::key('phone')->phone()->label((string) __t('auth.fields.mobile'))->required()->value($values['phone'] ?? '');
            }
            $fields += ['password' => $password, 'password_confirmation' => $confirmation];
        }

        return $fields;
    }

    /** Errors use field => reason codes, converted into a single page-level alert. */
    public function validate(string $surface, array $input, bool $passwordRequired = true): array
    {
        return match ($surface) {
            'signup-request' => AuthValidator::signupRequest($input, $this->documents()),
            'signup-completion' => AuthValidator::completion($input, $passwordRequired, $this->phoneRequired()),
            'password-restore' => AuthValidator::completion($input, true, false),
            default => [],
        };
    }

    /** Whitelist account fields. Custom contact/billing values belong to afterUserSaved(). */
    public function userValues(string $surface, array $input): array
    {
        $keys = $surface === 'signup-request'
            ? ['name', 'surname', 'email', ...array_map(static fn ($document) => 'accept_'.$document, $this->documents())]
            : ['password', 'password_confirmation'];
        $values = array_intersect_key($input, array_flip($keys));
        if ($surface === 'signup-completion' && $this->phoneRequired()) {
            $values['phone'] = AuthValidator::canonicalPhone($input);
        }
        return $values;
    }

    public function afterUserSaved(int $userId, string $surface, array $input): void {}
    public function validateFederated(\Wonder\Auth\Federated\FederatedIdentityPayload $identity, array $input, bool $newAccount): array { return []; }
    public function validationMessages(array $errors): array { return AuthValidationAlert::messageKeys($errors); }

    public function layout(array $data = []): void
    {
        View::layout('frontend.account.auth', $data + ['auth_profile' => $this]);
    }

    public function viewPath(string $page): string
    {
        $relative = 'pages/frontend/auth/'.basename($page).'.php';
        $root = (string) ($GLOBALS['ROOT'] ?? '');
        $custom = $root.'/custom/view/'.$relative;
        return $root !== '' && is_file($custom) ? $custom : dirname(__DIR__, 3).'/app/view/'.$relative;
    }
}
