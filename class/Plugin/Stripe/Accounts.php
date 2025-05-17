<?php

    namespace Wonder\Plugin\Stripe;

    use Wonder\App\Path;
    use Wonder\Plugin\Stripe\Stripe;

    class Accounts {

        public static $apiKey = null;
        
        public static function get($accountId) {

            return (new Stripe(self::$apiKey))->accounts->retrieve($accountId);

        }

        public static function apiKey($apiKey) {
            self::$apiKey = $apiKey;
        }

        // [ https://docs.stripe.com/api/account_links/create ]
        public static function onboarding($accountId) {

            return (new Stripe(self::$apiKey))->accountLinks->create([
                'account' => $accountId,
                'refresh_url' => (new Path)->appApi.'/stripe/account/onboarding.php',
                'return_url' => (new Path)->appApi.'/stripe/account/checkOnboarding.php',
                'type' => 'account_onboarding',
            ]);

        }

        public static function webhook(string $accountId, string $url, array $events) {

            return (new Stripe(self::$apiKey))->webhookEndpoints->create([
                'url' => $url,
                'enabled_events' => $events,
            ], [
                'stripe_account' => $accountId
            ]);

        }

        // [ https://docs.stripe.com/api/accounts/create ]
        public static function create(array $params = []) {

            return (new Stripe(self::$apiKey))->accounts->create($params);

        }


        // [ https://docs.stripe.com/api/accounts/update ]
        public static function update(string $accountId, array $params = []) {

            return (new Stripe(self::$apiKey))->accounts->update($accountId, $params);
            
        }


        // [ https://docs.stripe.com/api/accounts/delete ]
        public static function delete($accountId) {
            
            return (new Stripe(self::$apiKey))->accounts->delete($accountId);

        }

    }
