<?php
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Button, Card, Container, SectionTitle, Text};
use Wonder\View\View;

$vars = ['client' => $request['client_name'], 'resource' => $request['resource_name'], 'site' => $siteName, 'host' => $redirectHost,
    'name' => trim(($user->name ?? '').' '.($user->surname ?? '')) ?: ($user->email ?? '')];

// Ogni scelta è un POST a sé con il proprio CSRF: "cambia account" resta
// un form separato, così Invio non può mai scambiare account per errore.
$action = '/oauth/consent?req='.rawurlencode($reqId);
$choice = static fn (string $value, string $label): Button => Button::post($action, $label)->hidden(['req' => $reqId, 'action' => $value]);

View::layout('backend.auth');

echo Layout::renderLayout((new Card())->gap(3)->components([
    SectionTitle::make(__t('oauth.consent.title', $vars))->level(1)->align('center'),
    Text::make(__t('oauth.consent.intro', $vars)),
    Text::make(__t('oauth.consent.redirect_to', $vars)),
    $loopback ? Text::make(__t('oauth.consent.loopback_warning'))->bold()->color('warning') : '',
    (new Container())->columns(2)->gap(2)->components([
        $choice('approve', __t('oauth.consent.approve'))->variant('success')->block(),
        $choice('deny', __t('oauth.consent.deny'))->variant('secondary')->block(),
    ]),
    Text::make(__t('oauth.consent.signed_in_as', $vars))->small()->muted()->align('center'),
    $choice('switch', __t('oauth.consent.switch_account'))->variant('link')->size('sm')->block(),
]));

View::end();
