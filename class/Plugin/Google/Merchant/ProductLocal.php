<?php

    namespace Wonder\Plugin\Google\Merchant;

    class ProductLocal extends Item {

        public function __construct( $id, $price, $storeCode ) 
        {

            parent::__construct($id, $price);
            
            $this->schema('store_code', $storeCode);
            $this->schema('availability', 'in_stock');
            $this->schema('pickup_method', 'buy');
            $this->schema('pickup_sla', 'same_day');

        }

        # https://support.google.com/merchants/answer/14634021
        public function pickupMethod( $value ): self
        {

            if (in_array($value, [ 'buy', 'reserve' ])) {
                return $this->schema('pickup_method', $value);
            } else {
                throw new \InvalidArgumentException("Pickup method '$value' non valido. Valori accettati: buy, reserve.");
            }

        }

        # https://support.google.com/merchants/answer/14635400
        public function pickupSLA( $value ): self
        {

            if (in_array($value, [ 'same_day', 'next_day', '2-day', '3-day', '4-day', '5-day', '6-day', 'multi-week' ])) {
                return $this->schema('pickup_method', $value);
            } else {
                throw new \InvalidArgumentException("Pickup SLA '$value' non valido. Valori accettati: same_day, next_day, 2-day, 3-day, 4-day, 5-day, 6-day, multi-week.");
            }

        }

        public function checkSchema(): bool 
        {

            return true;

        }

    }