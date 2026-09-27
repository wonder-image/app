<?php
/** php tests/Support/Html/SafeHtmlTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Support\Html\SafeHtml;

/** Nessun vettore deve lasciare nel risultato questi pezzi. */
function inert(string $html): bool
{
    $lower = strtolower($html);

    foreach (['<script', 'javascript:', 'vbscript:', 'data:', ' on', '<iframe', '<svg', '<style', '<object', '<embed', 'style=', 'srcdoc'] as $needle) {
        if (str_contains($lower, $needle)) {
            echo "    trovato «{$needle}» in: {$html}\n";

            return false;
        }
    }

    return true;
}

function same(string $got, string $expected): bool
{
    if ($got === $expected) {
        return true;
    }

    echo "    atteso: " . var_export($expected, true) . "\n    avuto:  " . var_export($got, true) . "\n";

    return false;
}

/** Il documento in cui SafeHtml avvolge l'input non deve finire nel risultato. */
function noWrapper(string $html): bool
{
    if (preg_match('/body|html/i', $html) !== 1) {
        return true;
    }

    echo "    pezzi del documento nel risultato: {$html}\n";

    return false;
}

/** I nomi e il segnaposto con cui SafeHtml fa leggere xmp e plaintext non devono finire nel risultato. */
function noRename(string $html): bool
{
    if (preg_match('/listing|wi-plaintext|[0-9a-f]{16}/i', $html) !== 1) {
        return true;
    }

    echo "    tracce della rinomina nel risultato: {$html}\n";

    return false;
}

# ---------------------------------------------------------------------------
# Il testo ammesso passa com'è
# ---------------------------------------------------------------------------

check('paragrafi e formattazione ammessa restano identici', fn () => same(
    SafeHtml::clean('<p>Uno <strong>due</strong> <b>tre</b> <em>quattro</em> <i>cinque</i> <u>sei</u> <s>sette</s> <strike>otto</strike> <del>nove</del></p>'),
    '<p>Uno <strong>due</strong> <b>tre</b> <em>quattro</em> <i>cinque</i> <u>sei</u> <s>sette</s> <strike>otto</strike> <del>nove</del></p>'
));

check('br resta, in forma HTML', fn () => same(
    SafeHtml::clean('<p>riga uno<br/>riga due<br>riga tre</p>'),
    '<p>riga uno<br>riga due<br>riga tre</p>'
));

check('più paragrafi', fn () => same(
    SafeHtml::clean('<p>a</p><p>b</p>'),
    '<p>a</p><p>b</p>'
));

check('testo senza tag resta testo', fn () => same(SafeHtml::clean('solo testo'), 'solo testo'));

check('i tag maiuscoli diventano minuscoli', fn () => same(SafeHtml::clean('<P><STRONG>x</STRONG></P>'), '<p><strong>x</strong></p>'));

# ---------------------------------------------------------------------------
# UTF-8
# ---------------------------------------------------------------------------

check('«perché è» fa il giro intero', fn () => same(SafeHtml::clean('<p>perché è</p>'), '<p>perché è</p>'));

check('accenti, euro, emoji e virgolette tipografiche', fn () => same(
    SafeHtml::clean('<p>Città · 1.299,90 € — «ciao» “sì” 😀 ñ ü</p>'),
    '<p>Città · 1.299,90 € — «ciao» “sì” 😀 ñ ü</p>'
));

check('le entità diventano caratteri, &nbsp; resta &nbsp;', fn () => same(
    SafeHtml::clean('<p>perch&eacute; &egrave;&nbsp;qui</p>'),
    '<p>perché è&nbsp;qui</p>'
));

check('i caratteri speciali del testo restano escapati', fn () => same(
    SafeHtml::clean('<p>a &lt;b&gt; &amp; c "d" \'e\'</p>'),
    '<p>a &lt;b&gt; &amp; c "d" \'e\'</p>'
));

check('UTF-8 non valido non rompe il risultato', function () {
    $out = SafeHtml::clean("<p>ok \xC3\x28 fine</p>");

    return mb_check_encoding($out, 'UTF-8') && str_contains($out, 'ok') && str_contains($out, 'fine');
});

# ---------------------------------------------------------------------------
# Vuoti dell'editor
# ---------------------------------------------------------------------------

foreach ([
    'stringa vuota' => '',
    'solo spazi' => "  \n\t ",
    '<p><br></p>' => '<p><br></p>',
    '<p></p>' => '<p></p>',
    '<p> </p>' => '<p> </p>',
    '&nbsp;' => '&nbsp;',
    '<p>&nbsp;</p>' => '<p>&nbsp;</p>',
    'più paragrafi vuoti' => "<p><br></p>\n<p>&nbsp;</p><p></p>",
    'solo formattazione vuota' => '<p><strong></strong><em> </em></p>',
    'solo uno script' => '<script>alert(1)</script>',
    'solo un commento' => '<!-- nota -->',
] as $label => $input) {
    check("vuoto: {$label} → ''", fn () => same(SafeHtml::clean($input), ''));
}

