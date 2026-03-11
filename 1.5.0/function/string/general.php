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

            for ($i = 1; $i > 0 && !$found; $i++) {

                $QUERY = ($id != null) ? "`id` != '$id' AND " : "";

                if (sqlColumnExists($table, 'deleted')) {

                    $QUERY .= "`deleted` = 'false' AND ";

                }

                $QUERY .= "`$column` = '$link'";

                $SQL = sqlSelect($table, $QUERY);

                if ($SQL->exists) {
                    $link = $str . '-' . $i;
                } else {
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

        if ($SQL->exists) {
            $unique = false;
        }

        return $unique;

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

    function code($lenght = 10, $type = 'all', $prefix = null): string
    {

        $code = new Wonder\Support\Text\Random($type);

        return $code::generate($lenght, $prefix);

    }

    function prettyPhone($number): string
    {

        return Wonder\Support\Prettify\Phone::prettify($number);

    }

    function prettyDate($date, $hours = false): string
    {

        return Wonder\Support\Prettify\Date::prettify($date, $hours);

    }

    function prettyAddress($street, $number, $cap, $city, $province, $country, $more = "", $name = "", $surname = "", $phone = ""): object
    {

        return Wonder\Support\Prettify\Address::prettify($street, $number, $cap, $city, $province, $country, $more, $name, $surname, $phone);

    }

    function prettyPrint($array): void
    {

        global $ROOT;

        $bt = debug_backtrace();
        $caller = array_shift($bt);

        echo 'File <b>' . str_replace($ROOT, '', $caller['file']) . '</b> line <b>' . $caller['line'] . '</b>';
        echo "<pre>" . print_r($array, true) . "</pre>";

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
