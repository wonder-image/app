<?php

use Wonder\App\Resources\OAuth\ConnectionsResource;
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Button, Card, Code, Container, SectionTitle, Text};
use Wonder\View\View;

$user = \Wonder\App\LegacyGlobals::get('USER');
$user = is_object($user) ? $user : null;
$servers = ConnectionsResource::servers();
$rows = ConnectionsResource::rows($user);
$seeAll = ConnectionsResource::seeAll($user);
$action = __r('backend.oauth.connections');

$serverCards = $servers === null
    ? [Text::make(__t('oauth.connections.issuer_error'))->color('warning')->bold()]
    : [Text::make(__t('oauth.connections.servers_help'))->muted(), ...array_map(static function (array $r): Card {
        $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $r['name'])), '-') ?: 'mcp';
        $copy = [__t('oauth.connections.copy'), __t('oauth.connections.copied')];

        return (new Card())->gap(2)->components([
            SectionTitle::make((string) $r['name']),
            Code::make((string) $r['url'], 'text')->copyLabels(...$copy),
            Code::make('claude mcp add --transport http '.$slug.' '.$r['url'], 'bash')
                ->title(__t('oauth.connections.claude_code_example'))->copyLabels(...$copy),
        ]);
    }, $servers)];

$table = static function (array $rows, bool $seeAll, string $action): string {
    $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $cols = array_merge($seeAll ? ['oauth.connections.col_user'] : [], ['oauth.connections.col_client', 'oauth.connections.col_redirect',
        'oauth.connections.col_resource', 'oauth.connections.col_consented', 'oauth.connections.col_last_used']);
    $html = '<div class="col-12"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr>';
    foreach ($cols as $col) { $html .= '<th>'.$e(__t($col)).'</th>'; }
    $html .= '<th></th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        if ($seeAll) { $html .= '<td>'.$e($row['user_name']).'</td>'; }
        foreach (['client_name', 'redirect_host', 'resource', 'consented_at'] as $key) { $html .= '<td>'.$e($row[$key]).'</td>'; }
        $html .= '<td>'.$e($row['last_used_at'] ?? __t('oauth.connections.never')).'</td>';
        $html .= '<td class="text-end">'.Button::post($action, __t('oauth.connections.revoke'))
            ->hidden(['grant_id' => (int) $row['grant_id']])
            ->variant('danger')->size('sm')
            ->confirm(__t('oauth.connections.revoke_confirm', ['client' => (string) $row['client_name']]), ok: __t('oauth.connections.revoke'))
            ->render().'</td>';
        $html .= '</tr>';
    }

    return $html.'</tbody></table></div></div>';
};

View::layout('backend.show', ['TITLE' => __t('oauth.connections.title'), 'SUBTITLE' => '']);

echo Layout::renderLayout((new Container())->gap(4)->components([
    (new Container())->gap(3)->components([
        SectionTitle::make(__t('oauth.connections.servers_title'))->level(5),
        ...$serverCards,
    ]),
    (new Container())->gap(3)->components([
        SectionTitle::make(__t('oauth.connections.active_title'))->level(5),
        $rows === []
            ? Text::make(__t('oauth.connections.empty'))->muted()
            : (new Card())->components([$table($rows, $seeAll, $action)]),
    ]),
]));

View::end();
