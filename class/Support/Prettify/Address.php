<?php

namespace Wonder\Support\Prettify;

class Address
{
    public static function prettify($street, $number, $cap, $city, $province, $country, $more = "", $name = "", $surname = "", $phone = ""): object
    {
        $return = (object) [];

        $addressMore = empty($more) ? "" : "<br>$more";
        $addressMorePDF = empty($more) ? "" : "\n$more";

        $number = empty($number) ? "" : " $number";

        $return->line = "$street$number, $cap $city ($province)";
        $prettyPhone = empty($phone) ? "" : Phone::prettify($phone);

        if (!empty($name) && !empty($surname) && !empty($phone)) {
            $return->pretty = "
                <b>$name $surname</b><br>
                $prettyPhone<br>
                $street$number, $cap <br>
                $city ($province)$addressMore";

            $return->prettyPDF = "$name $surname\n$prettyPhone\n$street$number, $cap\n$city ($province)$addressMorePDF";
        } elseif (!empty($name) && !empty($surname)) {
            $return->pretty = "
                <b>$name $surname</b><br>
                $street$number, $cap <br>
                $city ($province)$addressMore";

            $return->prettyPDF = "$name $surname\n$street$number, $cap\n$city ($province)$addressMore";
        } elseif (!empty($name)) {
            $return->pretty = "
                <b>$name</b><br>
                $street$number, $cap <br>
                $city ($province)$addressMore";

            $return->prettyPDF = "$name\n$street$number, $cap\n$city ($province)$addressMore";
        } elseif (!empty($street) && !empty($number) && !empty($cap) && !empty($city) && !empty($province)) {
            $return->pretty = "
                $street$number, $cap <br>
                $city ($province)$addressMore";

            $return->prettyPDF = "$street$number, $cap\n$city ($province)$addressMore";
        } elseif (!empty($street) && !empty($cap) && !empty($city) && !empty($province)) {
            $return->pretty = "
                $street, $cap <br>
                $city ($province)$addressMore";

            $return->prettyPDF = "$street, $cap\n$city ($province)$addressMore";
        } elseif (!empty($street) && !empty($city) && !empty($province)) {
            $return->pretty = "$street, $city ($province)";
            $return->prettyPDF = "$street, $city ($province)";
        } else {
            $return->line = "--";
            $return->pretty = "--";
            $return->prettyPDF = "--";
        }

        return $return;
    }
}
