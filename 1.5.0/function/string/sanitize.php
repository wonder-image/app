<?php

    function sanitize($str): string
    {

        global $CHARACTERS;

        $str = valueToString($str, '1', '');

        if (!empty($str)) {

            $str = trim($str);
            $str = encodeConfiguredCharacters($str, is_array($CHARACTERS ?? null) ? $CHARACTERS : []);
            $str = encodeUnsupportedUnicodeChars($str);
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

    function encodeConfiguredCharacters(string $str, array $characters): string
    {

        if ($str === '' || empty($characters)) {
            return $str;
        }

        foreach ($characters as $row) {

            if (!is_array($row)) {
                continue;
            }

            $character = isset($row['character']) ? (string) $row['character'] : '';
            if ($character === '') {
                continue;
            }

            // Questi vengono gestiti separatamente per non rompere il markup.
            if (in_array($character, [ '"', '>', '<', ' ', '&' ], true)) {
                continue;
            }

            $entity = '';
            if (isset($row['html']) && trim((string) $row['html']) !== '') {
                $entity = trim((string) $row['html']);
            } elseif (isset($row['unicode']) && trim((string) $row['unicode']) !== '') {
                $entity = trim((string) $row['unicode']);
            }

            if ($entity === '') {
                continue;
            }

            $str = str_replace($character, $entity, $str);

        }

        return $str;

    }

    function getUnicodeCodePoint(string $char): ?int
    {

        if ($char === '') {
            return null;
        }

        if (function_exists('mb_ord')) {
            $codePoint = mb_ord($char, 'UTF-8');
            if (is_int($codePoint) && $codePoint >= 0) {
                return $codePoint;
            }
        }

        $ucs4 = @iconv('UTF-8', 'UCS-4BE', $char);
        if (!is_string($ucs4) || strlen($ucs4) !== 4) {
            return null;
        }

        $decoded = unpack('Ncode', $ucs4);
        if (!is_array($decoded) || !isset($decoded['code'])) {
            return null;
        }

        return (int) $decoded['code'];

    }

    function encodeUnsupportedUnicodeChars(string $str): string
    {

        if ($str === '') {
            return '';
        }

        // Se il testo è già nel range single-byte non tocchiamo nulla.
        if (preg_match('/[^\x{0000}-\x{00FF}]/u', $str) !== 1) {
            return $str;
        }

        $encoded = preg_replace_callback(
            '/[^\x{0000}-\x{00FF}]/u',
            function (array $matches): string {
                $char = (string) ($matches[0] ?? '');
                $codePoint = getUnicodeCodePoint($char);

                if ($codePoint === null) {
                    return $char;
                }

                // Salva i caratteri non supportati come entità numerica HTML.
                return '&#'.$codePoint.';';
            },
            $str
        );

        return is_string($encoded) ? $encoded : $str;

    }

    function calculateMojibakeScore(string $value): int
    {

        if ($value === '') {
            return 0;
        }

        $score = 0;

        // Byte UTF-8 letto come latin1/windows-1252.
        $score += preg_match_all('/Ã[\x{0080}-\x{00BF}]/u', $value, $matchesA) ?: 0;
        $score += preg_match_all('/Â[\x{0080}-\x{00BF}]/u', $value, $matchesB) ?: 0;
        $score += preg_match_all('/â(?:€|‚|ƒ|„|…|†|‡|ˆ|‰|Š|‹|Œ|Ž|‘|’|“|”|•|–|—|˜|™|š|›|œ|ž|Ÿ|[\x{0080}-\x{00BF}])/u', $value, $matchesC) ?: 0;

        // Sequenze tipiche emoji corrotte.
        $score += substr_count($value, 'ðŸ');

        return $score;

    }

    function calculateMojibakePenalty(string $value): int
    {

        if ($value === '') {
            return 0;
        }

        return substr_count($value, '?') + substr_count($value, '�');

    }

    function decodeMojibakeString(string $value): string
    {

        if ($value === '') {
            return '';
        }

        $originalScore = calculateMojibakeScore($value);
        if ($originalScore === 0) {
            return $value;
        }

        $bestValue = $value;
        $bestScore = $originalScore;
        $bestPenalty = calculateMojibakePenalty($value);

        foreach (['Windows-1252', 'ISO-8859-1'] as $sourceEncoding) {

            // Ricostruisce i byte nel charset sorgente e prova la decodifica UTF-8.
            $binary = @mb_convert_encoding($value, $sourceEncoding, 'UTF-8');

            if (!is_string($binary) || $binary === '') {
                continue;
            }

            $converted = @mb_convert_encoding($binary, 'UTF-8', 'UTF-8');

            if (!is_string($converted) || $converted === '') {
                continue;
            }

            if (function_exists('isValidUtf8String') && !isValidUtf8String($converted)) {
                continue;
            }

            $convertedScore = calculateMojibakeScore($converted);
            $convertedPenalty = calculateMojibakePenalty($converted);

            $isBetterScore = $convertedScore < $bestScore;
            $isEqualScoreBetterPenalty = $convertedScore === $bestScore && $convertedPenalty < $bestPenalty;

            if ($isBetterScore || $isEqualScoreBetterPenalty) {
                $bestValue = $converted;
                $bestScore = $convertedScore;
                $bestPenalty = $convertedPenalty;
            }

        }

        if ($bestValue === $value) {
            return $value;
        }

        return $bestValue;

    }

    function isJsonStructure(string $value): bool
    {

        $trimmed = trim($value);

        if ($trimmed === '') {
            return false;
        }

        $firstChar = $trimmed[0];
        if ($firstChar !== '{' && $firstChar !== '[') {
            return false;
        }

        json_decode($trimmed, true);

        return json_last_error() === JSON_ERROR_NONE;

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

        if (is_string($str) && isJsonStructure($str)) {
            return $str;
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

        // Corregge in lettura il mojibake storico senza toccare i dati nel DB.
        $str = decodeMojibakeString($str);

        return $str;

    }
