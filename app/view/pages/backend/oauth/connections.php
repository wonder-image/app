<?php

use Wonder\App\Resources\OAuth\{ConnectionsResource, GrantResource};
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Badge, Card, Code, Container, SectionTitle, Text};
use Wonder\View\View;

$user = \Wonder\App\LegacyGlobals::get('USER');
$user = is_object($user) ? $user : null;
$servers = ConnectionsResource::servers();
$copy = [__t('oauth.connections.copy'), __t('oauth.connections.copied')];

$serverBlocks = $servers === null
    ? [Text::make(__t('oauth.connections.issuer_error'))->color('warning')->bold()]
    : array_map(static function (array $r) use ($copy): Container {
        $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $r['name'])), '-') ?: 'mcp';

        return (new Container())->gap(2)->components([
            SectionTitle::make((string) $r['name'])->level(6),
            Badge::make(__t('oauth.connections.transport_badge'))->variant('primary')->outline()->pill(),
            Code::make((string) $r['url'], 'text')->copyLabels(...$copy),
            Code::make('claude mcp add --transport http '.$slug.' '.$r['url'], 'bash')
                ->title(__t('oauth.connections.claude_code_example'))->copyLabels(...$copy),
        ]);
    }, $servers);

View::layout('backend.show', ['TITLE' => __t('oauth.connections.title'), 'SUBTITLE' => '']);

echo Layout::renderLayout((new Container())->gap(4)->components([
    (new Card())->gap(3)->components([
        SectionTitle::make(__t('oauth.connections.servers_title'))->level(5),
        Text::make(__t('oauth.connections.servers_help'))->muted()->small(),
        ...$serverBlocks,
    ]),
    (new Card())->components([
        '<div class="col-12">'.GrantResource::backendTable(GrantResource::columnsFor($user))->generate(false).'</div>',
    ]),
]));

View::end();
