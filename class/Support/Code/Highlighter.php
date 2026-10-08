<?php

namespace Wonder\Support\Code;

/**
 * Evidenziazione della sintassi lato server, senza librerie JavaScript.
 *
 * Il codice PHP passa dal tokenizer nativo (`token_get_all`), quindi parole
 * chiave, stringhe, variabili e nomi di classe sono riconosciuti davvero e non
 * indovinati con espressioni regolari; HTML e gli altri linguaggi usano un
 * riconoscimento leggero. L'output è HTML già escapato: ogni token sta in uno
 * `<span class="wi-code-<tipo>">`, il testo fra i token resta nudo, così
 * `textContent` dell'elemento restituisce il codice originale (è ciò che il
 * bottone "copia" legge).
 */
final class Highlighter
{
    public const LANGUAGES = ['php', 'html', 'css', 'js', 'json', 'bash', 'text'];

    /** Token PHP che sono nomi e non parole chiave. */
    private const PHP_NAME_TOKENS = [
        'T_STRING', 'T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED', 'T_NAME_RELATIVE',
        'T_VARIABLE', 'T_STRING_VARNAME', 'T_WHITESPACE', 'T_INLINE_HTML', 'T_OPEN_TAG',
        'T_OPEN_TAG_WITH_ECHO', 'T_CLOSE_TAG', 'T_COMMENT', 'T_DOC_COMMENT',
        'T_CONSTANT_ENCAPSED_STRING', 'T_ENCAPSED_AND_WHITESPACE', 'T_LNUMBER', 'T_DNUMBER',
        'T_NUM_STRING', 'T_START_HEREDOC', 'T_END_HEREDOC', 'T_BAD_CHARACTER',
    ];

    private const PHP_CONSTANTS = ['true', 'false', 'null'];

    private const GENERIC_KEYWORDS = [
        'css' => ['important', 'root', 'media', 'supports', 'keyframes', 'import', 'font-face'],
        'js' => [
            'const', 'let', 'var', 'function', 'return', 'if', 'else', 'for', 'while', 'do', 'switch',
            'case', 'break', 'continue', 'new', 'delete', 'typeof', 'instanceof', 'in', 'of', 'class',
            'extends', 'super', 'this', 'import', 'export', 'default', 'from', 'try', 'catch', 'finally',
            'throw', 'async', 'await', 'yield', 'true', 'false', 'null', 'undefined',
        ],
        'json' => ['true', 'false', 'null'],
        'bash' => [
            'if', 'then', 'else', 'elif', 'fi', 'for', 'in', 'do', 'done', 'while', 'case', 'esac',
            'function', 'export', 'return', 'exit', 'echo', 'cd', 'npm', 'php', 'composer', 'git',
        ],
        'text' => [],
    ];

    public static function languages(): array
    {
        return self::LANGUAGES;
    }

    public static function normalizeLanguage(string $language): string
    {
        $language = strtolower(trim($language));

        return match ($language) {
            'htm', 'xml', 'svg' => 'html',
            'javascript', 'mjs' => 'js',
            'sh', 'shell', 'zsh', 'console', 'terminal' => 'bash',
            'txt', 'plain', '' => 'text',
            default => in_array($language, self::LANGUAGES, true) ? $language : 'text',
        };
    }

    public static function highlight(string $code, string $language = 'php'): string
    {
        return match (self::normalizeLanguage($language)) {
            'php' => self::php($code),
            'html' => self::html($code),
            'text' => self::plain($code),
            default => self::generic($code, self::normalizeLanguage($language)),
        };
    }

    public static function plain(string $code): string
    {
        return self::escape($code);
    }

    public static function php(string $code): string
    {
        $hasOpenTag = (bool) preg_match('/^\s*<\?php/i', $code);
        $source = $hasOpenTag ? $code : "<?php ".$code;
        $tokens = token_get_all($source);
        $html = '';
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_string($token)) {
                $html .= self::escape($token);
                continue;
            }

            [$id, $text] = $token;
            $name = token_name($id);

            if ($name === 'T_OPEN_TAG' && !$hasOpenTag && $index === 0) {
                continue;
            }

