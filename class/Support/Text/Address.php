<?php

namespace Wonder\Support\Text;

class Address
{
    /**
     * Divide l'indirizzo dal numero civico.
     */
    public static function analyze($address): object
    {
        $input = trim(preg_replace('/\s+/', ' ', str_replace(',', '', $address)));

        $numberRegex = '(?:\d+[\/]?[A-Za-z]?|SNC|snc|\d+\s?(?:bis|ter|quater)?[\/]?[A-Za-z]?)';

        if (preg_match('/^(.*\D)\s(' . $numberRegex . ')$/u', $input, $matches)) {
            return (object) [
                'street' => trim($matches[1]),
                'number' => self::normalizeNumber(trim($matches[2])),
            ];
        }

        if (preg_match('/^(' . $numberRegex . ')\s+(.*)$/u', $input, $matches)) {
            return (object) [
                'street' => trim($matches[2]),
                'number' => self::normalizeNumber(trim($matches[1])),
            ];
        }

        return (object) [
            'street' => $input,
            'number' => '',
        ];
    }

    public static function normalizeNumber($number): string
    {
        $number = trim($number);

        if (strpos($number, '/') !== false) {
            return strtoupper($number);
        }

        $number = strtolower($number);
        $number = preg_replace('/(\d+)\s+([a-z]+)/', '$1$2', $number);

        return strtoupper($number === 'snc' ? 'SNC' : $number);
    }
}
