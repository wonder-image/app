<?php

namespace Wonder\App\Security;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Pulizia di un SVG caricato: solo DOM, niente rete, niente entità.
 *
 * Toglie gli elementi fuori dal namespace SVG (XHTML, XInclude, metadati
 * di editor), script, foreignObject e contenitori HTML, attributi `on*`, URL
 * `javascript:`/`vbscript:`, ogni `data:` che non sia un'immagine raster e
 * stili con `@import`, `url(javascript` o `expression(`. Rifiuta (null)
 * DOCTYPE, entità, XML non valido e radici che non siano `<svg>` SVG.
 */
final class SvgSanitizer
{
    private const REMOVED_ELEMENTS = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'handler', 'listener'];

    private const NAMESPACE = 'http://www.w3.org/2000/svg';

    /** Un `data:` qualsiasi, ovunque nel valore, che non sia raster. */
    private const UNSAFE_DATA_URI = '/data:(?!image\/(png|jpe?g|gif|webp)[;,])/';

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

        if (!$loaded || $document->doctype !== null || !$root instanceof DOMElement
            || $root->namespaceURI !== self::NAMESPACE || $root->localName !== 'svg') {
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

            if ($element !== $root && ($element->namespaceURI !== self::NAMESPACE
                || in_array($name, self::REMOVED_ELEMENTS, true)
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

        if (preg_match(self::UNSAFE_DATA_URI, $value) === 1) {
            return true;
        }

        return $name === 'style' && self::unsafeStyle($attribute->value);
    }

    private static function unsafeStyle(string $css): bool
    {
        $css = self::normalize(self::decodeCssEscapes($css));

        return str_contains($css, '@import') || str_contains($css, 'url(javascript') || str_contains($css, 'javascript:')
            || str_contains($css, 'vbscript:') || str_contains($css, 'expression(');
    }

    /** `\6a` e `\j` diventano `j`, come li legge il parser CSS. */
    private static function decodeCssEscapes(string $css): string
    {
        $css = (string) preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})\s?/', static function (array $match): string {
            $code = hexdec($match[1]);

            return $code > 0 && $code <= 0x10FFFF ? mb_chr((int) $code, 'UTF-8') : '';
        }, $css);

        return (string) preg_replace('/\\\\(.)/s', '$1', $css);
    }

    /** Minuscolo e senza spazi o caratteri di controllo, come li salta il browser. */
    private static function normalize(string $value): string
    {
        return strtolower((string) preg_replace('/[\x00-\x20]+/', '', $value));
    }
}
