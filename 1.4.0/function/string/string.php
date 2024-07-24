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

        global $CHARACTERS;

        if (!empty($str)) {

            $str = htmlspecialchars_decode($str, ENT_QUOTES | ENT_HTML5);
            $str = str_replace('<br />', '', $str);

            foreach ($CHARACTERS as $k => $c) {
                        
                $character = $c['character'];
                $html = $c['html'];

                if (!in_array($character, ['"', ">", "<", " ", "&"])) {
                    $str = str_replace($html, $character, $str);
                }
                
            }

        }

        return $str;

    }

    function printPDF($str, $upper = false){

        if (!empty($str)) {

            $str = str_replace('<br> ', "\n", $str);
            $str = str_replace('<br>', "\n", $str);

            if ($upper == true) {

                $str = strip_tags($str);
                $str = html_entity_decode(strtolower($str), ENT_QUOTES | ENT_HTML5);

                $str = str_replace('à', 'À', $str);
                $str = str_replace('è', 'È', $str);
                $str = str_replace('ì', 'Ì', $str);
                $str = str_replace('ò', 'Ò', $str);
                $str = str_replace('ù', 'Ù', $str);
                
                $str = strtoupper($str);

            } else {

                $str = strip_tags($str);
                $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5);

            }

            $str = iconv('UTF-8', 'windows-1252', $str);

        }
        
        return $str;

    }

    function code($lenght, $type = 'all', $first = null){

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

        $PRETTIFY = new Wonder\Plugin\Custom\Prettify;

        return $PRETTIFY->Phone( $number );

    }

    function prettyDate($date, $hours = false) {

        $PRETTIFY = new Wonder\Plugin\Custom\Prettify;

        return $PRETTIFY->Date( $date, $hours );

    }

    function prettyAddress($street, $number, $cap, $city, $province, $country, $more = "", $name = "", $surname = "", $phone = "") {

        $PRETTIFY = new Wonder\Plugin\Custom\Prettify;

        return $PRETTIFY->Address( $street, $number, $cap, $city, $province, $country, $more, $name, $surname, $phone );

    }

    function prettyPrint($array) { 

        global $ROOT;

        $bt = debug_backtrace();
        $caller = array_shift($bt);

        echo 'File <b>'.str_replace($ROOT, '', $caller['file']).'</b> line <b>'.$caller['line'].'</b>';
        echo "<pre>".print_r($array, true)."</pre>";

    }
    
    function getDomain($domain) {

        $domain = isset(parse_url($domain)['host']) ? parse_url($domain)['host'] : $domain;
        $domain = str_replace('www.', '', $domain);

        return $domain;

    }

    function getDomainExtension($domain) {

        $explode = explode(".", $domain);
        $extension = count($explode) > 1 ? end($explode) : "";

        return $extension;

    }