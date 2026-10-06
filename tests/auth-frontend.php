<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';

use Wonder\Auth\Frontend\AuthProfile;
use Wonder\Auth\Frontend\AuthSession;
use Wonder\Auth\Frontend\SafeRedirect;
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
$check($migration::plan(['contacts', 'contact_addresses', 'external_references']) === [], 'Migration is not idempotent.');
$check($migration::plan(['gst_contacts', 'gst_contact_addresses']) === ['gst_contacts' => 'contacts', 'gst_contact_addresses' => 'contact_addresses'], 'Legacy migration plan incomplete.');
try {
    $migration::plan(['gst_contacts', 'contacts']);
    $check(false, 'Migration accepted conflicting tables.');
} catch (RuntimeException) {}
foreach ([\Wonder\App\Models\Contacts\Contact::class, \Wonder\App\Models\Contacts\ContactAddress::class, \Wonder\App\Models\System\ExternalReference::class] as $model) {
    $check($model::rawTableSchema() !== [] && $model::syncSchema() === null, 'Shared model requires gestionale or syncs operational data.');
}
$coreContacts = \Wonder\App\Models\Contacts\Contact::rawTableSchema();
$check(!isset($coreContacts['price_list_id'], $coreContacts['payment_term_id']), 'Core owns commercial settings.');
$check(isset($coreContacts['user_id'], $coreContacts['business_name']), 'Core contact identity incomplete.');
$addressFields = \Wonder\Auth\Frontend\AccountAddressForm::fields(\Wonder\App\Models\Contacts\ContactAddress::address());
$check($addressFields['name']->get('label') === 'components.forms.fields.name.label', 'Address labels fall back to SQL names.');
$check($addressFields['country']->get('value') === 'IT', 'Address country default missing.');
$check(!class_exists('Wonder\\Plugin\\Ecommerce\\Ecommerce'), 'Core test unexpectedly depends on ecommerce.');
$check(array_keys($profile->fields('signup-request')) === ['name', 'surname', 'email', 'accept_privacy_policy'], 'Generic signup fields mismatch.');
$check($profile->validate('signup-completion', ['password' => 'password123', 'password_confirmation' => 'password123']) === [], 'Core forces a mobile number.');
$check(!isset($profile->userValues('signup-request', ['name' => 'Ada', 'authority' => 'admin', 'business_name' => 'Injected'])['authority']), 'Untrusted authority reaches user writes.');
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
if ($failures !== []) { fwrite(STDERR, implode("\n", $failures)."\n"); exit(1); }
echo "Auth frontend: OK\n";