check('un paragrafo con testo e un vuoto: il vuoto resta (non si ripulisce la struttura)', fn () => same(
    SafeHtml::clean('<p>a</p><p><br></p>'),
    '<p>a</p><p><br></p>'
));

# ---------------------------------------------------------------------------
# Link
# ---------------------------------------------------------------------------

check('link https resta con solo href', fn () => same(
    SafeHtml::clean('<a href="https://esempio.it/pagina?a=1&amp;b=2" target="_blank" rel="noopener" title="t" class="c" style="color:red" onclick="x()">sito</a>'),
    '<a href="https://esempio.it/pagina?a=1&amp;b=2">sito</a>'
));

foreach ([
    'http' => 'http://esempio.it',
    'mailto' => 'mailto:info@esempio.it',
    'tel' => 'tel:+390123456789',
    'schema maiuscolo' => 'HTTPS://ESEMPIO.IT',
] as $label => $href) {
    check("link {$label} ammesso", fn () => same(
        SafeHtml::clean('<a href="' . $href . '">x</a>'),
        '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">x</a>'
    ));
}

check('gli spazi attorno all\'href si tolgono', fn () => same(
    SafeHtml::clean('<a href="  https://esempio.it  ">x</a>'),
    '<a href="https://esempio.it">x</a>'
));

foreach ([
    'javascript' => 'javascript:alert(1)',
    'javascript maiuscolo' => 'JaVaScRiPt:alert(1)',
    'javascript con spazi davanti' => '   javascript:alert(1)',
    'javascript con tab dentro' => "java\tscript:alert(1)",
    'javascript con a capo dentro' => "java\nscript:alert(1)",
    'javascript con entità' => '&#106;avascript:alert(1)',
    'javascript con entità esadecimali' => '&#x6A;&#x61;&#x76;&#x61;&#x73;&#x63;&#x72;&#x69;&#x70;&#x74;&#x3A;alert(1)',
    'javascript con entità senza ;' => '&#106&#97&#118&#97&#115&#99&#114&#105&#112&#116&#58alert(1)',
    'javascript con tab come entità' => 'jav&#x09;ascript:alert(1)',
    'javascript con carattere di controllo' => "\x01javascript:alert(1)",
    'javascript con entità doppie' => '&amp;#106;avascript:alert(1)',
    'javascript con spazio a larghezza zero' => "java\u{200B}script:alert(1)",
    'data' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    'vbscript' => 'vbscript:msgbox(1)',
    'protocol-relative' => '//evil.example/x',
    'protocol-relative con backslash' => '\\\\evil.example/x',
    'relativo' => '/pagina',
    'ancora' => '#sezione',
    'file' => 'file:///etc/passwd',
    'vuoto' => '',
] as $label => $href) {
    check("link {$label} scartato, il testo resta", function () use ($href) {
        $out = SafeHtml::clean('<p><a href="' . $href . '">clic</a></p>');

        return same($out, '<p>clic</p>') && inert($out);
    });
}

check('link senza href: resta il testo', fn () => same(SafeHtml::clean('<a name="x">testo</a>'), 'testo'));

check('xlink:href e altri attributi del link scartati', fn () => same(
    SafeHtml::clean('<a xlink:href="javascript:alert(1)" href="https://ok.it">x</a>'),
    '<a href="https://ok.it">x</a>'
));

check('le virgolette nell\'href non escono dall\'attributo', function () {
    $out = SafeHtml::clean('<a href=\'https://ok.it/"onmouseover="alert(1)\'>x</a>');

    return same($out, '<a href="https://ok.it/&quot;onmouseover=&quot;alert(1)">x</a>');
});

# ---------------------------------------------------------------------------
# Tag e attributi non ammessi
# ---------------------------------------------------------------------------

check('attributi dei tag ammessi scartati', fn () => same(
    SafeHtml::clean('<p class="x" style="color:red" onclick="alert(1)" id="y" data-a="b">t</p>'),
    '<p>t</p>'
));

check('tag non ammessi scartati tenendo il contenuto', fn () => same(
    SafeHtml::clean('<div><span style="x">uno</span> <h2>due</h2> <ul><li>tre</li></ul> <font color="red">quattro</font></div>'),
    'uno due tre quattro'
));

foreach (['script', 'style', 'iframe', 'object', 'template', 'noscript', 'svg', 'math'] as $tag) {
    check("<{$tag}> tolto con il contenuto", function () use ($tag) {
        $out = SafeHtml::clean("<p>prima</p><{$tag}>SEGRETO alert(1)</{$tag}><p>dopo</p>");

        return same($out, '<p>prima</p><p>dopo</p>') && inert($out);
    });
}

