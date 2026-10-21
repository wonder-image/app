<?php

namespace Wonder\Frontend\Support;

use Throwable;
use Wonder\App\Credentials;

/**
 * Dati del `dataLayer` emessi dal layout frontend prima di Google Tag Manager.
 */
final class DataLayer
{
    /**
     * Consent Mode: tutto negato finché la CMP (Iubenda in GTM) non aggiorna.
     * `wait_for_update`: i tag Google aspettano fino a 500 ms l'update.
     */
    public const CONSENT_DEFAULT = [
        'ad_storage' => 'denied',
        'analytics_storage' => 'denied',
        'ad_user_data' => 'denied',
        'ad_personalization' => 'denied',
        'wait_for_update' => 500,
    ];

    /**
     * Id utente per il dataLayer: HMAC con `APP_KEY`, mai l'id in chiaro.
     * Null senza utente o senza `APP_KEY`.
     */
    public static function userId(int|string|null $userId, ?string $key = null): ?string
    {
        $userId = (int) $userId;

        if ($userId <= 0) {
            return null;
        }

        try {
            $key ??= Credentials::appKey();
        } catch (Throwable) {
            return null;
        }

        return $key === '' ? null : substr(hash_hmac('sha256', 'datalayer-user|'.$userId, $key), 0, 32);
    }

    /** Script inline: `gtag('consent','default',…)` e utente, da mettere prima del loader GTM. */
    public static function bootstrapScript(int|string|null $userId, ?string $key = null): string
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;

        return "window.dataLayer = window.dataLayer || [];\n"
            ."function gtag(){dataLayer.push(arguments);}\n"
            ."gtag('consent', 'default', ".json_encode(self::CONSENT_DEFAULT, $flags).");\n"
            .'window.dataLayer.push('.json_encode(['user' => ['id' => self::userId($userId, $key)]], $flags).");\n";
    }
}
