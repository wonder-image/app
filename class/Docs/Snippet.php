<?php

namespace Wonder\Docs;

/**
 * Prepara il codice di un esempio nelle due forme che servono: quella da
 * mostrare (con le righe `use` che gli mancano) e quella da eseguire (con il
 * `return` davanti all'ultima istruzione, se l'autore non l'ha scritto).
 *
 * Il codice eseguito è sempre quello mostrato: le due forme differiscono solo
 * per quel `return`, così chi copia lo snippet ottiene esattamente ciò che
 * l'anteprima ha reso.
 */
final class Snippet
{
    private const STATEMENT_KEYWORDS = [
        'T_RETURN', 'T_ECHO', 'T_PRINT', 'T_IF', 'T_FOREACH', 'T_FOR', 'T_WHILE', 'T_DO',
        'T_SWITCH', 'T_MATCH', 'T_TRY', 'T_THROW', 'T_USE', 'T_FUNCTION', 'T_CLASS', 'T_ABSTRACT',
        'T_FINAL', 'T_INTERFACE', 'T_TRAIT', 'T_ENUM', 'T_NAMESPACE', 'T_GLOBAL', 'T_STATIC',
        'T_UNSET', 'T_BREAK', 'T_CONTINUE', 'T_GOTO', 'T_DECLARE', 'T_CONST',
    ];

    /**
     * Il codice come appare nella pagina: senza `<?php`, senza l'indentazione
     * comune, con una riga `use` per ogni classe di `$uses` nominata ma non
     * importata.
     *
     * @param string[] $uses
     */
    public static function display(string $code, array $uses = []): string
    {
        $code = self::normalize($code);
        $imports = self::missingImports($code, $uses);

        if ($imports === []) {
            return $code;
        }

        $lines = array_map(static fn (string $class): string => 'use '.$class.';', $imports);

        return implode("\n", $lines)."\n\n".$code;
    }

    /** Il codice da passare a `eval()`: quello mostrato, più il `return` sull'ultima istruzione. */
    public static function executable(string $code): string
    {
        $code = self::normalize($code);

        if (trim($code) === '') {
            return 'return null;';
        }

        if (!str_ends_with(rtrim($code), ';') && !str_ends_with(rtrim($code), '}')) {
            $code = rtrim($code).';';
        }

        $offset = self::lastStatementOffset($code);

        if ($offset === null) {
            return $code;
        }

        $head = substr($code, 0, $offset);
        $tail = substr($code, $offset);
        $tokens = token_get_all('<?php '.$tail);
        $first = null;

        foreach ($tokens as $index => $token) {
            if ($index === 0) {
                continue;
            }

            if (is_array($token) && in_array(token_name($token[0]), ['T_WHITESPACE', 'T_COMMENT', 'T_DOC_COMMENT'], true)) {
                continue;
            }

            $first = $token;
            break;
        }

        if ($first === null || (is_array($first) && in_array(token_name($first[0]), self::STATEMENT_KEYWORDS, true))) {
            return $code;
        }

        if (is_string($first) && in_array($first, ['{', '}'], true)) {
            return $code;
        }

        return $head.'return '.$tail;
    }

    /**
     * Le classi di `$uses` che lo snippet nomina (col nome corto) senza averle
     * già importate, in ordine alfabetico.
     *
     * @param string[] $uses
     * @return string[]
     */
    public static function missingImports(string $code, array $uses): array
    {
        $stripped = self::withoutStringsAndComments($code);
        $imports = [];

        foreach ($uses as $class) {
            $class = ltrim(trim((string) $class), '\\');

            if ($class === '') {
                continue;
            }

            $short = substr($class, (int) strrpos('\\'.$class, '\\'));

            if (!preg_match('/(?<![\w\\\\$])'.preg_quote($short, '/').'(?![\w\\\\])/', $stripped)) {
                continue;
            }

            if (preg_match('/^\s*use\s+(?:\\\\)?'.preg_quote($class, '/').'\s*;/m', $stripped)
                || preg_match('/^\s*use\s+[^;]*\b'.preg_quote($short, '/').'\s*[;,}]/m', $stripped)) {
                continue;
            }

            $imports[] = $class;
        }

        sort($imports);

        return array_values(array_unique($imports));
    }

    /** Senza `<?php`, senza spazi finali e senza l'indentazione comune. */
    public static function normalize(string $code): string
    {
        $code = (string) preg_replace('/^\s*<\?php[ \t]*\r?\n?/', '', $code);
        $code = str_replace(["\r\n", "\r"], "\n", $code);
        $code = rtrim($code);
        $lines = explode("\n", $code);

        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }

        $indent = null;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $leading = strlen($line) - strlen(ltrim($line, " \t"));
            $indent = $indent === null ? $leading : min($indent, $leading);
        }

        if ($indent) {
            $lines = array_map(
                static fn (string $line): string => trim($line) === '' ? '' : substr($line, $indent),
                $lines
            );
        }

        return implode("\n", $lines);
    }

    /** L'offset, nel codice, del primo carattere dell'ultima istruzione di primo livello. */
    private static function lastStatementOffset(string $code): ?int
    {
        $prefix = '<?php ';
        $tokens = token_get_all($prefix.$code);
        $position = 0;
        $depth = 0;
        $statementStart = 0;
        $lastSignificant = null;
        $pendingStart = null;

        foreach ($tokens as $index => $token) {
            $text = is_array($token) ? $token[1] : $token;
            $length = strlen($text);

            if ($index === 0) {
                $position += $length;
                continue;
            }

            $isWhitespace = is_array($token) && in_array(token_name($token[0]), ['T_WHITESPACE', 'T_COMMENT', 'T_DOC_COMMENT'], true);

            if (!$isWhitespace) {
                if ($pendingStart !== null) {
                    $statementStart = $position;
                    $pendingStart = null;
                }

                $lastSignificant = $position;

                if (is_string($token)) {
                    if (in_array($token, ['(', '[', '{'], true)) {
                        $depth++;
                    } elseif (in_array($token, [')', ']', '}'], true)) {
                        $depth = max(0, $depth - 1);

                        if ($depth === 0 && $token === '}') {
                            $pendingStart = $position + $length;
                        }
                    } elseif ($token === ';' && $depth === 0) {
                        $pendingStart = $position + $length;
                    }
                } elseif (in_array(token_name($token[0]), ['T_CURLY_OPEN', 'T_DOLLAR_OPEN_CURLY_BRACES'], true)) {
                    $depth++;
                }
            }

            $position += $length;
        }

        if ($lastSignificant === null) {
            return null;
        }

        return max(0, $statementStart - strlen($prefix));
    }

    /** Il codice con stringhe e commenti sostituiti da spazi: per cercare nomi di classe senza falsi positivi. */
    private static function withoutStringsAndComments(string $code): string
    {
        $out = '';

        foreach (token_get_all('<?php '.$code) as $index => $token) {
            if ($index === 0) {
                continue;
            }

            if (is_string($token)) {
                $out .= $token;
                continue;
            }

            $name = token_name($token[0]);

            if (in_array($name, ['T_CONSTANT_ENCAPSED_STRING', 'T_ENCAPSED_AND_WHITESPACE', 'T_COMMENT', 'T_DOC_COMMENT'], true)) {
                $out .= str_repeat(' ', strlen($token[1]));
                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }
}
