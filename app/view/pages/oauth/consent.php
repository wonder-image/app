<?php
use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Button, Card, SectionTitle, Text};
use Wonder\Elements\Form\Form;
use Wonder\Http\Csrf;
use Wonder\View\View;

$vars = ['client' => $request['client_name'], 'resource' => $request['resource_name'], 'site' => $siteName, 'host' => $redirectHost,
    'name' => trim(($user->name ?? '').' '.($user->surname ?? '')) ?: ($user->email ?? '')];

// Ogni scelta è un POST a sé con il proprio CSRF: "cambia account" resta
// un form separato, così Invio non può mai scambiare account per errore.
$action = '/oauth/consent?req='.rawurlencode($reqId);
$choice = static fn (string $value, string $label): Button => Button::post($action, $label)->hidden(['req' => $reqId, 'action' => $value]);

View::layout('backend.auth');

// Gli scope sono caselle già spuntate: chi approva può togliere quelli che non vuole dare.
echo Layout::render((new Form())->components([
    (new Card())->gap(3)->components([
        SectionTitle::make(__t('oauth.consent.title', $vars))->level(1)->align('center'),
        Text::make(__t($scopes !== [] ? 'oauth.consent.intro_scopes' : 'oauth.consent.intro', $vars)),
        $scopes !== [] ? FormField::key('scopes')->label(__t('oauth.consent.scopes'))->checkbox()->options($scopes)->value(array_keys($scopes)) : '',
        Text::make(__t('oauth.consent.redirect_to', $vars)),
        $loopback ? Text::make(__t('oauth.consent.loopback_warning'))->bold()->color('warning') : '',
        Csrf::field(),
        FormField::key('req')->hidden()->value($reqId),
        FormField::key('action')->hidden()->value('approve'),
        Button::make(__t('oauth.consent.approve'))->type('submit')->variant('success')->block(),
    ]),
]), ['id' => 'oauth-consent', 'action' => $action, 'enctype' => 'application/x-www-form-urlencoded']);

echo Layout::renderLayout((new Card())->gap(3)->components([
    $choice('deny', __t('oauth.consent.deny'))->variant('secondary')->block(),
    Text::make(__t('oauth.consent.signed_in_as', $vars))->small()->muted()->align('center'),
    $choice('switch', __t('oauth.consent.switch_account'))->variant('link')->size('sm')->block(),
]));

View::end();