# In HTML5 <embed> è vuoto: quello che lo segue non è suo e il browser lo mostra.
# libxml dalla 2.14 lo legge così, la 2.9 gli metteva dentro il testo seguente:
# il risultato deve essere lo stesso con tutte e due.

check('<embed> tolto, il testo che segue resta come nel browser', function () {
    $out = SafeHtml::clean('<p>prima</p><embed>SEGRETO alert(1)</embed><p>dopo</p>');

    return same($out, '<p>prima</p>SEGRETO alert(1)<p>dopo</p>') && inert($out);
});

check('<embed> senza chiusura non si porta via il resto del documento', fn () => same(
    SafeHtml::clean('<p>Guarda <embed src="video.swf"> e poi leggi.</p><embed src="x"><p>due</p><p>tre</p>'),
    '<p>Guarda  e poi leggi.</p><p>due</p><p>tre</p>'
));

check('<embed> con src javascript o data: sparisce, quello che segue passa pulito', function () {
    $out = SafeHtml::clean('<embed src="javascript:alert(1)"><embed type="image/svg+xml" src="data:image/svg+xml;base64,PHN2Zz4="><embed><script>alert(2)</script><a href="javascript:alert(3)">x</a></embed>ok');

    return same($out, 'xok') && inert($out);
});

check('<img onerror> tolto', function () {
    $out = SafeHtml::clean('<p>a<img src="x" onerror="alert(1)">b</p>');

    return same($out, '<p>ab</p>') && inert($out);
});

check('<svg onload> con script dentro tolto', function () {
    $out = SafeHtml::clean('<svg onload="alert(1)"><script>alert(2)</script><a xlink:href="javascript:alert(3)">x</a></svg>ok');

    return same($out, 'ok') && inert($out);
});

check('<math> con link javascript tolto', function () {
    $out = SafeHtml::clean('<math><mtext><a href="javascript:alert(1)">x</a></mtext></math>ok');

    return same($out, 'ok') && inert($out);
});

check('commenti tolti, compresi i condizionali', function () {
    $out = SafeHtml::clean('<p>a<!-- nascosto --></p><!--[if IE]><script>alert(1)</script><![endif]--><p>b</p>');

    return same($out, '<p>a</p><p>b</p>') && inert($out);
});

check('script annidato in un tag scartato', function () {
    $out = SafeHtml::clean('<div><span><script>alert(1)</script>testo</span></div>');

    return same($out, 'testo') && inert($out);
});

check('script spezzato non si ricompone', function () {
    $out = SafeHtml::clean('<scr<script>ipt>alert(1)</scr</script>ipt>');

    return inert($out) && !str_contains($out, '<');
});

check('form, input, button, textarea e select non passano', function () {
    $out = SafeHtml::clean('<form action="https://x"><input name="a" value="v"><button formaction="javascript:alert(1)">b</button><textarea>t</textarea><select><option>o</option></select></form>');

    return inert($out) && !str_contains($out, '<form') && !str_contains($out, '<input') && !str_contains($out, '<button');
});

check('meta, link, base e object non passano', function () {
    $out = SafeHtml::clean('<meta http-equiv="refresh" content="0;url=javascript:alert(1)"><link rel="stylesheet" href="x"><base href="javascript:/"><object data="x"></object><p>ok</p>');

    return same($out, '<p>ok</p>') && inert($out);
});

check('iframe con srcdoc tolto', function () {
    $out = SafeHtml::clean('<iframe srcdoc="<script>alert(1)</script>"></iframe><p>ok</p>');

    return same($out, '<p>ok</p>') && inert($out);
});

check('tag con namespace inventato non fa passare lo script', function () {
    $out = SafeHtml::clean('<svg:script>alert(1)</svg:script><p>ok</p>');

    return inert($out) && str_contains($out, '<p>ok</p>');
});

check('< nel testo senza tag resta testo escapato', function () {
    $out = SafeHtml::clean('<p>3 < 5 e 5 > 3</p>');

    return same($out, '<p>3 &lt; 5 e 5 &gt; 3</p>');
});

check('tag non chiusi vengono chiusi', fn () => same(SafeHtml::clean('<p><strong>aperto'), '<p><strong>aperto</strong></p>'));

check('NUL byte tolto', function () {
    $out = SafeHtml::clean("<p>a\0b</p><scr\0ipt>alert(1)</script>");

    return !str_contains($out, "\0") && inert($out);
});

check('pulire due volte dà lo stesso risultato', function () {
    $input = '<p>perché <a href="https://x.it" onclick="y">link</a> <span>z</span><br>&nbsp;</p><script>x</script>';
    $once = SafeHtml::clean($input);

    return same(SafeHtml::clean($once), $once);
});

check('testo lungo non si perde', function () {
    $text = str_repeat('Descrizione del prodotto con accenti àèìòù. ', 2000);
    $out = SafeHtml::clean('<p>' . $text . '</p>');

    return same($out, '<p>' . $text . '</p>');
});

