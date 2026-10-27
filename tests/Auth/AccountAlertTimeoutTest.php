<?php
/** php tests/Auth/AccountAlertTimeoutTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\App\Theme;
use Wonder\Auth\Frontend\AccountModal;
use Wonder\App\ResourceSchema\FormField;
use Wonder\Frontend\Support\FlashMessage;

// Gli avvisi del pannello portano data-wi-alert-timeout: la lib li porta a livello di pagina e li chiude.
if (!function_exists('__t')) {
    function __t(string $key): string { return $key; }
}

Theme::set('wonder');
$fields = ['name' => FormField::key('name')->text()->label('Nome')];

check('modal con errori → avviso con timeout e testo degli errori', function () use ($fields): bool {
    $html = AccountModal::make('account-personal', 'Dati', $fields, '/account/personal/', [], ['Data non valida', 'Nome mancante'], true)->render();

    return preg_match("/<div id='[^']*' class='wi-alert wi-show' data-wi-alert-timeout=\"".AccountModal::ALERT_TIMEOUT."\">/", $html) === 1
        && str_contains($html, 'Data non valida<br />')
        && str_contains($html, 'Nome mancante');
});

check('modal senza errori → nessun avviso', fn (): bool =>
    !str_contains(AccountModal::make('account-personal', 'Dati', $fields, '/account/personal/')->render(), 'wi-alert')
);

check('avviso di salvataggio del pannello con lo stesso timeout', fn (): bool =>
    str_contains((string) file_get_contents(__DIR__.'/../../app/view/layout/frontend/account/panel.php'), "Alert::make(\$notice, 'success')->title((string) __t('account.notice_title'))->attr('data-wi-alert-timeout', \\Wonder\\Auth\\Frontend\\AccountModal::ALERT_TIMEOUT)")
);

check('errori generali del pannello con lo stesso timeout', fn (): bool =>
    str_contains((string) file_get_contents(__DIR__.'/../../app/view/layout/frontend/account/panel.php'), "Alert::make(implode(\"\\n\", \$errors), 'error')->title((string) __t('account.error_title'))->attr('data-wi-alert-timeout', \\Wonder\\Auth\\Frontend\\AccountModal::ALERT_TIMEOUT)")
);

check('FlashMessage (carrello, checkout) → avviso con timeout', function (): bool {
    $_SESSION = [];
    FlashMessage::error('Prodotto esaurito', 'Controlla il carrello');

    return str_contains(FlashMessage::render(), 'data-wi-alert-timeout="'.FlashMessage::ALERT_TIMEOUT.'"');
});

summary();
