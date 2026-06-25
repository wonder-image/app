<?php

    namespace Wonder\Plugin\Stripe;

    use Wonder\Plugin\Stripe\Stripe;
    
    class TaxRate extends Stripe {

        public function object()
        {
            return parent::connect()->taxRates;

        }

        public function create() {

            return $this->object()->create($this->params, $this->opts);

        }

        public function update($taxRateId) {

            return $this->object()->update($taxRateId, $this->params, $this->opts);

        }

        public function get($taxRateId) {
            
            return $this->object()->retrieve($taxRateId, $this->params, $this->opts);

        }

        # https://docs.stripe.com/api/tax_rates/create?api-version=2025-04-30.basil#create_tax_rate-display_name
        public function displayName($value) {

            return $this->addParams('display_name', $value);

        }

        # https://docs.stripe.com/api/tax_rates/create?api-version=2025-04-30.basil#create_tax_rate-inclusive
        public function inclusive(bool $value = true) {

            return $this->addParams('inclusive', $value);

        }

        public function description($value) {

            return $this->addParams('description', $value);

        }

        # https://docs.stripe.com/api/tax_rates/create?api-version=2025-04-30.basil#create_tax_rate-percentage
        public function percentage(float $value) {

            return $this->addParams('percentage', $value);

        }

    }