check('& sciolto e entità sconosciute restano testo escapato', fn () => same(
    SafeHtml::clean('a & b &sconosciuta;'),
    'a &amp; b &amp;sconosciuta;'
));

check('istruzioni di elaborazione (<?php … ?>) tolte', function () {
    $out = SafeHtml::clean('<p>a <?php echo 1; ?>b</p>');

    return same($out, '<p>a b</p>');
});

check('textarea e xmp con script dentro non fanno passare lo script', function () {
    $out = SafeHtml::clean('<textarea><script>alert(1)</script></textarea><xmp><script>alert(2)</script></xmp><p>ok</p>');

    return inert($out) && str_contains($out, '<p>ok</p>');
});

check('link annidato javascript dentro un link buono: quello cattivo perde il tag', function () {
    $out = SafeHtml::clean('<a href="https://a.it">uno <a href="javascript:alert(1)">due</a></a>');

    return inert($out) && str_contains($out, '<a href="https://a.it">') && str_contains($out, 'due');
});

check('entità che scrivono un tag restano testo', fn () => same(
    SafeHtml::clean('<p>&#60;script&#62;alert(1)&#60;/script&#62;</p>'),
    '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'
));

check('body con onload: resta solo il contenuto', fn () => same(
    SafeHtml::clean('<body onload="alert(1)"><p>ok</p></body>'),
    '<p>ok</p>'
));

check('annidamento normale (100 livelli) tiene il testo', fn () => same(
    SafeHtml::clean(str_repeat('<span>', 100) . 'x' . str_repeat('</span>', 100)),
    'x'
));

check('annidamento assurdo (oltre il limite di libxml): esce vuoto, senza errori', fn () => same(
    SafeHtml::clean(str_repeat('<span>', 5000) . '<script>alert(1)</script>x' . str_repeat('</span>', 5000)),
    ''
));

# ---------------------------------------------------------------------------
# Tag e attributi lasciati aperti a fine testo
# ---------------------------------------------------------------------------

# Da libxml 2.14 il testo di xmp, textarea e title finisce solo alla loro
# chiusura, quello di plaintext mai: lasciati aperti arrivano a fine input, e
# la chiusura del documento in cui SafeHtml avvolge l'input non deve diventare
# testo. Il risultato deve essere lo stesso con la 2.9.

foreach (['xmp', 'textarea', 'title', 'plaintext'] as $tag) {
    check("<{$tag}> non chiuso: resta il testo, niente pezzi del documento", fn () => same(
        SafeHtml::clean("<p>a</p><{$tag}>b"),
        '<p>a</p>b'
    ));

    check("<{$tag}> vuoto e non chiuso → ''", fn () => same(SafeHtml::clean("<{$tag}>"), ''));
}

check('xmp, textarea e title chiusi: resta il testo e quello che segue', fn () => same(
    SafeHtml::clean('<p>a</p><xmp>b</xmp><textarea>c &amp; d</textarea><title>e</title><p>f</p>'),
    '<p>a</p>bc &amp; de<p>f</p>'
));

# Un tag tagliato a metà ogni libxml lo chiude a modo suo (la 2.9 tiene il link
# vuoto, la 2.15 lo toglie): conta che la chiusura del documento resti fuori.

foreach ([
    'tra virgolette' => '<p>a</p><a href="https://x.it',
    'senza virgolette' => '<p>a</p><a href=https://x.it',
] as $label => $input) {
    check("href {$label} lasciato aperto: niente pezzi del documento", function () use ($input) {
        $out = SafeHtml::clean($input);

        return noWrapper($out) && str_starts_with($out, '<p>a</p>');
    });
}

# ---------------------------------------------------------------------------
# Chiusure di body e html scritte nel testo
# ---------------------------------------------------------------------------

# Un </body> o </html> senza apertura chiuderebbe il body del documento in cui
# SafeHtml avvolge l'input, e quello che segue resterebbe fuori. Come in HTML5,
# dove quel contenuto torna nel body, il testo continua. Il risultato deve
# essere lo stesso con ogni libxml.

check('html e body scritti e chiusi dall\'utente: quello che segue resta', fn () => same(
    SafeHtml::clean('<html><body><p>ok</p></body></html><p>dopo</p>'),
    '<p>ok</p><p>dopo</p>'
));

check('<html> dell\'utente abbinato: quello che segue resta', fn () => same(
    SafeHtml::clean('<html><p>a</p></html><p>b</p>'),
    '<p>a</p><p>b</p>'
));

check('<body> dell\'utente abbinato e un </html> in più: quello che segue resta', fn () => same(
    SafeHtml::clean('<body><p>a</p></body><p>b</p></html>c'),
    '<p>a</p><p>b</p>c'
));

check('</body> senza apertura: il paragrafo che segue resta', fn () => same(
    SafeHtml::clean('<p>a</p></body><p>b</p>'),
    '<p>a</p><p>b</p>'
));

