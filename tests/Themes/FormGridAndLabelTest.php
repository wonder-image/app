<?php
/** php tests/Themes/FormGridAndLabelTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Form\Components\{ InputEmail, InputPassword, InputText, Select, Textarea };
use Wonder\Elements\Form\Form;

$formTag = static function (Form $form): string {
    preg_match('/<form [^>]*class="([^"]*)"/', $form->render('wonder'), $m);

    return $m[1] ?? '';
};

check('Form Wonder: colonne e spazio escono come utility della lib', function () use ($formTag) {
    return $formTag((new Form)->columns(2)->gap(3)) === 'wi-form d-grid col-2 gap-3';
});

check('Form Wonder: nessuna classe di griglia Bootstrap', function () use ($formTag) {
    $class = $formTag((new Form)->columns(['default' => 1, 'md' => 2])->gap(4));

    return !preg_match('/\b(row|row-col-\d+|g-\d+)\b/', $class);
});

check('Form Wonder: tablet e telefono hanno le loro varianti', function () use ($formTag) {
    $class = $formTag((new Form)->columns(['default' => 1, 'md' => 2, 'xl' => 3])->gap(['default' => 2, 'lg' => 4]));

    return $class === 'wi-form d-grid col-3 col-t-2 col-p-1 gap-4 gap-t-2';
});

check('Form Bootstrap: invariato', function () {
    preg_match('/<form [^>]*class="([^"]*)"/', (new Form)->columns(2)->gap(3)->render('bootstrap'), $m);

    return str_contains($m[1] ?? '', 'row d-grid row-col-2') && str_contains($m[1] ?? '', 'g-3');
});

foreach (['InputText', 'InputEmail', 'InputPassword', 'Textarea', 'Select'] as $name) {
    $class = 'Wonder\\Elements\\Form\\Components\\' . $name;

    check("$name Bootstrap noFloating: la label sta sopra l'input", function () use ($class) {
        $html = (new $class('campo'))->label('Etichetta')->noFloating()->render('bootstrap');

        return substr_count($html, '<label') === 1
            && !str_contains($html, 'form-floating')
            && (bool) preg_match('/<label class="form-label" for="[^"]+">Etichetta<\/label>.*<(input|select|textarea)/s', $html);
    });

    check("$name Bootstrap: col floating la label resta dentro form-floating", function () use ($class) {
        $html = (new $class('campo'))->label('Etichetta')->render('bootstrap');

        return substr_count($html, '<label') === 1
            && str_contains($html, 'form-floating')
            && !str_contains($html, 'form-label');
    });
}

check('Form Bootstrap noFloating propaga ai figli e stampa le label', function () {
    $html = (new Form)->noFloating()->components([
        (new InputText('city'))->label('Città'),
        (new InputText('zip'))->label('CAP')->noFloating(false),
    ])->render('bootstrap');

    return substr_count($html, 'form-label') === 1 && substr_count($html, 'form-floating') === 1;
});

summary();
