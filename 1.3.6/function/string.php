<?php

    function create_link($str, $table = null, $column = null, $id = null){

        global $CHARACTERS;

        $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5);

        foreach ($CHARACTERS as $k => $c) {
                        
            $character = $c['character'];
            $html = $c['html'];
            $url = $c['url'];
            
            $str = str_replace($html, $url, $str);
            $str = str_replace($character, $url, $str);
            
        }
        
        $str = preg_replace('/[^A-Za-z0-9\-]/', ' ', $str);
        $str = trim($str);
        
        $str = str_replace('        ', ' ', $str); // 8 spazi
        $str = str_replace('       ', ' ', $str); // 7 spazi
        $str = str_replace('      ', ' ', $str); // 6 spazi
        $str = str_replace('     ', ' ', $str); // 5 spazi
        $str = str_replace('    ', ' ', $str); // 4 spazi
        $str = str_replace('   ', ' ', $str); // 3 spazi
        $str = str_replace('  ', ' ', $str); // 2 spazi
        $str = str_replace(' ', '-', $str); // 1 spazio

        $str = str_replace('----', '-', $str);
        $str = str_replace('---', '-', $str);
        $str = str_replace('--', '-', $str);

        if ($table != null && $column != null) {

            $found = false;
            $link = $str;

            for ($i=1; $i > 0 && !$found; $i++) {

                $QUERY = ($id != null) ? "`id` != '$id' AND " : "";

                if (sqlColumnExists($table, 'deleted')) {

                    $QUERY .= "`deleted` = 'false' AND ";

                } 

                $QUERY .= "`$column` = '$link'";

                $SQL = sqlSelect($table, $QUERY);

                if ($SQL->exists) {
                    $link = $str.'-'.$i;
                }else{
                    $found = true;
                }
                
            }

            $str = $link;

        }

        return $str;

    }

    function create_number($str, $decimals = 0) {

        $str = ($str == '' && $str != 0) ? '' : str_replace('€', '', $str);
        $str = ($str == '' && $str != 0) ? '' : str_replace('%', '', $str);
        $str = ($str == '' && $str != 0) ? '' : str_replace(',', '.', $str);
        $str = ($str == '' && $str != 0) ? '' : number_format($str, $decimals, '.', '');

        return $str;

    }

    function unique($str, $table, $column, $id = null) {

        $unique = true;

        if ($id != null) {
            $QUERY = "`id` != '$id' AND ";
        }else{
            $QUERY = "";
        }
    
        if (sqlColumnExists($table, 'deleted')) {

            $QUERY .= "`deleted` = 'false' AND ";

        } 

        $QUERY .= "`$column` = '$str'";

        $SQL = sqlSelect($table, $QUERY);

        if ($SQL->exists) { $unique = false; }

        return $unique;

    }

    function sanitize($str){
        
        global $CHARACTERS;
        
        if (!empty($str)) {

            foreach ($CHARACTERS as $k => $c) {
                        
                $character = $c['character'];
                $html = $c['html'];

                if (!in_array($character, ['"', ">", "<", " ", "&"])) {
                    $str = str_replace($character, $html, $str);
                }
                
            }

            $str = trim($str);
            $str = addslashes($str);

        }

        return $str;
        
    }    

    function sanitizeJSON($array) {

        global $CHARACTERS;

        $newArray = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {

                $newArray[$key] = sanitizeJSON($value);

            } else {

                if (!empty($value)) {

                    $value = str_replace(["\r\n", "\n\r", "\n", "\r"], "",  $value);
                    $value = preg_replace('/(<br>)+$/', '', $value);
                    
                    foreach ($CHARACTERS as $k => $c) {
                        
                        $character = $c['character'];
                        $html = $c['html'];

                        if (!in_array($character, ['"', ">", "<", " ", "&"])) {
                            $value = str_replace($character, $html, $value);
                        }
                        
                    }

                    $value = trim($value);
                    $value = addslashes($value);

                }

                $newArray[$key] = $value;
                
            }
        }

        return $newArray;
        
    }

    function sanitizeFirst($str){

        if (!empty($str)) {
            $str = strtolower($str);
            $str = ucwords($str);
            $str = sanitize($str);
        }
        
        return $str;
        
    }

    function sanitizeEcho($str) {

        if (!empty($str)) {
            $str = htmlspecialchars_decode($str, ENT_QUOTES);
            $str = str_replace('<br />', '', $str);
        }

        return $str;

    }

    function printPDF($str, $upper = false){

        if (!empty($str)) {

            if ($upper == true) {

                $str = html_entity_decode(strtolower($str), ENT_QUOTES | ENT_HTML5);

                $str = str_replace('à', 'À', $str);
                $str = str_replace('è', 'È', $str);
                $str = str_replace('ì', 'Ì', $str);
                $str = str_replace('ò', 'Ò', $str);
                $str = str_replace('ù', 'Ù', $str);
                
                $str = strtoupper($str);

            } else {

                $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5);

            }

            $str = iconv('UTF-8', 'windows-1252', $str);

        }
        
        return $str;

    }


    function code($lenght, $type, $first = null){

        if($type == 'all'){
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        }

        if($type == 'numbers'){
            $characters = '123456789';
        }

        if($type == 'letters'){
            $characters = 'ABCDEFGHIJKLMNPQRSTUVWXYZ';
        }

        $code = '';
        for ($i = 0; $i < $lenght; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }

        if(!empty($first)){
            $code = "$first$code";
        }

        return $code;

    }

    function prettyPhone($number) {

        if (!empty($number)) {
            
            $number = str_replace(" ", "", $number);

            if (substr($number, 0, 3) == '+39' && substr($number, 0, 3) != '039') {
                return substr($number, 0, 3).' '.substr($number, 3, 3).' '.substr($number, 6, 3).' '.substr($number, 9, 4);
            } else {
                return substr($number, 0, 3).' '.substr($number, 3, 3).' '.substr($number, 6, 4);
            }

        } else {
            return "";
        }

    }

    function prettyDate($date, $hours = false) {
        
        $RETURN = date("d", strtotime($date)).' '.translateDate($date, 'month').' '.date("Y", strtotime($date));
        $RETURN .= $hours ? ' alle '.date("H:i", strtotime($date)) : '';

        return $RETURN;

    }

    function prettyAddress($street, $number, $cap, $city, $province, $country, $more = "", $name = "", $surname = "", $phone = "") {

        $RETURN = (object) array();

        $addressMore = empty($more) ? "" : "<br>$more";
        $addressMorePDF = empty($more) ? "" : "\n$more";

        $RETURN->line = "$street $number, $cap $city ($province)";
        $prettyPhone = empty($phone) ? "" : prettyPhone($phone);

        if (!empty($name) && !empty($surname) && !empty($phone)) {

            $RETURN->pretty = "
            <b>$name $surname</b><br>
            $prettyPhone<br>
            $street $number, $cap <br>
            $city ($province)$addressMore";

            $RETURN->prettyPDF = "$name $surname\n$prettyPhone\n$street $number, $cap\n$city ($province)$addressMorePDF";

        } else if (!empty($name) && !empty($surname)) {

            $RETURN->pretty = "
            <b>$name $RETURN->surname</b><br>
            $street $number, $cap <br>
            $city ($province)$addressMore";

            $RETURN->prettyPDF = "$name $surname\n$street $number, $cap\n$city ($province)$addressMore";

        } else if (!empty($name)) {

            $RETURN->pretty = "
            <b>$name</b><br>
            $street $number, $cap <br>
            $city ($province)$addressMore";

            $RETURN->prettyPDF = "$name\n$street $number, $cap\n$city ($province)$addressMore";

        } else if (!empty($street) && !empty($number) && !empty($cap) && !empty($city) && !empty($province)) {

            $RETURN->pretty = "
            $street $number, $cap <br>
            $city ($province)$addressMore";

            $RETURN->prettyPDF = "$street $number, $cap\n$city ($province)$addressMore";

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

    function prettyPrint($array) {

        print("<pre>".print_r($array,true)."</pre>");

    }
    
?>