check('</html> senza apertura: il paragrafo che segue resta', fn () => same(
    SafeHtml::clean('<p>a</p></html><p>b</p>'),
    '<p>a</p><p>b</p>'
));

check('</body> in testa: il testo resta', fn () => same(SafeHtml::clean('</body>testo'), 'testo'));

check('</body></html> a metà: resta tutto quello che segue', fn () => same(
    SafeHtml::clean('<p>a</p></body></html>b<p>c</p>'),
    '<p>a</p>b<p>c</p>'
));

check('chiusure ripetute: il testo continua ogni volta', fn () => same(
    SafeHtml::clean('<p>a</p></body><p>b</p></html></body><p>c</p>'),
    '<p>a</p><p>b</p><p>c</p>'
));

check('</body> dentro un paragrafo: il paragrafo continua', fn () => same(
    SafeHtml::clean('<p>a</body>b</p>c'),
    '<p>ab</p>c'
));

check('</body> e </html> in fondo, ognuno sulla sua riga', fn () => same(
    SafeHtml::clean("<p>a</p>\n</body>\n</html>"),
    '<p>a</p>'
));

check('documento intero con doctype e head: resta il testo, anche dopo </html>', fn () => same(
    SafeHtml::clean("<!DOCTYPE html>\n<html lang=\"it\">\n<head>\n<meta charset=\"utf-8\">\n</head>\n<body>\n<p>a</p>\n</body>\n</html>\n<p>b</p>"),
    "<p>a</p>\n\n\n<p>b</p>"
));

# Le varianti della chiusura. Virgolette, simboli o lettere non ASCII attaccati
# al nome chiudono il body con libxml 2.9, non dalla 2.14: si tolgono lo stesso,
# così il risultato non cambia con la versione.

foreach ([
    'maiuscola' => '</BODY>',
    'con uno spazio' => '</body >',
    'con un a capo' => "</Html\n>",
    'con un tab' => "</body\t>",
    'con attributi' => '</body class="x" data-x=\'1\'>',
    'con la barra' => '</body/>',
    'senza >' => '</body',
    'con un attributo e senza >' => '</body x',
    'con le virgolette attaccate al nome' => '</body"x">',
    'con un simbolo attaccato al nome' => '</body@>',
    'con una lettera accentata attaccata al nome' => "</body\u{00E9}>",
] as $label => $close) {
    check("chiusura {$label}: il paragrafo che segue resta", fn () => same(
        SafeHtml::clean("<p>a</p>{$close}<p>b</p>"),
        '<p>a</p><p>b</p>'
    ));
}

# Dentro xmp, textarea, title e plaintext libxml dalla 2.14 legge </body> come
# testo, la 2.9 come chiusura: si toglie con tutte e due, e il testo attorno
# resta.

foreach (['xmp', 'textarea', 'title'] as $tag) {
    check("</body> dentro <{$tag}>: si toglie, il testo resta", fn () => same(
        SafeHtml::clean("<{$tag}>b</body>c</{$tag}><p>d</p>"),
        'bc<p>d</p>'
    ));
}

check('</body> dentro <plaintext>: si toglie, il testo resta', fn () => same(
    SafeHtml::clean('<plaintext>b</body>c'),
    'bc'
));

# Nei tag che spariscono con il contenuto, un </body> non deve portarsi via
# quello che segue il tag.

foreach (['script', 'style', 'iframe', 'noembed', 'noframes'] as $tag) {
    check("</body> dentro <{$tag}>: il tag sparisce intero, quello che segue resta", function () use ($tag) {
        $out = SafeHtml::clean("<p>a</p><{$tag}>x</body>alert(1)</{$tag}><p>b</p>");

        return same($out, '<p>a</p><p>b</p>') && inert($out);
    });
}

check('"</body" in una stringa di uno script: lo script sparisce intero', function () {
    $out = SafeHtml::clean('<script>if (s.indexOf("</body") > 0) { alert(1); }</script><p>d</p>');

    return same($out, '<p>d</p>') && inert($out);
});

# La chiusura tolta non arriva oltre la fine di un commento o di un attributo
# che la contengono: commento e attributo spariscono interi, senza pezzi.

check('</body dentro un commento: il commento sparisce intero', fn () => same(
    SafeHtml::clean('<p>a</p><!-- prima di </body "x" -- dopo --><p>b</p>'),
    '<p>a</p><p>b</p>'
));

check('</body dentro un commento chiuso con --!>: il commento sparisce intero', fn () => same(
    SafeHtml::clean('<p>a</p><!-- prima di </body --!><p>b</p>'),
    '<p>a</p><p>b</p>'
));

check('</body dentro un attributo: l\'attributo sparisce intero', fn () => same(
    SafeHtml::clean('<a href="https://x.it/" title="</body x">l</a><p>b</p>'),
    '<a href="https://x.it/">l</a><p>b</p>'
));

