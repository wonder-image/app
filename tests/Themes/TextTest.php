<?php
/** php tests/Themes/TextTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Components\Link;
use Wonder\Elements\Components\RichText;
use Wonder\Elements\Components\Text;
use Wonder\Themes\Resolver;
use Wonder\Themes\Wonder\Components\Text as WonderText;

check('wonder: Text e RichText si risolvono sul renderer Text del tema', fn () =>
    Resolver::renderer(Text::class, 'wonder') instanceof WonderText
    && Resolver::renderer(RichText::class, 'wonder') instanceof WonderText);

check('wonder: senza classi né colonna è un paragrafo nudo ed escapato', fn () =>
    Text::make('Eliminare <b>"tutto"</b> & subito?')->render('wonder')
        === '<p>Eliminare &lt;b&gt;&quot;tutto&quot;&lt;/b&gt; &amp; subito?</p>');

check('wonder: utility della lib, nessuna classe Bootstrap', function () {
    $html = Text::make('x')->bold()->small()->muted()->color('danger')->align('end')->lead()->render('wonder');

    return $html === '<p class="subtitle tx-secondary text-small fw-700 tx-danger a-r">x</p>'
        && array_intersect(explode(' ', (string) preg_replace('/^<p class="([^"]*)".*$/s', '$1', $html)), ['fw-bold', 'text-body-secondary', 'text-end', 'lead', 'small']) === [];
});

check('wonder: allineamenti start/left, center, end/right', fn () =>
    str_contains(Text::make('x')->align('start')->render('wonder'), 'class="a-l"')
    && str_contains(Text::make('x')->align('left')->render('wonder'), 'class="a-l"')
    && str_contains(Text::make('x')->align('center')->render('wonder'), 'class="a-c"')
    && str_contains(Text::make('x')->align('right')->render('wonder'), 'class="a-r"'));

check('wonder: italic non aggiunge classi', fn () =>
    Text::make('x')->italic()->render('wonder') === '<p>x</p>');

check('wonder: tag ammessi, gli altri ricadono su p', fn () =>
    Text::make('x')->tag('span')->render('wonder') === '<span>x</span>'
    && Text::make('x')->tag('blockquote')->render('wonder') === '<blockquote>x</blockquote>'
    && Text::make('x')->tag('script')->render('wonder') === '<p>x</p>');

check('wonder: classi, id e attributi dati restano in un solo class', fn () =>
    Text::make('x')->bold()->addClass('mt-4')->id('nota')->attr('data-x', '1')->render('wonder')
        === '<p class="fw-700 mt-4" data-x="1" id="nota">x</p>');

check('wonder: html() stampa il markup, RichText pure', fn () =>
    Text::make('<b>ok</b>')->html()->render('wonder') === '<p><b>ok</b></p>'
    && RichText::make('<ul><li>a</li></ul>')->tag('div')->render('wonder') === '<div><ul><li>a</li></ul></div>');

check('wonder: link inline come <a>, testo escapato, muted su tx-secondary', function () {
    $html = Text::make('Vedi <i> ')
        ->link('/terms?a=1&b=2', 'Termini & c.', ['icon' => 'bi bi-clock'])
        ->append(' e ')
        ->append(Link::to('/privacy', 'Privacy')->muted())
        ->render('wonder');

    return str_starts_with($html, '<p>Vedi &lt;i&gt; <a ')
        && str_contains($html, 'href="/terms?a=1&amp;b=2"')
        && str_contains($html, '><i class="bi bi-clock" aria-hidden="true"></i> Termini &amp; c.</a>')
        && preg_match('#<a [^>]*class="tx-secondary"[^>]*>Privacy</a></p>$#', $html) === 1
        && !str_contains($html, 'text-body-secondary');
});

check('wonder: la colonna avvolge solo se dichiarata', fn () =>
    Text::make('x')->columnSpan(6)->render('wonder') === '<div class="col-6"><p>x</p></div>');

check('bootstrap: resta invariato (wrapper di colonna e classi Bootstrap)', function () {
    $html = Text::make('x')->bold()->muted()->render('bootstrap');

    return str_starts_with($html, '<div class="')
        && str_contains($html, 'text-body-secondary')
        && str_contains($html, 'fw-bold');
});

summary();
