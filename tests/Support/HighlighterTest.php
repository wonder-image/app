<?php
/**
 * Highlighter: token PHP riconosciuti dal tokenizer, HTML e linguaggi
 * generici con pattern leggeri, testo sempre escapato e ricostruibile.
 *
 *   php tests/Support/HighlighterTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Support\Code\Highlighter;

echo "Highlighter\n";

$strip = static fn (string $html): string => html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

check('il testo del risultato è il codice originale, escapato', function () use ($strip) {
    $code = "echo '<b>' . \$x; // commento \"ok\"";

    return $strip(Highlighter::php($code)) === $code
        && !str_contains(Highlighter::php($code), '<b>')
        && $strip(Highlighter::html('<a href="/x">&amp;</a>')) === '<a href="/x">&amp;</a>';
});

check('PHP: parole chiave, variabili, stringhe, numeri, commenti', function () {
    $html = Highlighter::php("// c\n\$a = 'x';\nreturn \$a + 12;");

    return str_contains($html, '<span class="wi-code-comment">// c</span>')
        && str_contains($html, '<span class="wi-code-variable">$a</span>')
        && str_contains($html, '<span class="wi-code-string">&#039;x&#039;</span>')
        && str_contains($html, '<span class="wi-code-keyword">return</span>')
        && str_contains($html, '<span class="wi-code-number">12</span>');
});

check('PHP: classi, metodi statici, metodi e proprietà', function () {
    $html = Highlighter::php("use Wonder\\Elements\\Components\\Button;\nButton::make('a')->variant('b')->size;\nnew Button();\ntrue;");

    return str_contains($html, '<span class="wi-code-class">Wonder\\Elements\\Components\\Button</span>')
        && str_contains($html, '<span class="wi-code-class">Button</span>::<span class="wi-code-function">make</span>')
        && str_contains($html, '-&gt;<span class="wi-code-function">variant</span>')
        && str_contains($html, '-&gt;<span class="wi-code-property">size</span>')
        && str_contains($html, '<span class="wi-code-keyword">new</span> <span class="wi-code-class">Button</span>')
        && str_contains($html, '<span class="wi-code-constant">true</span>');
});

check('PHP: un tag di apertura già presente non viene raddoppiato', function () use ($strip) {
    $code = "<?php\necho 1;";

    return $strip(Highlighter::php($code)) === $code && substr_count(Highlighter::php($code), '&lt;?php') === 1;
});

check('HTML: tag, attributi, valori e commenti', function () {
    $html = Highlighter::html('<div class="card" hidden><!-- c -->Testo</div>');

    return str_contains($html, '<span class="wi-code-tag">&lt;div</span>')
        && str_contains($html, '<span class="wi-code-attr">class</span>=<span class="wi-code-string">&quot;card&quot;</span>')
        && str_contains($html, '<span class="wi-code-attr">hidden</span>')
        && str_contains($html, '<span class="wi-code-comment">&lt;!-- c --&gt;</span>')
        && str_contains($html, 'Testo<span class="wi-code-tag">&lt;/div</span>');
});

check('bash e json: commenti, stringhe e parole chiave', function () {
    $bash = Highlighter::highlight("# nota\nnpm install wonder-image", 'bash');
    $json = Highlighter::highlight('{"a": true, "b": 12}', 'json');

    return str_contains($bash, '<span class="wi-code-comment"># nota</span>')
        && str_contains($bash, '<span class="wi-code-keyword">npm</span>')
        && str_contains($json, '<span class="wi-code-string">&quot;a&quot;</span>')
        && str_contains($json, '<span class="wi-code-keyword">true</span>')
        && str_contains($json, '<span class="wi-code-number">12</span>');
});

check('text e un linguaggio sconosciuto escapano e basta', function () {
    return Highlighter::highlight('<x>', 'text') === '&lt;x&gt;'
        && Highlighter::highlight('<x>', 'cobol') === '&lt;x&gt;';
});

check('normalizeLanguage() accetta gli alias', function () {
    return Highlighter::normalizeLanguage('JavaScript') === 'js'
        && Highlighter::normalizeLanguage('sh') === 'bash'
        && Highlighter::normalizeLanguage('xml') === 'html'
        && Highlighter::normalizeLanguage('') === 'text';
});

summary();
