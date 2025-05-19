<?php

    namespace Wonder\Plugin\Custom\String;

    class Address {

        /**
         * Summary of analyze
         * Divide l'indirizzo dal numero civico
         * @param mixed $address
         * @return object
         */
        public static function analyze($address) {

            // Pulizia iniziale
            $input = trim(preg_replace('/\s+/', ' ', str_replace(',', '', $address)));

            // Pattern civico accettato (composto o semplice)
            $numberRegex = '(?:\d+[\/]?[A-Za-z]?|SNC|snc|\d+\s?(?:bis|ter|quater)?[\/]?[A-Za-z]?)';

            // 1. Civico alla fine
            if (preg_match('/^(.*\D)\s(' . $numberRegex . ')$/u', $input, $matches)) {
                return (object) [
                    'street' => trim($matches[1]),
                    'number' => self::normalizeNumber(trim($matches[2])),
                ];
            }

            // 2. Civico all’inizio
            if (preg_match('/^(' . $numberRegex . ')\s+(.*)$/u', $input, $matches)) {
                return (object) [
                    'street' => trim($matches[2]),
                    'number' => self::normalizeNumber(trim($matches[1])),
                ];
            }

            // 3. Nessun civico trovato
            return (object) [
                'street' => $input,
                'number' => '',
            ];

        }

        public static function normalizeNumber($number) {

            $number = trim($number);
            
            // Lascia invariato se contiene uno slash (es. 25/A, 25bis/B)
            if (strpos($number, '/') !== false) {
                return strtoupper($number);
            }

            // Unifica numeri + lettere e rimuove spazi interni (es. 25 A → 25A, 25 bis → 25bis)
            $number = strtolower($number);
            $number = preg_replace('/(\d+)\s+([a-z]+)/', '$1$2', $number);

            return strtoupper($number === 'snc' ? 'SNC' : $number);

        }
        
    }