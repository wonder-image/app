<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';

use Wonder\Auth\Frontend\AuthProfile;
use Wonder\Auth\Frontend\AuthSession;
use Wonder\Http\SafeRedirect;
use Wonder\App\ResourceSchema\FormField;

function __t(string $key): string { return $key; }
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) { $failures[] = $message; }
};
$profile = new AuthProfile();
$modal = \Wonder\Elements\Components\Modal::make('<script>test</script>')->id('test-modal');
$check($modal->render('wonder') === '', 'Backend-only modal unexpectedly rendered on frontend.');
$modalHtml = $modal->frontend()->render('wonder');
$check(str_contains($modalHtml, 'wi-modal') && str_contains($modalHtml, '&lt;script&gt;') && !str_contains($modalHtml, '<script>test'), 'Frontend modal escaped title or structural contract broken.');
$check(str_contains($modal->render('bootstrap'), 'modal-dialog'), 'Frontend opt-in broke Bootstrap rendering.');
$trigger = \Wonder\Elements\Components\Button::to('/fallback/', 'Open')->opensModal('test-modal');
$check(str_contains($trigger->render('wonder'), 'data-wi-modal-target="#test-modal"') && str_contains($trigger->render('wonder'), 'href="/fallback/"'), 'Wonder modal trigger/fallback missing.');
$check(str_contains($trigger->render('bootstrap'), 'data-bs-target="#test-modal"'), 'Bootstrap modal trigger missing.');
$check(str_contains($modalHtml, 'inert') && str_contains($modalHtml, 'aria-hidden="true"'), 'Closed modal fields are focusable.');
try { $trigger->type('submit')->render('wonder'); $check(false, 'Modal submit trigger accepted.'); } catch (\InvalidArgumentException $e) {}
$migration = \Wonder\App\Support\SharedContactTablesMigration::class;
$check($migration::plan([]) === [], 'Fresh database migration is not a no-op.');
$check($migration::plan(['external_references']) === [], 'Migration is not idempotent.');
$check($migration::plan(['gst_external_references']) === ['gst_external_references' => 'external_references'], 'Legacy migration plan incomplete.');
$check($migration::plan(['gst_contacts', 'gst_contact_addresses']) === [], 'Core migrates the customer-account tables.');
try {
    $migration::plan(['gst_external_references', 'external_references']);
    $check(false, 'Migration accepted conflicting tables.');
} catch (RuntimeException) {}
$check(\Wonder\App\Models\System\ExternalReference::rawTableSchema() !== [] && \Wonder\App\Models\System\ExternalReference::syncSchema() === null, 'Shared model requires gestionale or syncs operational data.');
// Sul sorgente e non su class_exists(): il vendor del core può contenere il modulo.
$coreSources = static function (): \Generator {
    foreach (['class', 'app'] as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/'.$dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') { yield $file->getPathname() => str_replace('\\\\', '\\', (string) file_get_contents($file->getPathname())); }
        }
    }
};
$coreRefs = [];
foreach ($coreSources() as $path => $source) {
    foreach (['Wonder\\Plugin\\CustomerAccount', 'Wonder\\App\\Models\\Contacts'] as $namespace) {
        if (str_contains($source, $namespace)) { $coreRefs[] = basename($path).': '.$namespace; }
    }
}
$check($coreRefs === [], 'Core still owns contacts or depends on customer-account: '.implode(', ', $coreRefs));
$check(!class_exists('Wonder\\Plugin\\Ecommerce\\Ecommerce'), 'Core test unexpectedly depends on ecommerce.');
$check(array_keys($profile->fields('signup-request')) === ['name', 'surname', 'email', 'accept_privacy_policy'], 'Generic signup fields mismatch.');
$check($profile->validate('signup-completion', ['password' => 'password123', 'password_confirmation' => 'password123']) === [], 'Core forces a mobile number.');
$check($profile->validate('signup-completion', ['password' => 'password123', 'password_confirmation' => 'other']) === ['password_confirmation' => 'mismatch'], 'Signup no longer checks the password confirmation.');
$check($profile->validate('password-restore', ['password' => 'password123', 'password_confirmation' => 'other']) === ['password_confirmation' => 'mismatch'], 'Password restore no longer checks the password confirmation.');
$check(!isset($profile->userValues('signup-request', ['name' => 'Ada', 'authority' => 'admin', 'business_name' => 'Injected'])['authority']), 'Untrusted authority reaches user writes.');
$check(\Wonder\Auth\Frontend\AuthValidationAlert::messageKeys(['email' => 'exists']) === ['auth.validation.errors.email_exists'], 'Signup with an existing account email has no dedicated message.');
$consentInput = ['name' => 'Ada', 'accept_privacy_policy' => 'true', 'privacy_policy_id' => '1', 'terms_conditions_id' => '2'];
$check(($profile->userValues('signup-request', $consentInput)['privacy_policy_id'] ?? null) === '1', 'Signup drops the legal document id needed to register consents.');
$check(!isset($profile->userValues('signup-request', $consentInput)['terms_conditions_id']), 'Signup keeps the id of a document the profile does not require.');

