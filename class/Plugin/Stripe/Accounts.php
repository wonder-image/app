<?php

    namespace Wonder\Plugin\Stripe;

    use Wonder\App\Path;
    use Wonder\Plugin\Stripe\Stripe;

    class Accounts extends Stripe {
        
        # Senza chiave usano quella dell'ambiente attivo: per collegare l'altro ambiente va passata.
        public static function object(?string $apiKey = null)
        {
            return parent::connect($apiKey)->accounts;

        }

        public static function get($accountId, ?string $apiKey = null) {

            return self::object($apiKey)->retrieve($accountId);

        }

        // [ https://docs.stripe.com/api/account_links/create ]
        public static function onboarding($accountId, ?string $apiKey = null, string $account = 'production') {

            return parent::connect($apiKey)->accountLinks->create([
                'account' => $accountId,
                // Il link scaduto riparte dallo stesso ambiente, non dalla produzione.
                'refresh_url' => (new Path)->appApi.'/service/stripe/onboarding/?account='.($account === 'test' ? 'test' : 'production'),
                'return_url' => (new Path)->appApi.'/service/stripe/onboarding/check/',
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
        public static function create(array $params = [], ?string $apiKey = null) {

            return self::object($apiKey)->create($params);

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
