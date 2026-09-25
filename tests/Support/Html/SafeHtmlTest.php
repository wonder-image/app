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

# Senza un tetto, una chiusura lunghissima esaurisce pcre.backtrack_limit e la
# regex che la toglie non dà risultato.

check('chiusura con una coda enorme di attributi: niente errori, il testo attorno resta', function () {
    $out = SafeHtml::clean('<p>a</p></body ' . str_repeat('-a', 1500000) . '><p>b</p>');

    return str_starts_with($out, '<p>a</p>') && str_ends_with($out, '<p>b</p>');
});

summary();