foreach ([
    'istruzione di elaborazione' => '<p>a</p><?php echo "</body>"; ?><p>b</p>',
    'CDATA' => '<p>a</p><![CDATA[</body>]]><p>b</p>',
] as $label => $input) {
    check("</body> dentro un {$label}: sparisce senza pezzi", fn () => same(
        SafeHtml::clean($input),
        '<p>a</p><p>b</p>'
    ));
}

# Le chiusure si tolgono dopo i caratteri di controllo e i < sciolti.

check('</body> spezzato da un carattere di controllo: si toglie lo stesso', fn () => same(
    SafeHtml::clean("<p>a</p></bo\x01dy><p>b</p>"),
    '<p>a</p><p>b</p>'
));

check('< sciolto davanti a </body>: resta testo, come in HTML5', fn () => same(
    SafeHtml::clean('<</body>p>b'),
    '&lt;p&gt;b'
));

# Togliendo una chiusura, il testo che aveva attorno può comporne un'altra:
# quella resta come testo e non chiude il body.

foreach ([
    'nel mezzo del nome' => '</bo</body>dy>',
    'subito dopo la barra' => '</</html>body>',
] as $label => $close) {
    check("chiusura tolta {$label} di un'altra: quella resta testo, il paragrafo che segue resta", fn () => same(
        SafeHtml::clean("<p>a</p>{$close}<p>b</p>"),
        '<p>a</p>&lt;/body&gt;<p>b</p>'
    ));
}

check('chiusure tolte una dentro l\'altra: il paragrafo che segue resta', function () {
    $out = SafeHtml::clean('<p>a</p></b</bo</body>dy>ody><p>b</p>');

    return str_starts_with($out, '<p>a</p>') && str_ends_with($out, '<p>b</p>');
});

# Senza un tetto, una chiusura lunghissima esaurisce pcre.backtrack_limit e la
# regex che la toglie non dà risultato.

check('chiusura con una coda enorme di attributi: niente errori, il testo attorno resta', function () {
    $out = SafeHtml::clean('<p>a</p></body ' . str_repeat('-a', 1500000) . '><p>b</p>');

    return str_starts_with($out, '<p>a</p>') && str_ends_with($out, '<p>b</p>');
});
# ---------------------------------------------------------------------------
# Contenuto dei tag che spariscono con il contenuto
# ---------------------------------------------------------------------------

# HTML5 legge il contenuto di script, style, iframe, noembed e noframes come
# testo semplice fino alla loro chiusura: quello che c'è in mezzo è loro, anche
# la chiusura di un tag che li contiene. libxml lo fa solo per script e style,
# e la 2.9 fino al primo </ seguito da una lettera invece che fino alla loro
# chiusura. Spariscono con il contenuto, ma quanto testo si portino via cambia:
# il risultato deve essere quello di HTML5 con ogni libxml.

foreach (['script', 'style', 'iframe', 'noembed', 'noframes'] as $tag) {
    check("<{$tag}> non chiuso in un paragrafo: si porta via il testo che segue", function () use ($tag) {
        $out = SafeHtml::clean("<p>a<{$tag}>b</p>c");

        return same($out, '<p>a</p>') && inert($out) && noRename($out);
    });

    check("chiusura del tag che contiene <{$tag}>: è contenuto suo", function () use ($tag) {
        $out = SafeHtml::clean("<div>a<{$tag}>b</div>c</{$tag}>d");

        return same($out, 'ad') && inert($out) && noRename($out);
    });

    check("chiusura di un li che contiene <{$tag}>: è contenuto suo", function () use ($tag) {
        $out = SafeHtml::clean("<ul><li>a<{$tag}>b</li>c</{$tag}>d</ul>");

        return same($out, 'ad') && inert($out) && noRename($out);
    });
}

# La chiusura di un altro tag dello stesso gruppo non finisce il testo semplice:
# conta solo la propria.

check('</iframe> dentro uno script: lo script sparisce intero', fn () => same(
    SafeHtml::clean('<script>a</iframe>b</script>c'),
    'c'
));

check('</script> dentro un iframe: l\'iframe sparisce intero', fn () => same(
    SafeHtml::clean('<iframe>a</script>b</iframe>c'),
    'c'
));

check('<iframe> dentro <iframe>: chiude la prima chiusura', fn () => same(
    SafeHtml::clean('<iframe>a<iframe>b</iframe>c</iframe>d'),
    'cd'
));

# Eccezione dichiarata: per leggere iframe, noembed e noframes come testo
# semplice anche con la 2.9 SafeHtml li fa leggere con il nome di style, l'unico
# insieme a script che entra in quella lettura con tutte le libxml. Il nome è
# quindi condiviso da style, iframe, noembed e noframes, e le chiusure si
# accoppiano per nome: </style>, </iframe>, </noembed> e </noframes> finiscono
# il testo semplice di qualunque dei quattro, anche prima di quanto direbbe
# HTML5. Il tag sparisce comunque con il contenuto e quello che resta è testo
# escapato.

