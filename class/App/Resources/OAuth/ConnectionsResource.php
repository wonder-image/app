<?php

namespace Wonder\App\Resources\OAuth;

use Wonder\App\LegacyGlobals;
use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\Backend\Support\FlashAlert;
use Wonder\Http\Csrf;
use Wonder\OAuth\OAuth;
use Wonder\OAuth\OAuthException;
use Wonder\OAuth\RedirectUri;

/** Backend "Connessioni MCP": URL dei server MCP e grant OAuth attivi, con revoca. */
class ConnectionsResource extends NavigationOnlyResource
{
    private const SEE_ALL = ['admin', 'administrator'];

    public static function path(): string { return 'app/oauth/connections'; }
    public static function icon(): string { return 'bi-plug'; }
    public static function titleLabel(): string { return __t('oauth.connections.title'); }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('dev')
            ->title(__t('oauth.connections.title'))
            ->order(20)
            ->authority([])
            ->enabled(OAuth::enabled());
    }

    public static function pageView(): string
    {
        return dirname(__DIR__, 4).'/app/view/pages/backend/oauth/connections.php';
    }

    /** Server MCP con URL canonico; null se l'issuer non è valido (APP_URL non https in produzione). */
    public static function servers(): ?array
    {
        try {
            return OAuth::resources();
        } catch (OAuthException) {
            return null;
        }
    }

    public static function rows(?object $user): array
    {
        if ($user === null) {
            return [];
        }
        $store = OAuth::store();
        $seeAll = self::seeAll($user);
        $names = array_column(self::servers() ?? [], 'name', 'url');
        $rows = [];

        foreach ($store->listActiveGrants($seeAll ? null : (int) $user->id) as $g) {
            $client = $store->findClient((string) $g['client_id']);
            $uris = json_decode((string) ($client['redirect_uris'] ?? '[]'), true) ?: [];
            $owner = $seeAll ? $store->findUser((int) $g['user_id']) : $user;
            $rows[] = [
                'grant_id' => (int) $g['id'],
                'user_name' => trim(($owner->name ?? '').' '.($owner->surname ?? '')) ?: (string) ($owner->email ?? ''),
                'client_name' => (string) ($client['client_name'] ?? $g['client_id']),
                'redirect_host' => isset($uris[0]) ? RedirectUri::host((string) $uris[0]) : '',
                'resource' => $names[$g['resource']] ?? (string) $g['resource'],
                'consented_at' => (string) $g['consented_at'],
                'last_used_at' => $g['last_used_at'] ?? null,
            ];
        }

        return $rows;
    }

    public static function seeAll(?object $user): bool
    {
        return $user !== null && array_intersect(self::SEE_ALL, (array) ($user->authority ?? [])) !== [];
    }

    public static function canRevoke(object $user, array $grant): bool
    {
        return (int) $grant['user_id'] === (int) $user->id || self::seeAll($user);
    }

    /**
     * Revoca di un grant. Controlla CSRF, esistenza, stato e proprietà lato server
     * (il grant_id del form è solo un puntatore). Esito via FlashAlert, poi PRG.
     */
    public static function handleSubmit(): never
    {
        $user = LegacyGlobals::get('USER');
        $grant = OAuth::store()->findGrant((int) ($_POST['grant_id'] ?? 0));

        if (!Csrf::verify() || $grant === null || !empty($grant['revoked_at']) || !is_object($user) || !self::canRevoke($user, $grant)) {
            FlashAlert::custom(__t('oauth.connections.title'), __t('oauth.connections.revoke_error'), 'error');
        } else {
            OAuth::store()->revokeGrant((int) $grant['id'], date('Y-m-d H:i:s'));
            FlashAlert::custom(__t('oauth.connections.revoked_title'), __t('oauth.connections.revoked_message'), 'success');
        }

        header('Location: '.__r('backend.oauth.connections'), true, 303);
        exit();
    }
}
