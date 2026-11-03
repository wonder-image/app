<?php
/** php tests/Support/Labels/LabelSheetTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Support\Labels\LabelFormat;
use Wonder\Support\Labels\LabelSheet;

$item = ['lines' => ['Maglia', 'Blu / M', 'SKU MAG-BLU-M'], 'highlight' => '29,90 €', 'ean' => '4006381333931', 'copies' => 1];
$formats = LabelFormat::standard();

check('cinque formati standard', fn () => array_keys($formats) === ['a4-24', 'a4-40', 'a4-65', 'roll-50x30', 'roll-40x25']);

foreach (['a4-24' => 24, 'a4-40' => 40, 'a4-65' => 65, 'roll-50x30' => 1, 'roll-40x25' => 1] as $key => $perPage) {
    $format = $formats[$key];
    check("{$key}: {$perPage} per pagina", fn () => $format->perPage() === $perPage);
    check("{$key}: la griglia sta nel foglio", fn () => $format->marginLeft + $format->columns * $format->labelWidth <= $format->pageWidth + 0.001
        && $format->marginTop + $format->rows * $format->labelHeight <= $format->pageHeight + 0.001);

    $labels = LabelSheet::labels([['copies' => $perPage + 1] + $item]);
    $plan = LabelSheet::plan($format, $labels);
    check("{$key}: l'etichetta in più va a pagina 2", fn () => count($plan) === $perPage + 1
        && $plan[$perPage - 1]['page'] === 1 && $plan[$perPage]['page'] === 2);

    $last = $plan[$perPage - 1];
    check("{$key}: l'ultima casella tocca il bordo della griglia", fn () => abs($last['x'] + $format->labelWidth - ($format->marginLeft + $format->columns * $format->labelWidth)) < 0.01
        && abs($last['y'] + $format->labelHeight - ($format->marginTop + $format->rows * $format->labelHeight)) < 0.01);

    $box = LabelSheet::layout($format, $labels[0]);
    check("{$key}: barre dentro l'etichetta e alte almeno 5 mm", fn () => $box['bars'] !== null
        && $box['bars']['x'] >= 0 && $box['bars']['x'] + 95 * $box['bars']['module'] <= $format->labelWidth
        && $box['bars']['height'] >= 5.0
        && $box['bars']['y'] + $box['bars']['height'] <= $box['digits']['y'] + 0.001
        && $box['digits']['y'] + $box['line'] <= $format->labelHeight);
    check("{$key}: il valore in evidenza resta", fn () => $box['highlight'] !== '');
    check("{$key}: il PDF esce", fn () => str_starts_with(LabelSheet::render($format, $labels), '%PDF'));
}

check('A4 24: le prime due caselle una accanto all\'altra', function () use ($formats, $item) {
    $plan = LabelSheet::plan($formats['a4-24'], LabelSheet::labels([['copies' => 2] + $item]));

    return $plan[0]['x'] === 0.0 && $plan[1]['x'] === 70.0 && $plan[0]['y'] === $plan[1]['y'] && abs($plan[0]['y'] - 0.5) < 0.001;
});
check('start salta le caselle già usate', function () use ($formats, $item) {
    $plan = LabelSheet::plan($formats['a4-24'], LabelSheet::labels([$item]), 4);

    return $plan[0]['x'] === 70.0 && abs($plan[0]['y'] - 37.5) < 0.001;
});
check('sul rotolo start non conta', function () use ($formats, $item) {
    $plan = LabelSheet::plan($formats['roll-50x30'], LabelSheet::labels([['copies' => 2] + $item]), 5);

    return $plan[0]['page'] === 1 && $plan[1]['page'] === 2 && $plan[0]['x'] === 0.0;
});
check('rotolo 50 × 30 in orizzontale', function () use ($formats, $item) {
    $pdf = LabelSheet::render($formats['roll-50x30'], LabelSheet::labels([$item]));

    return preg_match('#/MediaBox \[0 0 ([0-9.]+) ([0-9.]+)\]#', $pdf, $m) === 1 && (float) $m[1] > (float) $m[2];
});
check('PDF vuoto con una pagina', fn () => str_starts_with(LabelSheet::render($formats['a4-24'], []), '%PDF'));
check('sul 40 × 25 le righe in più si lasciano', function () use ($formats, $item) {
    $box = LabelSheet::layout($formats['roll-40x25'], LabelSheet::labels([$item])[0]);

    return count($box['lines']) < 3 && $box['bars']['height'] >= 5.0;
});
check('formato su misura', function () use ($item) {
    $format = LabelFormat::a4('a4-21', 'A4, 21 etichette', 3, 7, 70.0, 42.3);

    return $format->perPage() === 21 && str_starts_with(LabelSheet::render($format, LabelSheet::labels([$item])), '%PDF');
});

check('EAN-13 valido: 95 moduli', function () use ($item) {
    $label = LabelSheet::labels([$item])[0];

    return $label['ean'] === '4006381333931' && strlen((string) $label['modules']) === 95 && !$label['invalid'];
});
check('EAN-8 valido: 67 moduli', function () use ($item) {
    $label = LabelSheet::labels([['ean' => '96385074'] + $item])[0];

    return strlen((string) $label['modules']) === 67 && !$label['invalid'];
});
check('EAN sbagliato: niente barre, avviso', function () use ($formats, $item) {
    $label = LabelSheet::labels([['ean' => '4006381333932'] + $item])[0];
    $box = LabelSheet::layout($formats['a4-24'], $label);

    return $label['invalid'] && $label['modules'] === null && $box['bars'] === null
        && str_contains($box['digits']['text'], 'non valido');
});
check('senza EAN niente codice', function () use ($formats, $item) {
    $label = LabelSheet::labels([['ean' => ''] + $item])[0];
    $box = LabelSheet::layout($formats['a4-24'], $label);

    return $box['bars'] === null && $box['digits'] === null && !$label['invalid'];
});
check('righe vuote via', fn () => LabelSheet::labels([['lines' => ['Maglia', ' ', '']] + $item])[0]['lines'] === ['Maglia']);
check('copie: una etichetta ciascuna', fn () => count(LabelSheet::labels([['copies' => 3] + $item, ['copies' => 0] + $item])) === 3);

summary();
