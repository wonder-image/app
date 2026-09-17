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

            $RETURN->number = substr($number, strlen($RETURN->prefix));
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
                $prefixLength = 4;

                if ($analyze->prefix === '+39') {
                    if (preg_match('/^0[26]/', $analyze->number)) {
                        $prefixLength = 2;
                    } elseif (preg_match('/^0[13-57-9][0159]/', $analyze->number)) {
                        $prefixLength = 3;
                    }
                }

                $number = substr($analyze->number, 0, $prefixLength) . ' ' . substr($analyze->number, $prefixLength);
            } else {
                $number = trim(substr($analyze->number, 0, 3) . ' ' . substr($analyze->number, 3, 3) . ' ' . substr($analyze->number, 6));
            }

            if (!empty($analyze->prefix)) {
                $number = $analyze->prefix . ' ' . $number;
            }
        }

        return $number;
    }
}
