<?php
use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Button, Card, SectionTitle, Text};
use Wonder\Elements\Form\Form;
use Wonder\Http\Csrf;
use Wonder\View\View;

$vars = ['client' => $request['client_name'], 'resource' => $request['resource_name'], 'site' => $siteName];

View::layout('backend.auth');

if ($alert !== null) { echo alertTheme($alert); }

echo Layout::render((new Form())->components([
    (new Card())->gap(3)->components([
        SectionTitle::make(__t('oauth.login.title', $vars))->level(1)->align('center'),
        Text::make(__t('oauth.login.subtitle', $vars))->align('center'),
        Csrf::field(),
        FormField::key('req')->hidden()->value($reqId),
        FormField::key('username')->text()->label(__t('oauth.login.username'))->autocomplete('username')->required(),
        FormField::key('password')->password()->label(__t('oauth.login.password'))->autocomplete('current-password')->required(),
        Button::make(__t('oauth.login.submit'))->type('submit')->variant('success')->block(),
    ]),
]), ['id' => 'oauth-login', 'action' => '/oauth/login?req='.rawurlencode($reqId), 'enctype' => 'application/x-www-form-urlencoded']);

View::end();