$custom = new class extends AuthProfile {
    public function fields(string $surface, array $values = [], bool $passwordRequired = true): array
    {
        $fields = parent::fields($surface, $values, $passwordRequired);
        if ($surface === 'login') {
            $fields['country'] = FormField::key('country')->country()->required();
        }
        return $fields;
    }
    public function validate(string $surface, array $input, bool $passwordRequired = true): array
    {
        $errors = parent::validate($surface, $input, $passwordRequired);
        if ($surface === 'login' && !in_array($input['country'] ?? '', ['IT', 'DE'], true)) {
            $errors['country'] = 'invalid';
        }
        return $errors;
    }
};
$check(isset($custom->fields('login')['country']), 'Custom field missing.');
$check($custom->validate('login', []) === ['country' => 'invalid'], 'Custom field not validated server-side.');
$check($custom->validate('login', ['country' => 'IT']) === [], 'Valid custom field rejected.');
$check(!isset($custom->userValues('signup-request', ['country' => 'IT'])['country']), 'Custom field is persisted implicitly.');
$optionalPhone = new class extends AuthProfile { public function phoneEnabled(): bool { return true; } };
$check(isset($optionalPhone->fields('signup-completion')['phone']) && !isset($profile->fields('signup-completion')['phone']), 'Optional phone not shown only when enabled.');
$check($optionalPhone->validate('signup-completion', ['phone_prefix' => '+39', 'phone' => '', 'password' => 'password123', 'password_confirmation' => 'password123']) === [], 'Empty optional phone (default prefix) rejected.');
$check($optionalPhone->validate('signup-completion', ['phone_prefix' => '+39', 'phone' => '12', 'password' => 'password123', 'password_confirmation' => 'password123']) === ['phone' => 'required'], 'Invalid optional phone accepted.');
$check(!isset($optionalPhone->userValues('signup-completion', ['phone_prefix' => '+39', 'phone' => ''])['phone'])
    && ($optionalPhone->userValues('signup-completion', ['phone_prefix' => '+39', 'phone' => '333 1234567'])['phone'] ?? '') === '+393331234567', 'Optional phone write mismatch.');
$check($profile->validationTexts(['email' => 'exists']) === ['auth.validation.errors.email_exists'], 'Validation texts not translated from message keys.');
$check($profile->captchaAction('signup-completion') === 'account_signup_completion', 'Captcha action scope mismatch.');
$_ENV['APP_URL'] = 'https://example.com';
foreach (['//evil.test', '/\\evil.test', 'javascript:alert(1)', 'https://example.com:444/'] as $url) {
    $check(SafeRedirect::fromRequest($url, '/safe/') === '/safe/', 'Unsafe redirect accepted.');
}
$check(SafeRedirect::fromRequest('https://example.com/account/', '/') === 'https://example.com/account/', 'Same-origin redirect rejected.');
$_SESSION = [];
$csrf = AuthSession::csrfToken();
$check(AuthSession::verify($csrf) && !AuthSession::verify('wrong'), 'CSRF verification failed.');
AuthSession::queueEvent('login', 'google');
$check(AuthSession::consumeEvents() === [['event' => 'login', 'method' => 'google']] && AuthSession::consumeEvents() === [], 'Auth event replayed.');
$check($profile->phoneColumns('signup-completion') === 5 && $profile->phoneFieldSpan('signup-completion', 'phone_prefix') + $profile->phoneFieldSpan('signup-completion', 'phone') === 5, 'Signup phone prefix and number not on one phone row.');
$check($profile->phoneColumns('login') === 1 && $profile->phoneFieldSpan('signup-request', 'name') === 1, 'Other auth forms lost the single phone column.');
$authForm = (string) file_get_contents(dirname(__DIR__).'/app/view/components/frontend/account/auth-form.php');
$check(str_contains($authForm, 'col-p-<?=$phone_columns?>') && str_contains($authForm, '$auth_profile->phoneFieldSpan($surface'), 'Auth form ignores the phone grid.');
$validator = \Wonder\Auth\Frontend\AuthValidator::class;
$check($validator::localPhone('+39333', '+39') === '333', 'localPhone does not strip the matching prefix.');
$check($validator::localPhone('333', '+39') === '333' && $validator::localPhone('333 123', '+39') === '333 123', 'localPhone altered a local number.');
$check($validator::localPhone('+44333', '+39') === '+44333', 'localPhone stripped a different prefix.');
$check($validator::defaultPrefix() === '+39', 'Default phone prefix changed.');
if ($failures !== []) { fwrite(STDERR, implode("\n", $failures)."\n"); exit(1); }
echo "Auth frontend: OK\n";
