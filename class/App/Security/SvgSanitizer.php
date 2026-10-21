<?php

namespace Wonder\App\Security;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Pulizia di un SVG caricato: solo DOM, niente rete, niente entità.
 *
 * Toglie script, foreignObject e contenitori HTML, attributi `on*`, URL
 * `javascript:`/`vbscript:`, `data:` che non siano immagini raster e stili
 * con `url(javascript` o `expression(`. Rifiuta (null) DOCTYPE, entità,
 * XML non valido e radici diverse da `<svg>`.
 */
final class SvgSanitizer
{
    private const REMOVED_ELEMENTS = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'handler', 'listener'];

    private const SAFE_DATA_URI = '/^data:image\/(png|jpe?g|gif|webp)[;,]/';

    /** SVG pulito, identico all'originale se non c'era nulla da togliere. */
    public static function sanitize(string $svg): ?string
    {
        if (trim($svg) === '' || preg_match('/<!(DOCTYPE|ENTITY)/i', $svg) === 1) {
            return null;
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->documentElement;

        if (!$loaded || $document->doctype !== null || !$root instanceof DOMElement || strtolower($root->localName) !== 'svg') {
            return null;
        }

        $changed = false;
        $xpath = new DOMXPath($document);

        foreach (iterator_to_array($xpath->query('//processing-instruction()') ?: []) as $instruction) {
            $instruction->parentNode?->removeChild($instruction);
            $changed = true;
        }

        foreach (iterator_to_array($xpath->query('//*') ?: []) as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }

            $name = strtolower($element->localName);

            if ($element !== $root && (in_array($name, self::REMOVED_ELEMENTS, true)
                || ($name === 'style' && self::unsafeStyle($element->textContent)))) {
                $element->parentNode?->removeChild($element);
                $changed = true;
                continue;
            }

            foreach (iterator_to_array($element->attributes) as $attribute) {
                if ($attribute instanceof DOMAttr && self::unsafeAttribute($attribute)) {
                    $element->removeAttributeNode($attribute);
                    $changed = true;
                }
            }
        }

        if (!$changed) {
            return $svg;
        }

        $clean = $document->saveXML($root);

        return is_string($clean) ? $clean : null;
    }

    private static function unsafeAttribute(DOMAttr $attribute): bool
    {
        $name = strtolower($attribute->localName ?? $attribute->name);
        $value = self::normalize($attribute->value);

        if (str_starts_with($name, 'on')) {
            return true;
        }

        if (str_contains($value, 'javascript:') || str_contains($value, 'vbscript:')) {
            return true;
        }

        if (str_starts_with($value, 'data:') && preg_match(self::SAFE_DATA_URI, $value) !== 1) {
            return true;
        }

        return $name === 'style' && self::unsafeStyle($attribute->value);
    }

    private static function unsafeStyle(string $css): bool
    {
        $css = self::normalize($css);

        return str_contains($css, 'url(javascript') || str_contains($css, 'javascript:')
            || str_contains($css, 'vbscript:') || str_contains($css, 'expression(');
    }

    /** Minuscolo e senza spazi o caratteri di controllo, come li salta il browser. */
    private static function normalize(string $value): string
    {
        return strtolower((string) preg_replace('/[\x00-\x20]+/', '', $value));
    }
}
