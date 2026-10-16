<?php

namespace Wonder\App\Resources\OAuth;

use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\OAuth\OAuth;
use Wonder\OAuth\OAuthException;

/** Backend "Connessioni MCP": URL dei server MCP e grant OAuth attivi (tabella in GrantResource), con revoca. */
class ConnectionsResource extends NavigationOnlyResource
{
    private const SEE_ALL = ['admin', 'administrator'];

    public static function path(): string { return 'app/oauth/connections'; }
    public static function icon(): string { return 'bi-plug'; }
    public static function titleLabel(): string { return __t('oauth.connections.title'); }

    public static function navigationSchema(): NavigationSchema
    {
        # Fuori dal menu delle sezioni: la voce sta in Impostazioni, in fondo
        # alla sidebar, accanto a Profilo ed Esci (backend/layout/header.php).
        return NavigationSchema::for(static::class)
            ->title(__t('oauth.connections.menu'))
            ->authority([])
            ->enabled(false);
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

    public static function seeAll(?object $user): bool
    {
        return $user !== null && array_intersect(self::SEE_ALL, (array) ($user->authority ?? [])) !== [];
    }

    public static function canRevoke(object $user, array $grant): bool
    {
        return (int) $grant['user_id'] === (int) $user->id || self::seeAll($user);
    }

    /**
     * Revoca il grant $grantId se $user ne è il proprietario o vede tutti i grant.
     * L'id arriva dal client ed è solo un puntatore: esistenza, stato e proprietà
     * si ricontrollano qui. Revoca anche access e refresh token del grant.
     */
    public static function revoke(int $grantId, ?object $user): bool
    {
        $grant = $grantId > 0 ? OAuth::store()->findGrant($grantId) : null;

        if ($user === null || $grant === null || !empty($grant['revoked_at']) || !self::canRevoke($user, $grant)) {
            return false;
        }

        OAuth::store()->revokeGrant((int) $grant['id'], date('Y-m-d H:i:s'));

        return true;
    }
}
