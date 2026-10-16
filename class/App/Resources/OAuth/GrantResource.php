<?php

namespace Wonder\App\Resources\OAuth;

use Wonder\App\LegacyGlobals;
use Wonder\App\Models\OAuth\OAuthGrant;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\{ApiSchema, NavigationSchema, PageSchema, TableColumn, TableLayoutSchema};
use Wonder\OAuth\RedirectUri;

/**
 * Tabella delle connessioni OAuth attive, incorporata nella pagina
 * "Connessioni MCP" (ConnectionsResource). Nessuna pagina, API o voce di
 * menu propria: esiste per dare alla DataTable model, etichette e azioni.
 * La revoca passa da /api/backend/oauth/revoke/, che ricontrolla la proprietà.
 */
class GrantResource extends Resource
{
    public static string $model = OAuthGrant::class;

    public static function path(): string { return 'app/oauth/grants'; }

    public static function textSchema(): array
    {
        return ['label' => __t('oauth.connections.label'), 'plural_label' => __t('oauth.connections.plural_label'), 'article' => 'le', 'last' => 'ultime', 'all' => 'tutte', 'this' => 'questa'];
    }

    public static function labelSchema(): array
    {
        return ['user_id' => __t('oauth.connections.col_user'), 'client_name' => __t('oauth.connections.col_client'),
            'redirect_uris' => __t('oauth.connections.col_redirect'), 'resource' => __t('oauth.connections.col_resource'),
            'consented_at' => __t('oauth.connections.col_consented'), 'last_used_at' => __t('oauth.connections.col_last_used'), 'actions' => ''];
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('user_id')->userName(),
            TableColumn::key('client_name')->text()->formatter(static fn ($row) => e((string) ($row['client_name'] ?: $row['client_id']))),
            TableColumn::key('redirect_uris')->text()->formatter(static fn ($row) => e(self::redirectHost($row['redirect_uris'] ?? null))),
            TableColumn::key('resource')->text()->formatter(static fn ($row) => e(self::resourceName((string) $row['resource']))),
            TableColumn::key('consented_at')->datetime(),
            TableColumn::key('last_used_at')->text()->formatter(static fn ($row) => empty($row['last_used_at'])
                ? e(__t('oauth.connections.never'))
                : e((new \DateTime((string) $row['last_used_at']))->format('d/m/Y H:i'))),
            TableColumn::key('actions')->button()->actions([
                'revoke' => [
                    'label' => __t('oauth.connections.revoke'),
                    'request' => 'backend/oauth/revoke/',
                    'confirm' => __t('oauth.connections.revoke_confirm', ['client' => '{client_name}']),
                    'confirm_title' => __t('oauth.connections.revoke_title'),
                    'confirm_ok' => __t('oauth.connections.revoke'),
                    'confirm_variant' => 'danger',
                    'color' => 'danger',
                ],
            ]),
        ];
    }

    /** Dominio del primo redirect_uri registrato dal client ('' se assente). */
    public static function redirectHost(?string $redirectUris): string
    {
        $uris = json_decode((string) ($redirectUris ?? '[]'), true);

        return is_array($uris) && isset($uris[0]) ? RedirectUri::host((string) $uris[0]) : '';
    }

    /** Nome del server MCP per l'URL della risorsa; l'URL stesso se non è registrato. */
    public static function resourceName(string $resource): string
    {
        return array_column(ConnectionsResource::servers() ?? [], 'name', 'url')[$resource] ?? $resource;
    }

    /** Colonne per la pagina: l'utente compare solo a chi vede i grant di tutti. */
    public static function columnsFor(?object $user): array
    {
        $columns = ['client_name', 'redirect_uris', 'resource', 'consented_at', 'last_used_at', 'actions'];

        return ConnectionsResource::seeAll($user) ? ['user_id', ...$columns] : $columns;
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title(__t('oauth.connections.active_title'))
            ->results(false)
            ->hideButtonAdd()
            ->filters(false, false)
            ->select("(SELECT c.client_name FROM oauth_clients c WHERE c.client_id = oauth_grants.client_id AND c.deleted = 'false' LIMIT 1) AS client_name, "
                ."(SELECT c.redirect_uris FROM oauth_clients c WHERE c.client_id = oauth_grants.client_id AND c.deleted = 'false' LIMIT 1) AS redirect_uris");
    }

    /**
     * Grant attivi dell'utente collegato (tutti per admin). La condizione è
     * firmata nella config della DataTable al render della pagina, quindi il
     * client non può allargarla.
     */
    public static function querySchema(): array
    {
        $user = LegacyGlobals::get('USER');
        $condition = "deleted = 'false' AND revoked_at IS NULL";

        if (!is_object($user) || (int) ($user->id ?? 0) <= 0) {
            $condition = '1 = 0';
        } elseif (!ConnectionsResource::seeAll($user)) {
            $condition .= ' AND user_id = '.(int) $user->id;
        }

        return array_replace(parent::querySchema(), [
            'condition' => $condition,
            'order' => ['column' => 'consented_at', 'direction' => 'DESC'],
        ]);
    }

    public static function pageSchema(): PageSchema { return PageSchema::for(static::class)->only([]); }
    public static function apiSchema(): ApiSchema { return ApiSchema::for(static::class)->enabled(false); }
    public static function navigationSchema(): NavigationSchema { return NavigationSchema::for(static::class)->enabled(false); }
}
