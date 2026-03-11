<?php

    function sanitize($str): string
    {

        $str = valueToString($str, '1', '');

        if (!empty($str)) {

            $str = trim($str);
            $str = \Wonder\Support\Html\Entity::encode($str);
            $str = addslashes($str);

        }

        return $str;

    }

    function sanitizeJSON(array $array): array
    {

        $newArray = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {

                $newArray[$key] = sanitizeJSON($value);

            } else {
                if (is_string($value)) {

                    $value = str_replace(["\r\n", "\n\r", "\n", "\r"], "", $value);
                    $value = pregReplaceSafe('/(<br>)+$/', '', $value);
                    $value = \Wonder\Support\Html\Entity::decode($value);
                    $newArray[$key] = sanitize($value);

                } elseif ($value === null || is_bool($value) || is_int($value) || is_float($value)) {

                    $newArray[$key] = $value;

                } else {

                    $newArray[$key] = sanitize(encodeJsonString($value));

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
        if (is_string($str) && is_numeric($str) && ($str === '0' || !preg_match('/^0\d+$/', $str)) && !str_starts_with($str, '+')) {
            return $str + 0; // cast numerico
        }

        // Altrimenti è testo: sanifichiamo
        return sanitizeEcho((string) $str);

    }

    function sanitizeEcho(string $str): string
    {

        if (empty(trim($str))) {
            return '';
        }

        // Compatibilità con sanitize(): ripristina le slash aggiunte in input.
        $str = stripslashes($str);
        $str = \Wonder\Support\Html\Entity::decode($str);

        $str = pregReplaceSafe('#<br\s*/?>#i', '', $str);

        return $str;

    }
