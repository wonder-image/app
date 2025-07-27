<?php

    namespace Wonder\Plugin\Stripe;

    use Wonder\App\Path;
    use Wonder\Plugin\Stripe\Stripe;

    class Accounts extends Stripe {
        
        public static function object()
        {
            return parent::connect()->accounts;

        }

        public static function get($accountId) {

            return self::object()->retrieve($accountId);

        }

        // [ https://docs.stripe.com/api/account_links/create ]
        public static function onboarding($accountId) {

            return parent::connect()->accountLinks->create([
                'account' => $accountId,
                'refresh_url' => (new Path)->appApi.'/stripe/account/onboarding.php',
                'return_url' => (new Path)->appApi.'/stripe/account/checkOnboarding.php',
                'type' => 'account_onboarding',
            ]);

        }

        public static function webhook(string $accountId, string $url, array $events) {

            return parent::connect()->webhookEndpoints->create([
                'url' => $url,
                'enabled_events' => $events,
            ], [
                'stripe_account' => $accountId
            ]);

        }

        // [ https://docs.stripe.com/api/accounts/create ]
        public static function create(array $params = []) {

            return self::object()->create($params);

        }


        // [ https://docs.stripe.com/api/accounts/update ]
        public static function update(string $accountId, array $params = []) {

            return self::object()->update($accountId, $params);
            
        }


        // [ https://docs.stripe.com/api/accounts/delete ]
        public static function delete($accountId) {
            
            return self::object()->delete($accountId);

        }

    }
