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
 * spariscono insieme al contenuto. Di `script`, `style`, `iframe`, `noembed` e
 * `noframes` è contenuto tutto quello che sta fino alla loro chiusura, come in
 * HTML5: anche la chiusura di un tag che li contiene, e il testo che la segue.
 * I commenti si tolgono sempre. `</body>` e `</html>` non chiudono niente:
 * quello che li segue resta. I tag vuoti di HTML5 (`wbr`, `source`, `embed`…)
 * restano vuoti: quello che li segue non è loro e si chiude dove si chiude nel
 * browser.
 *
 * Il risultato è riscritto da zero a partire dall'albero del documento, non
 * ritoccato sulla stringa: quello che esce è solo quello che il serializzatore
 * qui sotto sa scrivere. Gli a capo escono come `\n`, anche quelli scritti
 * `\r\n`, `\r` o `&#13;`. Un testo vuoto per l'editor (`<p><br></p>`, solo
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
     * Niente `embed`: in HTML5 è vuoto, quello che lo segue non è suo e ora si
     * legge così con ogni libxml (vedi UNKNOWN_VOID_RENAMES). Di contenuto suo
     * non ne ha; tolto come gli altri tag, il testo che lo segue resta.
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

    /**
     * Tag di DROPPED che l'HTML5 legge come testo semplice, con il nome che
     * prendono per la lettura.
     *
     * libxml legge come testo semplice solo `script` e `style`: `iframe`,
     * `noembed` e `noframes` sono testo dalla 2.14 e tag normali prima, e la
     * quantità di testo che si portano via cambia con la versione. Rinominati
     * in `style`, li legge come testo semplice con tutte e due, e `style` è già
     * in DROPPED: sparisce con il contenuto senza rimettere il nome vecchio.
     *
     * Il nome è condiviso, e le chiusure si accoppiano per nome: `</style>`,
     * `</iframe>`, `</noembed>` e `</noframes>` finiscono il testo semplice di
     * qualunque di questi quattro tag, anche prima della fine che direbbe
     * HTML5. Il tag sparisce comunque con il contenuto e quello che resta è
     * testo escapato. `script` tiene il suo nome: una loro chiusura scritta in
     * uno script (`document.write('…</iframe>')`) non lo finisce prima.
     */
    private const DROPPED_RAW_TEXT_RENAMES = ['iframe' => 'style', 'noembed' => 'style', 'noframes' => 'style'];

    /**
     * `HTML_PARSE_RECOVER` per loadHTML(), scritto a mano perché
     * `LIBXML_RECOVER` non c'è su tutte le versioni di PHP supportate (manca
     * su 8.2).
     *
     * Senza, libxml 2.9 finisce il testo semplice di `script` e `style` al
     * primo `</` seguito da una lettera invece che alla loro chiusura. Se il
     * tag che nomina è aperto si chiude, e lo script si porta via meno testo
     * delle versioni dalla 2.14; se non lo è (`if (a </b) {`), la chiusura
     * si legge fino al primo `>`, anche quello del `</script>` vero, e lo
     * script si porta via tutto il resto. Con il flag restano due casi: un
     * nome che comincia con il suo (PREFIXED_NAME_PATTERN) e un `</` all'inizio
     * del contenuto (RAW_TEXT_START_PATTERN).
     */
    private const PARSE_RECOVER = 1;

    /**
     * Apertura di `script` o `style` come la legge libxml 2.9, anche quella
     * scritta da renameTags() (`<style/…`).
     */
    private const RAW_TEXT_OPEN_PATTERN = '~<(?:script|style)(?![a-z0-9:_.-])~i';

    /**
     * Apertura o chiusura di un tag il cui nome comincia con `script` o
     * `style` e continua (`</scriptx`, `<style-a>`).
     *
     * Anche con PARSE_RECOVER, libxml prima della 2.14 finisce il testo
     * semplice di uno script a un `</` seguito da un nome che comincia con
     * `script` (di uno style, con `style`). `</scriptx` non lo chiude, ma si
     * legge fino al primo `>`, anche quello del `</script>` vero, e lo script
     * si porta via tutto il resto. Con un pezzo davanti al nome resta testo
     * dello script, come in HTML5. Aperture e chiusure prendono lo stesso
     * pezzo, quindi fuori da script e style si accoppiano come prima.
     */
    private const PREFIXED_NAME_PATTERN = '~<(/?)(?=(?:script|style)[a-z0-9:_.-])~i';

    /**
     * Un `>` seguito da `</` e da una lettera, `_`, `:` o `.`: dove comincia il
     * contenuto di uno script o di uno style, una chiusura.
     *
     * Anche con PARSE_RECOVER, libxml prima della 2.14 legge come chiusura un
     * `</` seguito da un nome all'inizio del testo semplice di uno script o di
     * uno style (`<script></b) …`). Se il tag che nomina è aperto si chiude
     * con lo script; se non lo è, la chiusura si legge fino al primo `>`,
     * anche quello del `</script>` vero, e lo script si porta via tutto il
     * resto. Con un pezzo di testo davanti il contenuto non comincia più con
     * `</` e arriva fino alla chiusura, come in HTML5. Il pezzo va dopo ogni
     * `>` così seguito, senza cercare dove finisce l'apertura: altrove finisce
     * in un testo o nel valore di un attributo, da cui lo toglie
     * restoreRenamedTags(), o in un commento, che non esce. Un testo in più
     * non sposta niente: la 2.9 gli apre davanti un paragrafo solo fuori dal
     * body.
     */
    private const RAW_TEXT_START_PATTERN = '~>(?=</[a-z_:.])~i';

    /**
     * Tag vuoti in HTML5 che libxml prima della 2.14 non conosce, con il nome
     * che prendono per la lettura.
     *
     * Quella libxml li apre come contenitori: quello che segue ci finisce
     * dentro e i tag attorno si chiudono in un altro punto. `param` è vuoto
     * per ogni versione e, entrando, non chiude niente di aperto: rinominati,
     * la struttura è quella di HTML5 con tutte e due. Un nome solo basta per
     * tutti, perché nessuno di questi tag esce nel risultato e gli attributi
     * si perdono comunque.
     */
    private const UNKNOWN_VOID_RENAMES = [
        'bgsound' => 'param',
        'embed' => 'param',
        'keygen' => 'param',
        'source' => 'param',
        'track' => 'param',
        'wbr' => 'param',
    ];

    /**
     * Apertura di uno di quei tag: il nome finisce dove lo fa finire libxml.
     *
     * Solo l'apertura, e il gruppo vuoto tiene il posto della `/` che
     * renameTags() si aspetta: una chiusura non si rinomina, perché la 2.9 non
     * salta quello che segue il nome e il segnaposto resterebbe come testo.
     * Senza più il contenitore aperto, `</wbr>` non chiude niente né lì né
     * dalla 2.14.
     */
    private const UNKNOWN_VOID_PATTERN = '~<()(bgsound|embed|keygen|source|track|wbr)(?=[\t\n\f\r />])~i';

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $body = self::parse(self::normalizeInput($html));

        if ($body === null) {
            return '';
        }

        $output = trim(self::normalizeNewlines(self::serializeChildren($body)));

        return self::isBlank($output) ? '' : $output;
    }

    /**
     * UTF-8 valido, a capo solo `\n`, niente caratteri di controllo (NUL
     * compreso), i `<` sciolti come testo, via le chiusure di `body` e `html` e
     * i caratteri non ASCII come entità numeriche, così il parser non deve
     * indovinare la codifica.
     */
    private static function normalizeInput(string $html): string
    {
        $html = mb_scrub($html, 'UTF-8');

        // libxml 2.15 normalizza gli a capo da sé, la 2.9 tiene i `\r`. Come
        // nella preelaborazione di HTML5 si fa prima di togliere qualcosa: un
        // `\r` e un `\n` separati da un carattere di controllo o da un
        // `</body>` restano due a capo.
        $html = self::normalizeNewlines($html);
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

    /**
     * `\r\n` e `\r` diventano `\n`, come nella preelaborazione dell'input di
     * HTML5. Sull'input prima della lettura, e di nuovo sul risultato: un
     * `&#13;` mette un `\r` nel documento, che scritto così com'è un browser
     * leggerebbe come `\n` e una seconda pulizia cambierebbe.
     */
    private static function normalizeNewlines(string $html): string
    {
        return str_replace(["\r\n", "\r"], "\n", $html);
    }

    private static function parse(string $html): ?DOMElement
    {
        $document = new DOMDocument('1.0', 'UTF-8');

        // `iframe`, `noembed` e `noframes` si rinominano con ogni libxml: il
        // testo semplice che si portano via cambia con la versione. Il resto
        // dipende dalla libxml caricata (LIBXML_VERSION è quella con cui è
        // stato compilato PHP): dalla 2.14 si aggiungono `xmp` e `plaintext`,
        // che lì sono testo semplice, prima i tag vuoti di HTML5, che lì si
        // aprono come contenitori. `xmp` e `plaintext` con la 2.9 non si
        // rinominano: li legge già come gli altri tag, e in una loro chiusura
        // non salta quello che segue il nome (`</listing/…>` lascerebbe `/…>`
        // come testo).
        $renames = self::DROPPED_RAW_TEXT_RENAMES;
        $passes = [];

        if ((int) LIBXML_LOADED_VERSION >= 21400) {
            $renames += self::RAW_TEXT_RENAMES;
        }

        $passes[] = [self::rawTextPattern($renames), $renames];

        // I tag vuoti stanno in un passaggio a sé: di loro si rinomina solo
        // l'apertura, e il segnaposto che finisce in un nome già riscritto non
        // è più `<` più il nome vecchio, quindi nessun passaggio tocca quello
        // che ha fatto l'altro.
        if ((int) LIBXML_LOADED_VERSION < 21400) {
            $passes[] = [self::UNKNOWN_VOID_PATTERN, self::UNKNOWN_VOID_RENAMES];
            $renames += self::UNKNOWN_VOID_RENAMES;
        }

        $marker = null;

        foreach ($passes as [$pattern, $group]) {
            if (preg_match($pattern, $html) !== 1) {
                continue;
            }

            $marker ??= bin2hex(random_bytes(8));
            $html = self::renameTags($html, $pattern, $group, $marker);
        }

        // Prima della 2.14 il testo semplice di uno script o di uno style
        // finisce anche dove in HTML5 continua. Dopo le rinomine, perché conta
        // anche lo `style/…` scritto da renameTags().
        if ((int) LIBXML_LOADED_VERSION < 21400 && preg_match(self::RAW_TEXT_OPEN_PATTERN, $html) === 1) {
            $marker ??= bin2hex(random_bytes(8));
            $html = preg_replace(self::PREFIXED_NAME_PATTERN, '<${1}'.self::prefix($marker), $html) ?? $html;
            $html = preg_replace(self::RAW_TEXT_START_PATTERN, '>'.self::prefix($marker), $html) ?? $html;
        }

        // Niente `</body></html>` in fondo: a fine input libxml chiude da sé, e
        // un attributo, un `textarea` o un `title` (da libxml 2.14) lasciati
        // aperti se lo mangerebbero come testo.
        $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'
            .$html;

        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML($wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | self::PARSE_RECOVER);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($loaded !== true) {
            return null;
        }

        if ($marker !== null) {
            self::restoreRenamedTags($document, $renames, $marker);
        }

        $body = $document->getElementsByTagName('body')->item(0);

        return $body instanceof DOMElement ? $body : null;
    }

    /** Apertura o chiusura di uno dei tag da rinominare: il nome finisce dove lo fa finire libxml. */
    private static function rawTextPattern(array $renames): string
    {
        return '~<(/?)('.implode('|', array_keys($renames)).')(?=[\t\n\f\r />])~i';
    }

    /**
     * Rinomina i tag che le due libxml non leggono nello stesso modo, perché si
     * leggano come testo semplice (`iframe`, `noembed`, `noframes`), come tag
     * normali (`xmp`, `plaintext`) o come tag vuoti (`wbr`, `source`…) con
     * tutte e due.
     *
     * Su `xmp` e `plaintext`, che libxml dalla 2.14 legge come testo semplice,
     * le entità scritte da normalizeInput() restavano come testo e si
     * escapavano di nuovo (`perch&amp;#233;`), e i tag dentro non si leggevano:
     * il nome nuovo li fa leggere come li legge la 2.9. Su `iframe`, `noembed`
     * e `noframes` vale il contrario: il nome di `style` li fa leggere come
     * testo semplice anche con la 2.9, che li legge come tag normali. I tag
     * vuoti di HTML5 prima della 2.14 si aprono invece come contenitori: il
     * nome nuovo è vuoto anche lì.
     *
     * Il nome nuovo è seguito da `/`, dal segnaposto e dal nome vecchio: libxml
     * fa finire il nome al `/` e legge il resto come un attributo, che si perde
     * con il tag. Dove il tag era testo (dentro `textarea` o `title`, nel
     * valore di un attributo) restoreRenamedTags() rimette il nome vecchio. Il
     * pezzo aggiunto (lettere, cifre, `-` e `/`, senza `--`) non sposta la fine
     * di un attributo, di un commento o di un testo.
     */
    private static function renameTags(string $html, string $pattern, array $renames, string $marker): string
    {
        return preg_replace_callback(
            $pattern,
            // Gruppo 1: la `/` di una chiusura, vuoto per un'apertura.
            static fn (array $match): string => '<'.$match[1].$renames[strtolower($match[2])].'/'.$marker.$match[2],
            $html
        ) ?? $html;
    }

    /**
     * Il pezzo che PREFIXED_NAME_PATTERN mette davanti al nome e
     * RAW_TEXT_START_PATTERN davanti al `</`. Comincia con una lettera, come
     * un nome di tag per libxml; come quello di renameTags() non sposta la
     * fine di un attributo, di un commento o di un testo.
     */
    private static function prefix(string $marker): string
    {
        return 'wi-'.$marker.'-';
    }

    /** Toglie quello che renameTags() e prefix() hanno aggiunto dove è rimasto: nel testo e negli attributi. */
    private static function restoreRenamedTags(DOMDocument $document, array $renames, string $marker): void
    {
        $inserted = array_unique(array_map(static fn (string $name): string => $name.'/'.$marker, $renames));
        $inserted[] = self::prefix($marker);
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
