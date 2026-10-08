<?php
/**
 * Code: blocco di codice con evidenziazione e bottone copia, uguale nei due
 * temi, con CSS e script stampati una volta per pagina.
 *
 *   php tests/Themes/CodeTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Components\Code;
use Wonder\Themes\Support\PageAssets;

echo "Code\n";

foreach (['wonder', 'bootstrap'] as $theme) {
    check("{$theme}: radice data-wi-code con linguaggio, intestazione, bottone copia e <pre><code>", function () use ($theme) {
        PageAssets::reset();
        $html = Code::make("echo 'ciao';", 'php')->title('esempio.php')->render($theme);

        return str_contains($html, '<div class="wi-code wi-code-dark" data-wi-code data-wi-code-language="php">')
            && str_contains($html, '<span class="wi-code-title">esempio.php</span>')
            && str_contains($html, '<button type="button" class="wi-code-copy" data-wi-copy data-wi-copy-done="Copiato" aria-label="Copia">')
            && str_contains($html, '<pre class="wi-code-pre"><code class="wi-code-source language-php" data-wi-code-source>')
            && str_contains($html, '<span class="wi-code-keyword">echo</span>');
    });

    check("{$theme}: CSS e script escono una volta sola", function () use ($theme) {
        PageAssets::reset();
        $first = Code::make('a')->render($theme);
        $second = Code::make('b')->render($theme);

        return str_contains($first, '<style data-wi-code-style>') && str_contains($first, '<script data-wi-code-script>')
            && !str_contains($second, '<style data-wi-code-style>') && !str_contains($second, '<script data-wi-code-script>');
    });
}

check('il titolo di default è il linguaggio', function () {
    PageAssets::reset();

    return str_contains(Code::make('x', 'bash')->render('bootstrap'), '<span class="wi-code-title">bash</span>');
});

check('copy(false) toglie il bottone, copyLabels() cambia le etichette', function () {
    PageAssets::reset();
    $without = Code::make('x')->copy(false)->render('bootstrap');
    $labels = Code::make('x')->copyLabels('Copy', 'Copied')->render('bootstrap');

    return !str_contains($without, 'class="wi-code-copy"')
        && str_contains($labels, 'data-wi-copy-done="Copied" aria-label="Copy"')
        && str_contains($labels, '<span data-wi-copy-label>Copy</span>');
});

check('scheme(), lineNumbers() e maxHeight() scrivono classi e variabile CSS', function () {
    PageAssets::reset();
    $html = Code::make('x')->scheme('auto')->lineNumbers()->maxHeight('20rem')->render('wonder');

    return str_contains($html, 'class="wi-code wi-code-auto wi-code-numbered"')
        && str_contains($html, 'style="--wi-code-max-height: 20rem"');
});

check('maxHeight() e scheme() rifiutano valori non validi', function () {
    try { Code::make('x')->maxHeight('alto'); return false; } catch (InvalidArgumentException) {}
    try { Code::make('x')->scheme('blu'); return false; } catch (InvalidArgumentException) {}

    return true;
});

check('il codice è escapato e torna identico da textContent', function () {
    PageAssets::reset();
    $code = "<script>alert('x')</script>";
    $html = Code::make($code, 'html')->render('bootstrap');
    preg_match('#<code[^>]*>(.*?)</code>#s', $html, $match);

    return !str_contains($html, "<script>alert")
        && html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8') === $code;
});

check('id() e addClass() finiscono sulla radice', function () {
    PageAssets::reset();
    $html = Code::make('x')->id('blocco')->addClass('mb-0')->render('bootstrap');

    return str_contains($html, 'class="wi-code wi-code-dark mb-0"') && str_contains($html, 'id="blocco"');
});

check('columnSpan() esplicito incarta il blocco nella colonna del tema', function () {
    PageAssets::reset();
    $bootstrap = Code::make('x')->columnSpan(6)->render('bootstrap');
    $wonder = Code::make('x')->columnSpan(['default' => 12, 'lg' => 6])->render('wonder');

    return str_starts_with(substr($bootstrap, (int) strpos($bootstrap, '<div class="col-span-6">')), '<div class="col-span-6"><div class="wi-code')
        && str_contains($wonder, '<div class="col-6 col-t-12"><div class="wi-code');
});

summary();
