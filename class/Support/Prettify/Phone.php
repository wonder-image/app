<?php

namespace Wonder\Support\Prettify;

class Phone
{
    public static string $defaultCountryCode = '+39';

    public static function format($number)
    {
        $number = preg_replace('/[\s\.\-]/', '', $number);

        if (str_starts_with($number, '+')) {
            return $number;
        }

        if (str_starts_with($number, '00')) {
            return '+' . substr($number, 2);
        }

        if (str_starts_with($number, '0')) {
            return self::$defaultCountryCode . $number;
        }

        return self::$defaultCountryCode . $number;
    }

    public static function analyze($number)
    {
        $RETURN = (object) [];
        $RETURN->prefix = '';
        $RETURN->country = '';
        $RETURN->number = '';

        if (!empty($number)) {
            $number = self::format($number);

            $RETURN = (object) [];
            $RETURN->prefix = '';
            $RETURN->country = '';

            foreach (countriesPhonePrefix() as $country => $prefix) {
                $x = substr($number, 0, strlen($prefix));
                if ($x == $prefix) {
                    $RETURN->prefix = $prefix;
                    $RETURN->country = $country;
                    break;
                }
            }

            $RETURN->number = str_replace($RETURN->prefix, '', $number);
        }

        return $RETURN;
    }

    public static function prettify($number)
    {
        if (!empty($number)) {
            $analyze = self::analyze($number);

            $number = '';

            if (strlen($analyze->number) <= 4) {
                $number = $analyze->number;
            } elseif (substr($analyze->number, 0, 1) == '0') {
                $number = substr($analyze->number, 0, 4) . ' ' . substr($analyze->number, 4, 6);
            } else {
                $number = substr($analyze->number, 0, 3) . ' ' . substr($analyze->number, 3, 3) . ' ' . substr($analyze->number, 6, 4);
            }

            if (!empty($analyze->prefix)) {
                $number = $analyze->prefix . ' ' . $number;
            }
        }

        return $number;
    }
}
