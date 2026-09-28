<?php
/** php tests/Themes/RadioPillsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;

/**
 * Le pillole anche per le radio: il «Preferito» di ogni riga della finestra
 * dei fornitori è una pillola sola, senza il riquadro che scorre e senza un
 * titolo sopra.
 */
check('radio()->pills() rende le voci come pillole', function () {
    $html = FormField::key('preferred')->radio(['7' => 'Preferito', '9' => 'Riserva'])->pills()->render('bootstrap');

    return str_contains($html, 'wi-check-pills')
        && str_contains($html, 'class="btn-check" type="radio" name="preferred" value="7" id="radio-preferred-7"')
        && str_contains($html, 'id="radio-preferred-9"')
        && !str_contains($html, 'overflow-scroll');
});

check('la voce del valore è spuntata', function () {
    $html = FormField::key('preferred')->radio(['7' => 'Preferito', '9' => 'Riserva'])->pills()->value('9')->render('bootstrap');

    return preg_match('/value="9" id="radio-preferred-9"[^>]*checked>/', $html) === 1
        && preg_match('/value="7" id="radio-preferred-7"[^>]*checked>/', $html) === 0;
});

check('con label(\'\') le pillole non hanno titolo', function () {
    $html = FormField::key('preferred')->radio(['7' => 'Preferito'])->pills()->label('')->render('bootstrap');

    return str_contains($html, 'wi-check-pills') && !str_contains($html, '<h6');
});

check('con un\'etichetta il titolo piccolo resta', function () {
    $html = FormField::key('preferred')->radio(['7' => 'Preferito'])->pills()->label('Preferito')->render('bootstrap');

    return str_contains($html, '<h6 class="small text-body-secondary mb-1">Preferito</h6>');
});

check('senza pills() la radio resta il riquadro che scorre', function () {
    $html = FormField::key('preferred')->radio(['7' => 'Preferito'])->label('Preferito')->render('bootstrap');

    return !str_contains($html, 'wi-check-pills')
        && str_contains($html, 'overflow-scroll')
        && str_contains($html, '<h6>Preferito</h6>');
});

check('le spunte a pillole restano come prima', function () {
    $html = FormField::key('sizes')->checkbox()->options(['s' => 'S', 'm' => 'M'])->pills()->label('Taglie')->render('bootstrap');

    return str_contains($html, 'wi-check-pills')
        && str_contains($html, 'type="checkbox" name="sizes[]"')
        && str_contains($html, '<h6 class="small text-body-secondary mb-1">Taglie</h6>');
});

// L'asterisco dell'obbligatorio non è un titolo: da solo farebbe un `<h6>*</h6>`
// sopra la pillola, proprio dove `label('')` voleva la riga compatta.
check('con label(\'\') e required() le pillole restano senza titolo', function () {
    $html = FormField::key('preferred')->radio(['7' => 'Preferito'])->pills()->label('')->required()->render('bootstrap');

    return str_contains($html, 'wi-check-pills') && !str_contains($html, '<h6');
});

check('un\'etichetta obbligatoria tiene il suo asterisco', function () {
    $html = FormField::key('preferred')->radio(['7' => 'Preferito'])->pills()->label('Preferito')->required()->render('bootstrap');

    return preg_match('/<h6 class="small text-body-secondary mb-1">Preferito\s*\*<\/h6>/', $html) === 1;
});

summary();
