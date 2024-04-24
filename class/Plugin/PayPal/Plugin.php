<?php

    namespace Wonder\Plugin\PayPal;
    
    class Plugin {

        public static function Address( string $countryCode, string $postalCode, string $adminArea1, string $adminArea2, string $addressLine1, string $addressLine2 = null ):array {

            $address = [];
            $address['country_code'] = strtoupper($countryCode);
            $address['postal_code'] = $postalCode;
            $address['admin_area_1'] = $adminArea1;
            $address['admin_area_2'] = $adminArea2;
            $address['address_line_1'] = $addressLine1;

            if (!empty($addressLine2)) { $address['address_line_2'] = $addressLine2; }

            return $address;

        }

    }