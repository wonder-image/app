<?php
/** Emula in PHP come MySQL (sql_mode standard) legge `colonna LIKE '...'`, per i test di comportamento. */
declare(strict_types=1);

if (!function_exists('sqlLikeMatches')) {
    /** Il valore di un letterale fra apici, con le regole di MySQL: `\%` e `\_` tengono la barra. */
    function sqlLiteralValue(string $literal): string
    {
        $out = '';
        $length = strlen($literal);
        for ($i = 0; $i < $length; $i++) {
            $c = $literal[$i];
            if ($c === "'" && ($literal[$i + 1] ?? '') === "'") {
                $out .= "'";
                $i++;
            } elseif ($c === '\\' && $i + 1 < $length) {
                $n = $literal[++$i];
                $out .= match ($n) {
                    '0' => "\0", 'n' => "\n", 'r' => "\r", 'Z' => "\x1a", 't' => "\t", 'b' => "\x08",
                    '%', '_' => '\\'.$n,
                    default => $n,
                };
            } else {
                $out .= $c;
            }
        }
        return $out;
    }

    /** true se `$value` soddisfa il pattern LIKE (escape `\`). */
    function sqlLikePatternMatches(string $pattern, string $value): bool
    {
        $regex = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $c = $pattern[$i];
            if ($c === '\\' && $i + 1 < $length) {
                $regex .= preg_quote($pattern[++$i], '/');
            } elseif ($c === '%') {
                $regex .= '.*';
            } elseif ($c === '_') {
                $regex .= '.';
            } else {
                $regex .= preg_quote($c, '/');
            }
        }
        return preg_match('/^'.$regex.'$/s', $value) === 1;
    }

    /** Valuta la prima `LIKE '...'` di `$sql` su `$value`. */
    function sqlLikeMatches(string $sql, string $value, int $index = 0): bool
    {
        preg_match_all("/LIKE '((?:[^'\\\\]|\\\\.|'')*)'/s", $sql, $m);
        if (!isset($m[1][$index])) {
            throw new RuntimeException('Nessuna LIKE numero '.$index.' in: '.$sql);
        }
        return sqlLikePatternMatches(sqlLiteralValue($m[1][$index]), $value);
    }
}
