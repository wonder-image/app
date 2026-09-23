<?php
/** php tests/Themes/OptionVisualTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\Support\OptionVisual;

/**
 * Un'opzione può portare con sé un'immagine, un'icona o un colore: le
 * pillole e le spunte li disegnano davanti al nome, il select li passa a
 * Select2 in `data-wi-*`. I valori vengono dalle anagrafiche e si accettano
 * solo nelle forme che non escono dall'attributo.
 */
$opzioni = [
    '1' => ['name' => 'Rosso', 'color' => '#c00'],
    '2' => ['name' => 'Impermeabile', 'icon' => 'bi-droplet'],
    '3' => ['name' => 'Righe', 'image' => '/assets/upload/righe.webp'],
    '4' => 'Semplice',
];

check("vince l'immagine, poi l'icona, poi il colore", function () {
    return OptionVisual::of(['image' => '/a.png', 'icon' => 'bi-star', 'color' => '#fff'])['type'] === 'image'
        && OptionVisual::of(['icon' => 'bi-star', 'color' => '#fff'])['type'] === 'icon'
        && OptionVisual::of(['color' => '#fff'])['type'] === 'color'
        && OptionVisual::of('Rosso')['type'] === '';
});

check('i valori pericolosi si scartano', function () {
    return OptionVisual::icon('bi-star" onmouseover="x') === ''
        && OptionVisual::icon('star') === 'bi-star'
        && OptionVisual::color('red;background:url(x)') === ''
        && OptionVisual::color('#12ab34') === '#12ab34'
        && OptionVisual::image('javascript:alert(1)') === ''
        && OptionVisual::image('data:image/svg+xml,<svg>') === ''
        && OptionVisual::image('/x.png" onerror="y') === ''
        && OptionVisual::image('https://cdn.test/x.png') === 'https://cdn.test/x.png';
});

check('le pillole disegnano il segno davanti al nome', function () use ($opzioni) {
    $html = FormField::key('sizes')->checkbox()->options($opzioni)->pills()->render('bootstrap');

    return str_contains($html, '<i class="bi bi-circle-fill wi-option-visual" style="color:#c00" aria-hidden="true"></i> Rosso')
        && str_contains($html, '<i class="bi bi-droplet wi-option-visual" aria-hidden="true"></i> Impermeabile')
        && str_contains($html, '<img src="/assets/upload/righe.webp"')
        && (bool) preg_match('/for="[^"]*">Semplice<\/label>/', $html);
});

check('anche le spunte', function () use ($opzioni) {
    $html = FormField::key('sizes')->checkbox()->options($opzioni)->render('bootstrap');

    return str_contains($html, 'bi-droplet wi-option-visual');
});

check('il select passa il segno in data-wi-*', function () use ($opzioni) {
    $html = FormField::key('size')->select($opzioni)->render('bootstrap');

    return (bool) preg_match('/<option value="1"[^>]*data-wi-color="#c00"[^>]*>Rosso/', $html)
        && (bool) preg_match('/<option value="2"[^>]*data-wi-icon="bi-droplet"/', $html)
        && (bool) preg_match('/<option value="3"[^>]*data-wi-image="\/assets\/upload\/righe.webp"/', $html)
        && (bool) preg_match('/<option value="4">Semplice/', $html);
});

/*
 * Il campo icona: anteprima, nome e bottone della raccolta. Anche in una
 * colonna di repeater, dove il nome dell'input cambia.
 */
check("il campo icona mostra l'anteprima del valore", function () {
    $html = FormField::key('icon')->icon()->label('Icona')->value('bi-droplet')->render('bootstrap');

    return str_contains($html, '<i class="bi bi-droplet wi-show-icon"')
        && (bool) preg_match('/<input type="text"[^>]*name="icon"[^>]*value="bi-droplet"[^>]*data-wi-icon-picker="true"/', $html)
        && str_contains($html, 'data-wi-icon-picker-open=');
});

check('un valore non valido non finisce nella classe', function () {
    $html = FormField::key('icon')->icon()->value('x" onclick="y')->render('bootstrap');

    return str_contains($html, 'bi-question-square wi-show-icon')
        && !str_contains($html, 'class="bi x"');
});

check('nel tema del sito è un campo di testo', function () {
    $html = FormField::key('icon')->icon()->value('bi-star')->render('wonder');

    return str_contains($html, 'name="icon"') && str_contains($html, 'value="bi-star"');
});

summary();
