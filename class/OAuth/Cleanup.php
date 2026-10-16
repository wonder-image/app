<?php

namespace Wonder\OAuth;

use Wonder\OAuth\Store\OAuthStore;

/** Pulizia notturna: soglie fisse (7 giorni token, 30 giorni client), scadenza grant da Config. */
final class Cleanup
{
    public static function run(OAuthStore $store, int $now): array
    {
        $date = static fn (int $t): string => date('Y-m-d H:i:s', $t);

        // Prima la revoca: i grant appena revocati non tengono in vita i loro client.
        $grants = $store->revokeGrantsConsentedBefore($date($now - Config::grantMaxAge()), $date($now));

        return [
            'grants' => $grants,
            'tokens' => $store->purgeTokens($date($now - 7 * 86400)),
            'clients' => $store->purgeIdleClients($date($now - 30 * 86400)),
        ];
    }
}
