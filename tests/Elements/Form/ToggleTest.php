<?php
/** php tests/Elements/Form/ToggleTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Inputs\InputToggle;
use Wonder\App\Theme;

Theme::set('bootstrap');

$render = static fn (InputToggle $input): string => $input->render();

check('FormField::toggle() restituisce un InputToggle', fn () =>
    FormField::key('enabled')->toggle() instanceof InputToggle
);

check('label e descrizione finiscono nel markup', function () use ($render) {
    $html = $render(
        FormField::key('enabled')->toggle()->label('Ordini')->description('Gestione degli ordini.')
    );

    return str_contains($html, 'Ordini')
        && str_contains($html, 'Gestione degli ordini.')
        && str_contains($html, 'form-switch');
});

check('il valore acceso mette la spunta', function () use ($render) {
    $acceso = $render(FormField::key('enabled')->toggle()->value('true'));
    $spento = $render(FormField::key('enabled')->toggle()->value('false'));

    return str_contains($acceso, 'checked') && !str_contains($spento, 'checked');
});

check('un hidden manda il valore anche da spento', function () use ($render) {
    $html = $render(FormField::key('enabled')->toggle()->value('false'));

    return str_contains($html, '<input type="hidden" name="enabled" value="false">');
});

check('i valori si possono cambiare', function () use ($render) {
    $html = $render(FormField::key('stato')->toggle()->values('si', 'no')->value('si'));

    return str_contains($html, 'value="si"') && str_contains($html, 'value="no"');
});

check('disabilitato quando la pagina è in sola lettura', function () use ($render) {
    $html = $render(FormField::key('enabled')->toggle()->disabled());

    return str_contains($html, 'disabled');
});

check('la descrizione è messa in sicurezza', function () use ($render) {
    $html = $render(FormField::key('enabled')->toggle()->description('<script>alert(1)</script>'));

    return !str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;');
});

summary();
