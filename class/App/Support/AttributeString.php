<?php

namespace Wonder\App\Support;

/**
 * Parser/serializer per stringhe di attributi HTML legacy.
 *
 * Le funzioni procedurali in `app/function/{backend,frontend}/input.php`
 * accettano l'attributo come stringa unica (es. `'required maxlength="10"'`).
 * Gli Element del nuovo sistema, invece, lavorano con array associativi
 * key => value (vedi `HasAttributes::attributes()`).
 *
 * Questa utility fa il bridge tra i due mondi.
 *
 * NB: il pattern di parsing proviene dal vecchio dispatcher dei form ed è
 * stato estratto qui per riuso.
 */
final class AttributeString
{
    /**
     * Trasforma una stringa di attributi HTML in array key => value.
     * Per gli attributi booleani (senza `=`) il valore è `true`.
     *
     * @return array<string, mixed>
     */
    public static function parse(?string $attribute): array
    {
        $attribute = trim((string) $attribute);

        if ($attribute === '') {
            return [];
        }

        $attributes = [];
        $pattern = '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+)))?/';

        if (!preg_match_all($pattern, $attribute, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $key = trim((string) ($match[1] ?? ''));

            if ($key === '') {
                continue;
            }

            $value = $match[2] ?? $match[3] ?? $match[4] ?? true;
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * `true` se la stringa contiene l'attributo (senza fare un parse completo).
     * Usato per il check `required`/`multiple` veloce, equivalente al
     * vecchio `strpos($attribute, 'required') !== false` ma più sicuro
     * perché tokenizza.
     */
    public static function has(?string $attribute, string $name): bool
    {
        return array_key_exists($name, self::parse($attribute));
    }

    /**
     * Serializza un array key => value in attributi HTML, senza spazio
     * iniziale: lo aggiunge chi stampa. `true` stampa l'attributo senza
     * valore, `false` e `null` lo omettono. Le chiavi si ripuliscono dagli
     * spazi; quelle vuote e quelle in `$reserved` (confronto in minuscolo)
     * si saltano.
     *
     * Differenze da `View\Component::renderAttributes()`: dentro un array
     * `"0"` resta (si salta solo la voce vuota dopo il trim) e le voci non
     * scalari e non Stringable si saltano invece di essere convertite.
     *
     * @param array<array-key, mixed> $attributes
     * @param list<string> $reserved
     */
    public static function render(array $attributes, array $reserved = []): string
    {
        $reserved = array_map('strtolower', $reserved);
        $html = [];

        foreach ($attributes as $key => $value) {
            $key = trim((string) $key);

            if ($key === '' || $value === null || $value === false || in_array(strtolower($key), $reserved, true)) {
                continue;
            }

            if ($value === true) {
                $html[] = $key;
                continue;
            }

            if (is_array($value)) {
                $value = implode(' ', array_filter(array_map(
                    static fn (mixed $item): string => is_scalar($item) || $item instanceof \Stringable ? trim((string) $item) : '',
                    $value
                ), static fn (string $item): bool => $item !== ''));
            } elseif (!is_scalar($value) && !$value instanceof \Stringable) {
                continue;
            }

            $html[] = $key.'="'.htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        return implode(' ', $html);
    }
}
