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
 * NB: il pattern di parsing è preso dalla copia privata in
 * `FormFieldElementFactory::parseAttributes()`; estratto qui per riuso.
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
}
