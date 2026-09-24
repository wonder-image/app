<?php

namespace Wonder\Support\Html;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Pulizia a whitelist dell'HTML scritto dall'editor dei testi formattati.
 *
 * Restano solo i tag della formattazione di base (`p`, `br`, `strong`, `b`,
 * `em`, `i`, `u`, `s`, `strike`, `del`, `a`), sempre senza attributi: l'unico
 * che sopravvive è `href` sui link, e solo se inizia con `http:`, `https:`,
 * `mailto:` o `tel:`. Un link con un href diverso (relativo, `javascript:`,
 * `data:`, `//host`…) perde il tag e tiene il testo.
 *
 * Gli altri tag si tolgono tenendo il contenuto, tranne quelli che portano
 * codice o markup estraneo (`script`, `style`, `iframe`, `svg`, `math`…), che
 * spariscono insieme al contenuto. I commenti si tolgono sempre.
 *
 * Il risultato è riscritto da zero a partire dall'albero del documento, non
 * ritoccato sulla stringa: quello che esce è solo quello che il serializzatore
 * qui sotto sa scrivere. Un testo vuoto per l'editor (`<p><br></p>`, solo
 * spazi o `&nbsp;`) diventa ''. Un HTML che libxml non riesce a leggere (per
 * esempio oltre 255 livelli di annidamento) esce anche lui '': meglio perdere
 * un input assurdo che farlo passare a metà.
 *
 * Usato in scrittura dai campi dichiarati `Field::richText()`.
 */
final class SafeHtml
{
    /** Tag che restano (senza attributi, tranne `href` su `a`). */
    private const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'a'];

    /** Tag vuoti: si scrivono senza chiusura. */
    private const VOID = ['br'];

    /** Tag tolti insieme a tutto il contenuto. */
    private const DROPPED = [
        'script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'svg', 'math',
        'applet', 'frame', 'frameset', 'noembed', 'noframes',
    ];

    /** Schemi ammessi per l'href dei link. */
    private const HREF_PATTERN = '/^(?:https?|mailto|tel):/i';

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $body = self::parse(self::normalizeInput($html));

        if ($body === null) {
            return '';
        }

        $output = trim(self::serializeChildren($body));

        return self::isBlank($output) ? '' : $output;
    }

    /**
     * UTF-8 valido, niente caratteri di controllo (NUL compreso), i `<` sciolti
     * come testo e i caratteri non ASCII come entità numeriche, così il parser
     * non deve indovinare la codifica.
     */
    private static function normalizeInput(string $html): string
    {
        $html = mb_scrub($html, 'UTF-8');
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $html) ?? '';

        // Come in HTML5, un `<` che non apre un tag (`3 < 5`) è testo: libxml
        // invece lo prenderebbe per un tag rotto e si mangerebbe il pezzo.
        $html = preg_replace('/<(?![a-zA-Z\/!?])/', '&lt;', $html) ?? '';

        return mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
    }

    private static function parse(string $html): ?DOMElement
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'
            .$html
            .'</body></html>';

        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML($wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($loaded !== true) {
            return null;
        }

        $body = $document->getElementsByTagName('body')->item(0);

        return $body instanceof DOMElement ? $body : null;
    }

    private static function serializeChildren(DOMNode $node): string
    {
        $output = '';

        foreach ($node->childNodes as $child) {
            $output .= self::serializeNode($child);
        }

        return $output;
    }

    private static function serializeNode(DOMNode $node): string
    {
        // DOMCdataSection estende DOMText: anche il suo contenuto esce come testo.
        if ($node instanceof DOMText) {
            return self::escapeText($node->data);
        }

        // Commenti, istruzioni di elaborazione e il resto non escono mai.
        if (!$node instanceof DOMElement) {
            return '';
        }

        $name = strtolower($node->nodeName);
        $position = strrpos($name, ':');
        $localName = $position === false ? $name : substr($name, $position + 1);

        if (in_array($name, self::DROPPED, true) || in_array($localName, self::DROPPED, true)) {
            return '';
        }

        if (!in_array($name, self::ALLOWED, true)) {
            return self::serializeChildren($node);
        }

        if (in_array($name, self::VOID, true)) {
            return '<'.$name.'>';
        }

        $attributes = '';

        if ($name === 'a') {
            $href = self::safeHref($node);

            if ($href === null) {
                return self::serializeChildren($node);
            }

            $attributes = ' href="'.htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        return '<'.$name.$attributes.'>'.self::serializeChildren($node).'</'.$name.'>';
    }

    /**
     * L'href del link se è assoluto con uno schema ammesso, altrimenti null.
     *
     * Il controllo vale sia sul valore così com'è (senza spazi ai lati) sia
     * sulla versione con le entità decodificate e senza spazi, caratteri di
     * controllo e caratteri a larghezza zero: `java&#x09;script:` o
     * `java\tscript:` non passano in nessuna delle due forme.
     */
    private static function safeHref(DOMElement $element): ?string
    {
        $href = null;

        foreach ($element->attributes as $attribute) {
            if (strtolower($attribute->nodeName) === 'href') {
                $href = (string) $attribute->value;

                break;
            }
        }

        if ($href === null) {
            return null;
        }

        $href = trim($href, " \t\n\r\0\x0B\f");

        if ($href === '') {
            return null;
        }

        $decoded = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $compact = preg_replace('/[\s\x00-\x1F\x7F\x{00A0}\x{200B}-\x{200F}\x{2028}\x{2029}\x{2060}\x{FEFF}]+/u', '', $decoded);

        if (!is_string($compact)) {
            return null;
        }

        if (preg_match(self::HREF_PATTERN, $href) !== 1 || preg_match(self::HREF_PATTERN, $compact) !== 1) {
            return null;
        }

        return $href;
    }

    private static function escapeText(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return str_replace("\u{00A0}", '&nbsp;', $escaped);
    }

    /** Vuoto per l'editor: senza tag, entità e spazi (anche &nbsp;) non resta niente. */
    private static function isBlank(string $html): bool
    {
        if ($html === '') {
            return true;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]+/u', '', $text);

        return $text === '';
    }
}