            $type = self::phpTokenType($name, $text, $tokens, $index);
            $html .= $type === null ? self::escape($text) : self::span($type, $text);
        }

        return $html;
    }

    public static function html(string $code): string
    {
        return (string) preg_replace_callback(
            '/<!--.*?-->|<!\[CDATA\[.*?\]\]>|<\/?[a-zA-Z][^>]*>?|[^<]+|</s',
            static function (array $match): string {
                $piece = $match[0];

                if (str_starts_with($piece, '<!--') || str_starts_with($piece, '<![CDATA[')) {
                    return self::span('comment', $piece);
                }

                if (!preg_match('/^<\/?[a-zA-Z]/', $piece)) {
                    return self::escape($piece);
                }

                return self::htmlTag($piece);
            },
            $code
        );
    }

    private static function htmlTag(string $tag): string
    {
        if (!preg_match('/^(<\/?)([a-zA-Z][\w:-]*)(.*?)(\/?>?)$/s', $tag, $parts)) {
            return self::escape($tag);
        }

        [, $open, $name, $body, $close] = $parts;
        $html = self::span('tag', $open.$name);

        $html .= (string) preg_replace_callback(
            '/([^\s=\/"\']+)(\s*=\s*)("[^"]*"|\'[^\']*\'|[^\s"\'>]+)?|\S+|\s+/s',
            static function (array $attr): string {
                if (trim($attr[0]) === '') {
                    return self::escape($attr[0]);
                }

                if (!isset($attr[1])) {
                    // Un attributo senza valore (`hidden`, `disabled`).
                    return preg_match('/^[a-zA-Z_:][\w:.-]*$/', $attr[0]) ? self::span('attr', $attr[0]) : self::escape($attr[0]);
                }

                $out = self::span('attr', $attr[1]);

                if (isset($attr[2])) {
                    $out .= self::escape($attr[2]);
                }

                if (isset($attr[3]) && $attr[3] !== '') {
                    $out .= self::span('string', $attr[3]);
                }

                return $out;
            },
            $body
        );

        return $html.($close !== '' ? self::span('tag', $close) : '');
    }

    private static function generic(string $code, string $language): string
    {
        $keywords = self::GENERIC_KEYWORDS[$language] ?? [];
        $comment = match ($language) {
            'css' => '\/\*.*?\*\/',
            'js', 'json' => '\/\*.*?\*\/|\/\/[^\n]*',
            'bash' => '#[^\n]*',
            default => '(?!)',
        };

        $pattern = '/(?<comment>'.$comment.')'
            .'|(?<string>"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|`(?:\\\\.|[^`\\\\])*`)'
            .'|(?<number>\b\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw|s|ms)?\b)'
            .'|(?<word>[A-Za-z_$][\w$-]*)'
            .'|(?<other>[^\s\w"\'`]+|\s+)/s';

        return (string) preg_replace_callback(
            $pattern,
            static function (array $match) use ($keywords, $language): string {
                if (($match['comment'] ?? '') !== '') {
                    return self::span('comment', $match['comment']);
                }

                if (($match['string'] ?? '') !== '') {
                    return self::span('string', $match['string']);
                }

                if (($match['number'] ?? '') !== '') {
                    return self::span('number', $match['number']);
                }

                if (($match['word'] ?? '') !== '') {
                    $word = $match['word'];

                    if (in_array(strtolower($word), $keywords, true)) {
                        return self::span('keyword', $word);
                    }

                    if ($language === 'css' && str_starts_with($word, '--')) {
                        return self::span('variable', $word);
                    }

                    if ($language === 'bash' && str_starts_with($word, '$')) {
                        return self::span('variable', $word);
                    }

                    return self::escape($word);
                }

                return self::escape($match[0]);
            },
            $code
        );
    }

    /**
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     */
    private static function phpTokenType(string $name, string $text, array $tokens, int $index): ?string
    {
        switch ($name) {
            case 'T_COMMENT':
            case 'T_DOC_COMMENT':
                return 'comment';
            case 'T_CONSTANT_ENCAPSED_STRING':
            case 'T_ENCAPSED_AND_WHITESPACE':
            case 'T_START_HEREDOC':
            case 'T_END_HEREDOC':
                return 'string';
            case 'T_VARIABLE':
            case 'T_STRING_VARNAME':
                return 'variable';
            case 'T_LNUMBER':
            case 'T_DNUMBER':
            case 'T_NUM_STRING':
                return 'number';
            case 'T_NAME_QUALIFIED':
            case 'T_NAME_FULLY_QUALIFIED':
            case 'T_NAME_RELATIVE':
                return 'class';
            case 'T_OPEN_TAG':
            case 'T_OPEN_TAG_WITH_ECHO':
            case 'T_CLOSE_TAG':
                return 'keyword';
            case 'T_ATTRIBUTE':
                return 'keyword';
            case 'T_STRING':
                return self::phpStringType($text, $tokens, $index);
        }

        if (in_array($name, self::PHP_NAME_TOKENS, true)) {
            return null;
        }

        return preg_match('/^\(?[a-z_]+\)?$/i', $text) === 1 ? 'keyword' : null;
    }

    /**
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     */
    private static function phpStringType(string $text, array $tokens, int $index): ?string
    {
        if (in_array(strtolower($text), self::PHP_CONSTANTS, true)) {
            return 'constant';
        }

        $previous = self::significantToken($tokens, $index, -1);
        $next = self::significantToken($tokens, $index, 1);
        $previousName = is_array($previous) ? token_name($previous[0]) : null;
        $nextText = is_array($next) ? $next[1] : $next;

        if ($previousName === 'T_OBJECT_OPERATOR' || $previousName === 'T_NULLSAFE_OBJECT_OPERATOR') {
            return $nextText === '(' ? 'function' : 'property';
        }

        if ($previousName === 'T_DOUBLE_COLON') {
            return $nextText === '(' ? 'function' : 'constant';
        }

        if (is_array($next) && token_name($next[0]) === 'T_DOUBLE_COLON') {
            return 'class';
        }

        if (in_array($previousName, ['T_NEW', 'T_INSTANCEOF', 'T_EXTENDS', 'T_IMPLEMENTS', 'T_USE', 'T_NAMESPACE', 'T_CLASS', 'T_INTERFACE', 'T_TRAIT', 'T_ENUM'], true)) {
            return 'class';
        }

        if ($nextText === '(') {
            return 'function';
        }

        if ($previousName === 'T_FUNCTION' || $previousName === 'T_CONST') {
            return 'function';
        }

        return ctype_upper($text[0] ?? '') ? 'class' : null;
    }

    /**
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     * @return array{0:int,1:string,2:int}|string|null
     */
    private static function significantToken(array $tokens, int $index, int $direction): array|string|null
    {
        $cursor = $index + $direction;

        while (isset($tokens[$cursor])) {
            $token = $tokens[$cursor];

            if (is_string($token)) {
                return $token;
            }

            $name = token_name($token[0]);

            if (!in_array($name, ['T_WHITESPACE', 'T_COMMENT', 'T_DOC_COMMENT'], true)) {
                return $token;
            }

            $cursor += $direction;
        }

        return null;
    }

    private static function span(string $type, string $text): string
    {
        return '<span class="wi-code-'.$type.'">'.self::escape($text).'</span>';
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
