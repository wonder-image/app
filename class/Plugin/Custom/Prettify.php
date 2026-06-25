<?php

namespace Wonder\Plugin\Custom;

use Wonder\Support\Prettify\Address as SupportAddress;
use Wonder\Support\Prettify\Date as SupportDate;
use Wonder\Support\Prettify\Phone as SupportPhone;

/**
 * DEPRECATO: usa Wonder\Support\Prettify\Date|Phone|Address.
 */
class Prettify
{
    public static function Phone($number): string
    {
        return SupportPhone::prettify($number);
    }

    public static function Date($date, $hours = false): string
    {
        return SupportDate::prettify($date, $hours);
    }

    public static function Address($street, $number, $cap, $city, $province, $country, $more = "", $name = "", $surname = "", $phone = ""): object
    {
        return SupportAddress::prettify($street, $number, $cap, $city, $province, $country, $more, $name, $surname, $phone);
    }
}