check('</style> dentro un iframe: l\'iframe sparisce, il resto resta testo', function () {
    $out = SafeHtml::clean('<iframe>a</style>b</iframe>c');

    return same($out, 'bc') && inert($out) && noRename($out);
});

check('</iframe> dentro un noembed: il noembed sparisce, il resto resta testo', function () {
    $out = SafeHtml::clean('<noembed>a</iframe>b</noembed>c');

    return same($out, 'bc') && inert($out) && noRename($out);
});

check('</iframe> dentro uno style: lo style sparisce, il resto resta testo', function () {
    $out = SafeHtml::clean('<style>a</iframe>b</style>c');

    return same($out, 'bc') && inert($out) && noRename($out);
});

# noscript non è in questo gruppo: con lo scripting spento HTML5 lo legge come
# gli altri tag, come fa libxml.

check('<noscript> non chiuso in un paragrafo: il testo che segue resta', fn () => same(
    SafeHtml::clean('<p>a<noscript>b</p>c'),
    '<p>a</p>c'
));

check('chiusura del tag che contiene <noscript>: il testo dopo resta', fn () => same(
    SafeHtml::clean('<div>a<noscript>b</div>c</noscript>d'),
    'acd'
));

# Il nome con cui si fanno leggere non cambia dove il tag comincia e dove
# finisce.

foreach ([
    'maiuscolo' => '<div>a<IFRAME>b</div>c</IFRAME>d',
    'con un attributo' => '<div>a<iframe src="https://x.it/">b</div>c</iframe>d',
    'con srcdoc' => '<div>a<iframe srcdoc="<b>x</b>">b</div>c</iframe>d',
    'senza > nella chiusura' => '<div>a<noembed>b</div>c</noembed d="1">d',
] as $label => $input) {
    check("<iframe> o <noembed> {$label}: si porta via il testo fino alla sua chiusura", function () use ($input) {
        $out = SafeHtml::clean($input);

        return same($out, 'ad') && inert($out) && noRename($out);
    });
}

check('<iframe/> chiuso con la barra: resta vuoto, il testo dopo resta', fn () => same(
    SafeHtml::clean('<p>a<iframe/>b</p>c'),
    '<p>ab</p>c'
));

# Dove il tag resta testo (attributi, textarea, title) non deve restare traccia
# del nome con cui SafeHtml lo fa leggere.

foreach ([
    'tra virgolette' => ['<a href="https://x.it/<iframe>">l</a>', '<a href="https://x.it/&lt;iframe&gt;">l</a>'],
    'maiuscolo' => ['<a href="https://x.it/<NOEMBED>">l</a>', '<a href="https://x.it/&lt;NOEMBED&gt;">l</a>'],
    'senza virgolette' => ['<a href=https://x.it/<noframes>l</a>', '<a href="https://x.it/&lt;noframes">l</a>'],
] as $label => [$input, $expected]) {
    check("iframe, noembed o noframes nell'href {$label}: resta com'era", fn () => same(
        SafeHtml::clean($input),
        $expected
    ));
}

foreach ([
    'textarea' => '<textarea>a<iframe>b</iframe>c</textarea>',
    'title' => '<title>a<noembed>b</noembed>c</title><p>d</p>',
    'una textarea lunga' => '<textarea>' . str_repeat('perché ', 800) . '<noframes>x</noframes></textarea>',
] as $label => $input) {
    check("iframe, noembed o noframes dentro {$label}: senza tracce della rinomina", function () use ($input) {
        $out = SafeHtml::clean($input);

        return noRename($out) && noWrapper($out) && inert($out);
    });
}


# ---------------------------------------------------------------------------
# Contenuto di xmp e plaintext
# ---------------------------------------------------------------------------

# libxml dalla 2.14 legge xmp come raw text e plaintext fino a fine input: le
# entità in cui normalizeInput() scrive le lettere accentate e i < sciolti
# restavano testo e si escapavano di nuovo (perch&amp;#233;), e i tag dentro
# uscivano come testo. La 2.9 li legge come gli altri tag: il risultato deve
# essere il suo con ogni libxml.

foreach ([
    'xmp' => ['<xmp>', '</xmp>'],
    'plaintext' => ['<plaintext>', ''],
] as $tag => [$open, $close]) {
    foreach ([
        'lettere accentate' => ['perché', 'perché'],
        'un < sciolto' => ['3 < 5', '3 &lt; 5'],
        'un\'entità' => ['a &amp; b', 'a &amp; b'],
        'le entità di un tag' => ['&lt;b&gt;', '&lt;b&gt;'],
        'entità con nome e numeriche' => ['&hellip; &euro; &#8364; &#x20AC;', '… € € €'],
        'un tag ammesso' => ['<b>x</b>', '<b>x</b>'],
    ] as $label => [$text, $expected]) {
        check("{$label} dentro <{$tag}>: come negli altri tag", fn () => same(
            SafeHtml::clean($open . $text . $close),
            $expected
        ));
    }

    check("script dentro <{$tag}>: sparisce, quello che segue resta", function () use ($open, $close) {
        $out = SafeHtml::clean($open . '<script>alert(1)</script>' . $close . '<p>ok</p>');

        return same($out, '<p>ok</p>') && inert($out);
    });
}

