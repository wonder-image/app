<?php

    namespace Wonder\Plugin\Custom;

    use Wonder\Plugin\Custom\Translator\TranslatorDate;
    use Wonder\Plugin\Custom\String\Phone;

    class Prettify {

        public static function Phone( $number ) { return Phone::prettify($number); }

        public static function Date( $date, $hours = false ) {

            if (!empty($date)) {
                
                $RETURN = date("d", strtotime($date)).' '.TranslatorDate::Month($date).' '.date("Y", strtotime($date));
                $RETURN .= $hours ? ' alle '.date("H:i", strtotime($date)) : '';

                return $RETURN;
        
            } else {

                return "";
                
            }
            
        }

        public static function Address($street, $number, $cap, $city, $province, $country, $more = "", $name = "", $surname = "", $phone = "") {

            $RETURN = (object) array();
    
            $addressMore = empty($more) ? "" : "<br>$more";
            $addressMorePDF = empty($more) ? "" : "\n$more";
    
            $number = empty($number) ? "" : " $number";
    
            $RETURN->line = "$street$number, $cap $city ($province)";
            $prettyPhone = empty($phone) ? "" : self::Phone($phone);
    
            if (!empty($name) && !empty($surname) && !empty($phone)) {
    
                $RETURN->pretty = "
                <b>$name $surname</b><br>
                $prettyPhone<br>
                $street$number, $cap <br>
                $city ($province)$addressMore";
    
                $RETURN->prettyPDF = "$name $surname\n$prettyPhone\n$street$number, $cap\n$city ($province)$addressMorePDF";
    
            } else if (!empty($name) && !empty($surname)) {
    
                $RETURN->pretty = "
                <b>$name $surname</b><br>
                $street$number, $cap <br>
                $city ($province)$addressMore";
    
                $RETURN->prettyPDF = "$name $surname\n$street$number, $cap\n$city ($province)$addressMore";
    
            } else if (!empty($name)) {
    
                $RETURN->pretty = "
                <b>$name</b><br>
                $street$number, $cap <br>
                $city ($province)$addressMore";
    
                $RETURN->prettyPDF = "$name\n$street$number, $cap\n$city ($province)$addressMore";
    
            } else if (!empty($street) && !empty($number) && !empty($cap) && !empty($city) && !empty($province)) {
    
                $RETURN->pretty = "
                $street$number, $cap <br>
                $city ($province)$addressMore";
    
                $RETURN->prettyPDF = "$street$number, $cap\n$city ($province)$addressMore";
    
            } else if (!empty($street) && !empty($cap) && !empty($city) && !empty($province)) {
                
                $RETURN->pretty = "
                $street, $cap <br>
                $city ($province)$addressMore";
    
                $RETURN->prettyPDF = "$street, $cap\n$city ($province)$addressMore";
    
            } else if (!empty($street) && !empty($city) && !empty($province)) {
                
                $RETURN->pretty = "$street, $city ($province)";
    
                $RETURN->prettyPDF = "$street, $city ($province)";
    
            } else {
                
                $RETURN->line = "--";
                $RETURN->pretty = "--";
                $RETURN->prettyPDF = "--";
    
            }
    
            return $RETURN;
        
        }

    }