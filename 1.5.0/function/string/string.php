<?php

    function create_link($str, $table = null, $column = null, $id = null): string
    {

        global $CHARACTERS;

        $str = \Wonder\Support\Html\Entity::decode($str);

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
        if ($str === null) {
            return '';
        }
        
        if (is_bool($str)) {
            $str = $str ? '1' : '0';
        } elseif (is_int($str) || is_float($str)) {
            $str = (string) $str;
        } elseif (is_array($str) || is_object($str)) {
            $encoded = json_encode($str, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $str = is_string($encoded) ? $encoded : '';
        } elseif (!is_string($str)) {
            $str = (string) $str;
        }
        
        if (!empty($str)) {

            $str = trim($str);
            $str = \Wonder\Support\Html\Entity::encode($str);
            $str = addslashes($str);

        }

        return $str;
        
    }

    function htmlToText(string $body): string
    {

        if ($body === '') {
            return '';
        }

        $text = $body;

        // Converte i link html in "testo (url)" prima di rimuovere i tag.
        $text = preg_replace_callback(
            '#<a\s[^>]*href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^>\s]+))[^>]*>(.*?)</a>#is',
            function (array $matches): string {
                $url = trim((string) ($matches[1] ?? $matches[2] ?? $matches[3] ?? ''));
                $url = \Wonder\Support\Html\Entity::decode($url);

                $label = trim(strip_tags((string) ($matches[4] ?? '')));
                $label = \Wonder\Support\Html\Entity::decode($label);

                if ($url === '') {
                    return $label;
                }

                if ($label === '' || $label === $url) {
                    return $url;
                }

                return $label . ' (' . $url . ')';
            },
            $text
        );

        $text = preg_replace('#<(br|/p|/div|/li|/tr|/h[1-6])\s*/?>#i', "\n", $text);
        $text = preg_replace('#<li[^>]*>#i', '- ', $text);

        $text = strip_tags($text);
        $text = \Wonder\Support\Html\Entity::decode($text);

        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $text = preg_replace("/ *\n */", "\n", $text);

        return trim((string) $text);

    }

    function fileToArray($files): array
    {

        if ($files === null || $files === '' || $files === []) {
            return [];
        }

        if (!is_array($files)) {
            $files = [ $files ];
        }

        $RETURN = [];

        foreach ($files as $key => $value) {

            if (is_numeric($key)) {
                $path = (string) $value;
                $name = '';
            } else {
                $path = (string) $key;
                $name = (string) $value;
            }

            if ($path === '') {
                continue;
            }

            $realPath = realpath($path);
            $exists = is_string($realPath) && is_file($realPath);
            $targetPath = $exists ? $realPath : $path;

            $size = null;
            if ($exists) {
                $sizeValue = @filesize($targetPath);
                $size = ($sizeValue === false) ? null : (int) $sizeValue;
            }

            $mime = null;
            if ($exists && function_exists('mime_content_type')) {
                $mimeType = @mime_content_type($targetPath);
                $mime = ($mimeType === false) ? null : $mimeType;
            }

            $RETURN[] = [
                'path' => $path,
                'name' => $name,
                'basename' => basename($path),
                'extension' => strtolower((string) pathinfo($path, PATHINFO_EXTENSION)),
                'exists' => $exists,
                'size' => $size,
                'mime' => $mime
            ];

        }

        return $RETURN;

    }

    function sanitizeJSON( array $array ): array 
    {

        $newArray = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {

                $newArray[$key] = sanitizeJSON($value);

            } else {
                if (is_string($value)) {

                    $value = str_replace(["\r\n", "\n\r", "\n", "\r"], "", $value);
                    $value = preg_replace('/(<br>)+$/', '', $value);
                    $value = \Wonder\Support\Html\Entity::decode($value);
                    $newArray[$key] = sanitize($value);

                } elseif ($value === null || is_bool($value) || is_int($value) || is_float($value)) {

                    $newArray[$key] = $value;

                } else {

                    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $newArray[$key] = is_string($encoded) ? sanitize($encoded) : '';

                }
                
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
        if (is_string($str) && is_numeric($str) && ( $str === '0' || !preg_match('/^0\d+$/', $str) ) && !str_starts_with($str, '+')) {
            return $str + 0; // cast numerico
        }

        // Altrimenti è testo: sanifichiamo
        return sanitizeEcho((string)$str);
        
    }

    function sanitizeEcho( string $str): string
    {

        if (empty(trim($str))) { return ''; }

        // Compatibilità con sanitize(): ripristina le slash aggiunte in input.
        $str = stripslashes($str);
        $str = \Wonder\Support\Html\Entity::decode($str);

        $str = preg_replace('#<br\s*/?>#i', '', $str);

        return $str;

    }

    function printPDF($str, $upper = false): string
    {

        if (!empty($str)) {

            $str = str_replace('<br> ', "\n", $str);
            $str = str_replace('<br>', "\n", $str);
            $str = preg_replace('#<(script|style)[^>]*?>.*?</\\1>#is', '', $str);

            if ($upper == true) {

                $str = strip_tags($str);
                $str = \Wonder\Support\Html\Entity::decode(strtolower($str));

                $str = str_replace('à', 'À', $str);
                $str = str_replace('è', 'È', $str);
                $str = str_replace('ì', 'Ì', $str);
                $str = str_replace('ò', 'Ò', $str);
                $str = str_replace('ù', 'Ù', $str);
                
                $str = strtoupper($str);

            } else {

                $str = strip_tags($str);
                $str = \Wonder\Support\Html\Entity::decode($str);

            }

            $str = iconv('UTF-8', 'windows-1252//IGNORE', $str);

        }
        
        return $str == null ? '' : $str;

    }

    function code($lenght = 10, $type = 'all', $prefix = null): string
    {

        $code = new Wonder\Support\Text\Random($type);

        return $code::generate($lenght, $prefix);

    }

    function prettyPhone($number): string
    {

        return Wonder\Support\Prettify\Phone::prettify( $number );

    }

    function prettyDate($date, $hours = false): string 
    {

        return Wonder\Support\Prettify\Date::prettify( $date, $hours );

    }

    function prettyAddress($street, $number, $cap, $city, $province, $country, $more = "", $name = "", $surname = "", $phone = ""): object
    {

        return Wonder\Support\Prettify\Address::prettify( $street, $number, $cap, $city, $province, $country, $more, $name, $surname, $phone );

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