check('commento dentro <xmp>: si toglie, il testo resta', fn () => same(
    SafeHtml::clean('<xmp><!-- c -->d</xmp>'),
    'd'
));

# Come con la 2.9, xmp chiude il paragrafo aperto e plaintext no, e plaintext
# finisce alla sua chiusura.

check('<xmp> dentro un paragrafo lo chiude', fn () => same(
    SafeHtml::clean('<p>a<xmp>b</xmp>c</p>'),
    '<p>a</p>bc'
));

check('<plaintext> dentro un paragrafo: il paragrafo continua', fn () => same(
    SafeHtml::clean('<p>a<plaintext>b</plaintext>c</p>'),
    '<p>abc</p>'
));

check('</plaintext> chiude plaintext: il paragrafo che segue resta', fn () => same(
    SafeHtml::clean('<plaintext>a</plaintext><p>b</p>'),
    'a<p>b</p>'
));

check('<plaintext> dentro <math>: sparisce con math, quello che segue resta', fn () => same(
    SafeHtml::clean('<math><plaintext>a</plaintext></math><p>b</p>'),
    '<p>b</p>'
));

check('<xmp> dentro <xmp>: resta tutto il testo', fn () => same(
    SafeHtml::clean('<xmp>a<xmp>b</xmp>c</xmp>d'),
    'abcd'
));

check('</b> dentro <xmp> chiude il grassetto', fn () => same(
    SafeHtml::clean('<b><xmp>x</b>y</xmp>z'),
    '<b>x</b>yz'
));

foreach ([
    'maiuscolo' => '<XMP>perché</XMP>',
    'con un attributo' => '<xmp class="x">perché</xmp>',
    'chiuso con la barra' => '<xmp/>perché',
    'composto togliendo un </body>' => '<xm</body>p>perché</xmp>',
] as $label => $input) {
    check("<xmp> {$label}: il testo resta", fn () => same(SafeHtml::clean($input), 'perché'));
}

check('</xmp> senza >: il testo resta', fn () => same(SafeHtml::clean('<xmp>a</xmp'), 'a'));

check('</xmp> con un attributo: il testo che segue resta', fn () => same(
    SafeHtml::clean('<xmp>a</xmp x="1">b'),
    'ab'
));

check('chiusura di body composta dentro <xmp>: resta testo', fn () => same(
    SafeHtml::clean('<xmp>a</bo</body>dy>b</xmp>'),
    'a&lt;/body&gt;b'
));

# Nel valore di un attributo xmp e plaintext restano com'erano.

foreach ([
    'tra virgolette' => ['<a href="https://x.it/<xmp>">l</a>', '<a href="https://x.it/&lt;xmp&gt;">l</a>'],
    'maiuscolo' => ['<a href="https://x.it/<XMP>">l</a>', '<a href="https://x.it/&lt;XMP&gt;">l</a>'],
    'plaintext' => ['<a href="https://x.it/<plaintext>">l</a>', '<a href="https://x.it/&lt;plaintext&gt;">l</a>'],
    'dopo un &amp; letterale' => ['<a href="https://x.it/?a=1&amp;amp;b=<xmp>">l</a>', '<a href="https://x.it/?a=1&amp;amp;b=&lt;xmp&gt;">l</a>'],
    'prima di un\'entità e di una lettera accentata' => ['<a href="https://x.it/<xmp>&amp;x=perché">l</a>', '<a href="https://x.it/&lt;xmp&gt;&amp;x=perché">l</a>'],
    'senza virgolette' => ['<a href=https://x.it/<xmp>l</a>', '<a href="https://x.it/&lt;xmp">l</a>'],
] as $label => [$input, $expected]) {
    check("tag nell'href {$label}: resta com'era", fn () => same(SafeHtml::clean($input), $expected));
}

# Dentro textarea e title libxml dalla 2.14 legge anche i tag come testo, la
# 2.9 come tag: il risultato cambia con la versione, ma non deve restare
# traccia del nome con cui SafeHtml fa leggere xmp e plaintext.

foreach ([
    'textarea' => '<textarea><xmp>perché</xmp></textarea>',
    'title' => '<title><plaintext>perché</title><p>y</p>',
    'una textarea lunga' => '<textarea>' . str_repeat('perché ', 800) . '<xmp>x</xmp></textarea>',
] as $label => $input) {
    check("xmp o plaintext dentro {$label}: il testo resta, senza tracce della rinomina", function () use ($input) {
        $out = SafeHtml::clean($input);

        return noRename($out) && str_contains($out, 'perché');
    });
}

summary();
