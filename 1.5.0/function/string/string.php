<?php

    function create_link($str, $table = null, $column = null, $id = null): string
    {

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

    function create_number($str, $decimals = 0) 
    {

        if (!empty($str)) {
            $str = ($str == '' && $str != 0) ? 0 : str_replace([ '€', '%', ',' ], '', $str);
        } else {
            $str = 0;
        }

        return number_format($str, $decimals, '.', '');

    }

    function unique($str, $table, $column, $id = null): bool 
    {

        $unique = true;

        $QUERY = ($id != null) ? "`id` != '$id' AND " : "";
    
        if (sqlColumnExists($table, 'deleted')) {

            $QUERY .= "`deleted` = 'false' AND ";

        } 

        $QUERY .= "`$column` = '$str'";

        $SQL = sqlSelect($table, $QUERY);

        if ($SQL->exists) { $unique = false; }

        return $unique;

    }

    function sanitize($str): string 
    {
        
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

    function sanitizeJSON( array $array ): array 
    {

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

    function sanitizeFirst($str): string
    {

        if (!empty($str)) {
            $str = strtolower($str);
            $str = ucwords($str);
            $str = sanitize($str);
        }
        
        return $str;
        
    }

    function normalizeDB($str): float|int|string
    {

        // preserva 0, 0.0 e numeri in generale
        if (is_int($str) || is_float($str)) {
            return $str; // 0 resta 0
        }

        if ($str === null) {
            return ''; // i NULL diventano stringa vuota
        }

        // Se è una stringa numerica ("0", "1", "42") -> torna numero
        if (is_string($str) && is_numeric($str)) {
            return $str + 0; // cast numerico
        }

        // Altrimenti è testo: sanifichiamo
        return sanitizeEcho((string)$str);
        
    }

    function sanitizeEcho( string $str): string
    {

        global $CHARACTERS;

        if (empty(trim($str))) {
            return '';
        }

        $str = htmlspecialchars_decode($str, ENT_QUOTES | ENT_HTML5);
        $str = str_replace('<br />', '', $str);

        foreach ($CHARACTERS as $k => $c) {
                    
            $character = $c['character'];
            $html = $c['html'];

            if (!in_array($character, ['"', ">", "<", " ", "&"])) {
                $str = str_replace($html, $character, $str);
            }
            
        }

        return $str;

    }

    function printPDF($str, $upper = false): string
    {

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

            $str = iconv('UTF-8', 'windows-1252//IGNORE', $str);

        }
        
        return $str == null ? '' : $str;

    }

    function code($lenght = 10, $type = 'all', $prefix = null): string
    {

        $code = new Wonder\Plugin\Custom\String\Rand($type);

        return $code::generate($lenght, $prefix);

    }

    function prettyPhone($number): string
    {

        return Wonder\Plugin\Custom\Prettify::Phone( $number );

    }

    function prettyDate($date, $hours = false): string 
    {

        return Wonder\Plugin\Custom\Prettify::Date( $date, $hours );

    }

    function prettyAddress($street, $number, $cap, $city, $province, $country, $more = "", $name = "", $surname = "", $phone = ""): object
    {

        return Wonder\Plugin\Custom\Prettify::Address( $street, $number, $cap, $city, $province, $country, $more, $name, $surname, $phone );

    }

    function prettyPrint($array): void 
    { 

        global $ROOT;

        $bt = debug_backtrace();
        $caller = array_shift($bt);

        echo 'File <b>'.str_replace($ROOT, '', $caller['file']).'</b> line <b>'.$caller['line'].'</b>';
        echo "<pre>".print_r($array, true)."</pre>";

    }
    
    function getDomain($domain): string 
    {

        $domain = isset(parse_url($domain)['host']) ? parse_url($domain)['host'] : $domain;
        $domain = str_replace('www.', '', $domain);

        return $domain;

    }

    function getDomainExtension($domain): string 
    {

        $explode = explode(".", $domain);
        $extension = count($explode) > 1 ? end($explode) : "";

        return $extension;

    }