<?php

    namespace Wonder\Plugin\Custom;

    use Wonder\Plugin\Custom\Translator\Date;

    class Prettify {

        public function Phone( $number ) {

            if (!empty($number)) {
            
                $number = str_replace(" ", "", $number);
    
                if (substr($number, 0, 1) == '+') {
                    return substr($number, 0, 3).' '.substr($number, 3, 3).' '.substr($number, 6, 3).' '.substr($number, 9, 4);
                } else {
                    return substr($number, 0, 3).' '.substr($number, 3, 3).' '.substr($number, 6, 4);
                }
    
            } else {
    
                return "";
                
            }

        }

        public function Date( $date, $hours = false ) {

            $month = new Date();

            $RETURN = date("d", strtotime($date)).' '.$month->Month($date).' '.date("Y", strtotime($date));
            $RETURN .= $hours ? ' alle '.date("H:i", strtotime($date)) : '';

            return $RETURN;
            
        }

        public function Address($street, $number, $cap, $city, $province, $country, $more = "", $name = "", $surname = "", $phone = "") {

            $RETURN = (object) array();
    
            $addressMore = empty($more) ? "" : "<br>$more";
            $addressMorePDF = empty($more) ? "" : "\n$more";
    
            $number = empty($number) ? "" : " $number";
    
            $RETURN->line = "$street$number, $cap $city ($province)";
            $prettyPhone = empty($phone) ? "" : $this->Phone($phone);
    
            if (!empty($name) && !empty($surname) && !empty($phone)) {
    
                $RETURN->pretty = "
                <b>$name $surname</b><br>
                $prettyPhone<br>
                $street$number, $cap <br>
                $city ($province)$addressMore";
    
                $RETURN->prettyPDF = "$name $surname\n$prettyPhone\n$street$number, $cap\n$city ($province)$addressMorePDF";
    
            } else if (!empty($name) && !empty($surname)) {
    
                $RETURN->pretty = "
                <b>$name $RETURN->surname</b><br>
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