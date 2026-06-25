<?php

    namespace Wonder\Plugin\Google\Merchant;

    class Product extends Item {

        public function __construct( $id, $name, $price, $link ) 
        {

            parent::__construct($id, $price);
            
            $this->schema('title', $name);
            $this->schema('description', $name);
            $this->schema('link', $link);
            $this->schema('availability', 'in_stock');
            $this->schema('condition', 'new');
            $this->schema('adult', 'no');

        }

        public function images( array $images ): Product 
        {

            $nImages = 0;
            foreach ($images as $image) {

                switch ($nImages) {
                    case 0:
                        $this->schema('image_link', $image);
                        break;
                    default:
                        $this->schemaPush('additional_image_link', $image);
                        break;
                }

                $nImages++;

            }

            return $this;

        }

        public function description( $value ): Product 
        {

            if (empty($value)) {
                return $this;
            } else {
                return $this->schema('description', (strlen($value) > 5000) ? substr($value, 0, 5000) : $value);
            }

        }

        public function highlight( $value ): Product 
        {

            return $this->schema('product_highlight', (strlen($value) > 100) ? substr($value, 0, 100) : $value);

        }

        # https://support.google.com/merchants/answer/12471624
        public function unit( $value, $unit ): Product 
        {

            return $this->schema('unit_pricing_measure', $this->number($value)." $unit");

        }

        # https://support.google.com/merchants/answer/12471624
        public function unitBase( $value, $unit ): Product 
        {

            return $this->schema('unit_pricing_base_measure', $this->number($value)." $unit");

        }

        public function brand( $value ): Product 
        {

            return $this->schema('brand', $value);

        }

        public function gtin( $value ): Product 
        {
            
            $this->schema('identifier_exists', empty($value) ? 'no' : 'yes');

            return $this->schema('gtin', $value);
           
        }

        public function mpn( $value ): Product 
        {

            $this->schema('identifier_exists', empty($value) ? 'no' : 'yes');

            return $this->schema('mpn', $value);
           
        }

        public function adult( bool $value ): Product 
        {

            return $this->schema('adult', $value ? 'yes' : 'no');
           
        }

        # https://support.google.com/merchants/answer/12472028
        public function ageGroup( $value ): Product
        {

            if (in_array($value, [ 'newborn', 'infant', 'toddler', 'kids', 'adult' ])) {
                return $this->schema('age_group', $value);
            } else {
                throw new \InvalidArgumentException("Age group '$value' non valida. Valori accettati: newborn, infant, toddler, kids, adult.");
            }

        }

        public function color( $value ): Product 
        {

            return $this->schema('color', $value);

        }

        public function material( $value ): Product 
        {

            return $this->schema('material', $value);

        }
        
        public function pattern( $value ): Product 
        {

            return $this->schema('pattern', $value);

        }

        public function gender( $value ): Product 
        {

            if (in_array($value, [ 'male', 'female', 'unisex' ])) {
                return $this->schema('gender', $value);
            } else {
                throw new \InvalidArgumentException("Gender '$value' non valida. Valori accettati: male, female, unisex.");
            }

        }

        # https://support.google.com/merchants/answer/12471627?sjid=10386446160149238429-NC
        public function size ($value ): Product 
        {

            return $this->schema('size', $value);

        }

        # https://support.google.com/merchants/answer/12471628
        public function sizeType( $value ): Product
        {

            if (in_array($value, [ 'regular', 'petite', 'maternity', 'big', 'tall', 'plus' ])) {
                return $this->schema('size_type', $value);
            } else {
                throw new \InvalidArgumentException("Size type '$value' non valida. Valori accettati: regular, petite, maternity, big, tall, plus.");
            }

        }

        # https://support.google.com/merchants/answer/12472828
        public function sizeSystem( $value ): Product
        {

            if (in_array($value, [ 'US', 'UK', 'UE', 'DE', 'FR', 'JP', 'CN', 'IT', 'BR', 'MEX', 'AU' ])) {
                return $this->schema('size_system', $value);
            } else {
                throw new \InvalidArgumentException("Size system '$value' non valida. Valori accettati: US, UK, UE, DE, FR, JP, CN, IT, BR, MEX, AU.");
            }

        }

        # https://support.google.com/merchants/answer/12472646?sjid=10386446160149238429-NC
        public function itemGroupId( $value ): Product 
        {

            return $this->schema('item_group_id', $value);

        }

        public function dimension( $weight, $length = null, $width = null, $height = null )
        {

            if (!empty($weight)) { $this->schema('product_weight', $this->number($weight).' g'); }
            if (!empty($length)) { $this->schema('product_length', $this->number($length).' cm'); }
            if (!empty($width)) { $this->schema('product_width', $this->number($width).' cm'); }
            if (!empty($height)) { $this->schema('product_height', $this->number($height).' cm'); }

            return $this;

        }

        public function condition( $value ): Product
        {

            if (in_array($value, [ 'new', 'refurbished', 'used' ])) {
                return $this->schema('condition', $value);
            } else {
                throw new \InvalidArgumentException("Condition '$value' non valida. Valori accettati: new, refurbished, used.");
            }

        }

        public function energyClass( $value ): Product
        {

            if (in_array($value, [ 'A+++', 'A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G' ])) {
                return $this->schema('energy_efficiency_class', $value);
            } else {
                throw new \InvalidArgumentException("Classe energetica non valida '$value' non valida. Valori accettati: A+++, A++, A+, A, B, C, D, E, F, G.");
            }

        }

        // Dettagli spedizione

            public function shipping( $country, $price, $minHandling = 1, $maxHandling = 3, $service = "Standard"): Product
            {

                return $this->schema('shipping', [ 
                    'country' => $country,
                    'service' => $service,
                    'price' => $this->number($price)." {$this->currency}",
                    'min_handling_time' => $minHandling,
                    'max_handling_time' => $maxHandling   
                ]);

            }

            public function shippingDimension( $weight, $length = null, $width = null, $height = null ): Product
            {

                if (!empty($weight)) { $this->schema('shipping_weight', $this->number($weight).' g'); }
                if (!empty($length)) { $this->schema('shipping_length', $this->number($length).' cm'); }
                if (!empty($width)) { $this->schema('shipping_width', $this->number($width).' cm'); }
                if (!empty($height)) { $this->schema('shipping_height', $this->number($height).' cm'); }

                return $this;

            }

        // 

        public function checkSchema(): bool 
        {

            if (empty($this->getSchema('image_link'))) {

                throw new \InvalidArgumentException("È necessaria almeno un immagine per poter caricare il prodotto su Google Mercant Center");

            }

            if (empty($this->getSchema('brand'))) {

                throw new \InvalidArgumentException("È necessaria specificare un brand per poter caricare il prodotto su Google Mercant Center");

            }

            return true;

        }

    }
    