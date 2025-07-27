<?php

    namespace Wonder\Plugin\Stripe;

    use Wonder\Plugin\Stripe\Stripe;

    class PaymentMethodConfigurations extends Stripe {

        public static function object()
        {
            return parent::connect()->paymentMethodConfigurations;

        }

        public function all() {

            return self::object()->all([], $this->opts);

        }

        public function getActive() 
        {

            $collection = $this->all();

            // Prendi la configurazione attiva
            $activeConfigs = array_filter($collection->data, fn($config) => !empty($config->active));;

            foreach ($activeConfigs as $key => $activeConfig) {
                
                $configArray = $activeConfig->toArray();

                foreach ($configArray as $method => $config) {
                    if (is_array($config) && !empty($config['available'])) {
                        $activePayments[] = $method;
                    }
                }

            }
            
            return array_values(array_unique($activePayments));

        }

    }