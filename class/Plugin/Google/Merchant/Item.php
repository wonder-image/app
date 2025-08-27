<?php

    namespace Wonder\Plugin\Google\Merchant;

    use Wonder\Concerns\HasSchema;
    use DateTime, DateTimeZone;

    /**
     * 
     * Per i prodotti nella categoria Abbigliamento e accessori (ID: 166) è necessario specificare: ageGroup, color, gender, size
     * 
     */
    abstract class Item {

        use HasSchema;

        public $currency = 'EUR';

        public function __construct( $id, $price ) 
        {

            $this->schema('id', $id);
            $this->schema('price', $this->number($price)." {$this->currency}");

        }
        
        protected function number( $value ) {

            return number_format($value, 2, '.', '');

        }

        protected function date( $date ) {

            $dt = new DateTime($date);
            // Imposta timezone UTC
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d\TH:i\Z');

        }

        public function salePrice( $value ): static 
        {
            
            return $this->schema('sale_price', $this->number($value)." {$this->currency}");

        }

        public function availability( $value, $dateAvailability = null ): static
        {

            if (in_array($value, [ 'in_stock', 'out_of_stock', 'pre-order', 'backorder' ])) {
                $this->schema('availability', $value);
            } else {
                throw new \InvalidArgumentException("Availability '$value' non valida. Valori accettati: in_stock, out_of_stock, pre-order, backorder.");
            }

            if (in_array($value, [ 'pre-order', 'backorder' ])) {
                
                if (empty($dateAvailability)) {
                    throw new \InvalidArgumentException("Per '$value' devi fornire una data di disponibilità.");
                }

                $this->schema('availability_date', $this->date($dateAvailability));
                    
            }

            return $this;

        }

        abstract public function checkSchema(): bool;
        
    }