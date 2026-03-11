<?php

    function printPDF($str, $upper = false): string
    {

        $str = valueToString($str, '1', '');

        if ($str === '') {
            return '';
        }

        $str = normalizeUtf8String($str);
        $str = convertHtmlBreaksToNewLine($str);
        $str = stripHtmlNonTextBlocks($str);
        $str = stripHtmlComments($str);

        $str = strip_tags($str);
        $str = \Wonder\Support\Html\Entity::decode($str);
        $str = normalizeUtf8String($str);

        $str = normalizeLineEndings($str);
        $str = str_replace("\xc2\xa0", ' ', $str);

        $str = pregReplaceSafe('/[\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $str);
        $str = pregReplaceSafe('/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $str);
        $str = pregReplaceSafe('/[\x{2028}\x{2029}]/u', "\n", $str);

        $str = str_replace("\u{2212}", '-', $str);
        $str = pregReplaceSafe("/[^\S\n]+/u", ' ', $str);
        $str = pregReplaceSafe("/\n{3,}/", "\n\n", $str);
        $str = pregReplaceSafe("/ *\n */", "\n", $str);
        $str = trim($str);

        if ($str === '') {
            return '';
        }

        if ($upper == true) {
            if (function_exists('mb_strtoupper')) {
                $str = mb_strtoupper($str, 'UTF-8');
            } else {
                $str = strtoupper($str);
            }
        }

        return convertUtf8ToWindows1252($str);

    }

    function normalizeUtf8String(string $str): string
    {

        if ($str === '') {
            return '';
        }

        if (isValidUtf8String($str)) {
            return $str;
        }

        $encodings = [ 'Windows-1252', 'ISO-8859-1', 'ISO-8859-15' ];

        foreach ($encodings as $encoding) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $str);
            if (!is_string($converted) || $converted === '') {
                continue;
            }

            if (isValidUtf8String($converted)) {
                return $converted;
            }
        }

        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $str);

        return is_string($cleaned) ? $cleaned : '';

    }

    function convertUtf8ToWindows1252(string $str): string
    {

        if ($str === '') {
            return '';
        }

        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $str);
        if (is_string($converted)) {
            return $converted;
        }

        $characters = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters)) {
            $fallback = @iconv('UTF-8', 'Windows-1252//IGNORE', $str);
            return is_string($fallback) ? $fallback : '';
        }

        $result = '';

        foreach ($characters as $character) {
            $singleChar = @iconv('UTF-8', 'Windows-1252', $character);
            if (is_string($singleChar)) {
                $result .= $singleChar;
                continue;
            }

            $asciiFallback = @iconv('UTF-8', 'ASCII//TRANSLIT', $character);
            if (is_string($asciiFallback) && $asciiFallback !== '') {
                $asciiFallback = pregReplaceSafe('/[^\x20-\x7E]/', '', $asciiFallback);
                $result .= ($asciiFallback !== '') ? $asciiFallback : '?';
                continue;
            }

            $result .= '?';
        }

        return $result;

    }

    function printPDFNormalizeUtf8(string $str): string
    {

        return normalizeUtf8String($str);

    }

    function printPDFToWindows1252(string $str): string
    {

        return convertUtf8ToWindows1252($str);

    }
