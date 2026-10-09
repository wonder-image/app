<?php
/** php tests/App/Resources/SecurityResourceTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\Resources\Config\SecurityResource;

// formSchema() legge i servizi mail: senza runtime basta un elenco vuoto.
if (!function_exists('mailService')) {
    function mailService(): array
    {
        return [];
    }
}

$normalize = static fn (string $html): string => (string) preg_replace('/field_[a-z]{10}/', 'field_X', $html);

check('P16 InputPassword senza opzioni: render di oggi, senza new-password', fn () =>
    $normalize(FormField::key('pwd')->password()->render('bootstrap'))
        === '<div><div class="form-floating"><input class="form-control" type="password" name="pwd" id="field_X" value="" data-wi-check="true" placeholder="" /><label for="field_X">Pwd</label></div><div class="invalid-feedback"></div></div>'
);

check('P16 i tre segreti del layout si rendono con autocomplete="new-password"', function () {
    foreach (['klaviyo_api_key', 'brevo_api_key', 'mail_password'] as $key) {
        if (!str_contains(SecurityResource::getInput($key)->render('bootstrap'), ' autocomplete="new-password"')) {
            return false;
        }
    }

    return true;
});

// Nel layout sono commentati, ma formSchema() li dichiara comunque.
check('P16 google_oauth_client_secret e apple_oauth_private_key dichiarano new-password', function () {
    foreach (['google_oauth_client_secret', 'apple_oauth_private_key'] as $key) {
        if (!str_contains(SecurityResource::getInput($key)->render('bootstrap'), ' autocomplete="new-password"')) {
            return false;
        }
    }

    return true;
});

check('i segreti del webhook Stripe si rendono come password in sola lettura', function () {
    foreach (['stripe_webhook_secret', 'stripe_test_webhook_secret'] as $key) {
        $html = SecurityResource::getInput($key)->render('bootstrap');

        if (!str_contains($html, 'type="password"') || !str_contains($html, 'readonly')) {
            return false;
        }
    }

    return true;
});

summary();
