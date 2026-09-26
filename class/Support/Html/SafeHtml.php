<?php

namespace Wonder\Support\Html;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

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
 * spariscono insieme al contenuto. I commenti si tolgono sempre. `</body>` e
 * `</html>` non chiudono niente: quello che li segue resta.
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

    /**
     * Tag tolti insieme a tutto il contenuto.
     *
     * Niente `embed`: in HTML5 è vuoto e quello che lo segue non è suo. libxml
     * prima della 2.14 lo apre invece come contenitore, e toglierlo con il
     * contenuto si portava via il testo seguente, a volte il resto del
     * documento. Tolto come gli altri tag, quel testo resta.
     */
    private const DROPPED = [
        'script', 'style', 'iframe', 'object', 'template', 'noscript', 'svg', 'math',
        'applet', 'frame', 'frameset', 'noembed', 'noframes',
    ];

    /** Schemi ammessi per l'href dei link. */
    private const HREF_PATTERN = '/^(?:https?|mailto|tel):/i';

    /**
     * Chiusura di `body` o `html`, con eventuali attributi.
     *
     * Il nome si riconosce come fa libxml 2.9, che chiude il body in più casi
     * delle versioni dalla 2.14: `body` o `html` non seguiti da una lettera,
     * una cifra, `:`, `_`, `.` o `-`. Il resto del tag arriva fino al primo
     * `>` compreso, ma non passa un `<`, la fine di un commento (`-->`, `--!>`)
     * o una virgoletta senza la sua chiusura: un commento o un attributo che
     * contengono `</body` finiscono dove finivano. Il tetto di 64 ripetizioni
     * tiene la regex sotto `pcre.backtrack_limit` anche su un input ostile;
     * oltre, il resto del tag resta come testo.
     */
    private const BODY_CLOSE_PATTERN = '~</(?:body|html)(?![a-z0-9:_.-])(?:[^<>"\'-]++|"[^"<>]*+"|\'[^\'<>]*+\'|-(?!-!?>)){0,64}+>?~i';

    /**
     * Tag che libxml dalla 2.14 legge come testo semplice, con il nome che
     * prendono per la lettura: `xmp` è raw text, `plaintext` arriva fino in
     * fondo all'input. `listing` chiude gli stessi tag di `xmp` e si fa
     * chiudere dagli stessi; `plaintext` non ne chiude nessuno, come il nome
     * inventato che lo sostituisce. Le chiusure si accoppiano per nome: in
     * `<xmp>a<listing>b</xmp>` la chiusura di `xmp` prende il `listing` vero,
     * e quello che segue può chiudersi in un punto diverso che con la 2.9.
     */
    private const RAW_TEXT_RENAMES = ['xmp' => 'listing', 'plaintext' => 'wi-plaintext'];

    /** Apertura o chiusura di `xmp` o `plaintext`: il nome finisce dove lo fa finire libxml. */
    private const RAW_TEXT_PATTERN = '~<(/?)(xmp|plaintext)(?=[\t\n\f\r />])~i';

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
     * come testo, via le chiusure di `body` e `html` e i caratteri non ASCII
     * come entità numeriche, così il parser non deve indovinare la codifica.
     */
    private static function normalizeInput(string $html): string
    {
        $html = mb_scrub($html, 'UTF-8');
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $html) ?? '';

        // Come in HTML5, un `<` che non apre un tag (`3 < 5`) è testo: libxml
        // invece lo prenderebbe per un tag rotto e si mangerebbe il pezzo.
        $html = preg_replace('/<(?![a-zA-Z\/!?])/', '&lt;', $html) ?? '';

        // Un `</body>` o `</html>` senza la sua apertura chiuderebbe il body in
        // cui parse() avvolge l'input, e quello che segue resterebbe fuori. Come
        // in HTML5, dove quel contenuto torna nel body, le chiusure si tolgono
        // tutte, anche dentro `textarea` o `script`. Lì libxml dalla 2.14 le
        // legge come testo e la 2.9 come chiusure: togliendole, il risultato è
        // lo stesso con tutte e due.
        $html = preg_replace(self::BODY_CLOSE_PATTERN, '', $html) ?? $html;

        // Togliendo una chiusura, il testo che aveva attorno può comporne
        // un'altra (`</bo</body>dy>`). Quella resta come testo, come i `<`
        // sciolti: qui non si toglie niente, e così non se ne compongono altre.
        $html = preg_replace_callback(
            self::BODY_CLOSE_PATTERN,
            static fn (array $match): string => '&lt;'.substr($match[0], 1),
            $html
        ) ?? $html;

        return mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
    }

    private static function parse(string $html): ?DOMElement
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $marker = null;

        // Solo dalla 2.14: la 2.9 legge già `xmp` e `plaintext` come gli altri
        // tag, e in una chiusura non salta quello che segue il nome
        // (`</listing/…>` lascerebbe `/…>` come testo). Conta la libxml
        // caricata: LIBXML_VERSION è quella con cui è stato compilato PHP.
        if ((int) LIBXML_LOADED_VERSION >= 21400 && preg_match(self::RAW_TEXT_PATTERN, $html) === 1) {
            $marker = bin2hex(random_bytes(8));
            $html = self::renameRawTextTags($html, $marker);
        }

        // Niente `</body></html>` in fondo: a fine input libxml chiude da sé, e
        // un attributo, un `textarea` o un `title` (da libxml 2.14) lasciati
        // aperti se lo mangerebbero come testo.
        $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'
            .$html;

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

        if ($marker !== null) {
            self::restoreRawTextTags($document, $marker);
        }

        $body = $document->getElementsByTagName('body')->item(0);

        return $body instanceof DOMElement ? $body : null;
    }

    /**
     * Rinomina `xmp` e `plaintext`, che libxml dalla 2.14 legge come testo
     * semplice: le entità scritte da normalizeInput() restano come testo e si
     * escapano di nuovo (`perch&amp;#233;`), e i tag dentro non si leggono. La
     * 2.9 li legge come tag normali; rinominati, si leggono così con tutte e
     * due.
     *
     * Il nome nuovo è seguito da `/`, dal segnaposto e dal nome vecchio: libxml
     * fa finire il nome al `/` e legge il resto come un attributo, che si perde
     * con il tag. Dove il tag era testo (dentro `textarea` o `title`, nel
     * valore di un attributo) restoreRawTextTags() rimette il nome vecchio. Il
     * pezzo aggiunto (lettere, cifre, `-` e `/`, senza `--`) non sposta la fine
     * di un attributo, di un commento o di un testo.
     */
    private static function renameRawTextTags(string $html, string $marker): string
    {
        return preg_replace_callback(
            self::RAW_TEXT_PATTERN,
            static fn (array $match): string => '<'.$match[1].self::RAW_TEXT_RENAMES[strtolower($match[2])].'/'.$marker.$match[2],
            $html
        ) ?? $html;
    }

    /** Toglie quello che renameRawTextTags() ha aggiunto dove il tag era testo. */
    private static function restoreRawTextTags(DOMDocument $document, string $marker): void
    {
        $inserted = array_map(static fn (string $name): string => $name.'/'.$marker, self::RAW_TEXT_RENAMES);
        $nodes = (new DOMXPath($document))->query('//text()[contains(., "'.$marker.'")] | //@*[contains(., "'.$marker.'")]');

        foreach ($nodes ?: [] as $node) {
            // Il valore di un attributo si cambia sui suoi nodi di testo:
            // assegnato all'attributo, libxml ci rileggerebbe le entità.
            foreach ($node instanceof DOMAttr ? $node->childNodes : [$node] as $text) {
                if ($text instanceof DOMText) {
                    $text->data = str_replace($inserted, '', $text->data);
                }
            }
        }
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
