<?php
/** php tests/Themes/DatePickerScriptEscapeTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Form\Components\DatePicker;
use Wonder\Elements\Form\Components\DateRange;
use Wonder\Elements\Form\Components\DateTimeRange;
use Wonder\Elements\Form\Components\SelectDate;
use Wonder\Elements\Form\Components\TextList;

defined('APP_URL') || define('APP_URL', 'https://example.test');
defined('APP_VERSION') || define('APP_VERSION', 'dev');

$payload = '</script><script>alert(1)</script>';

$fields = [
    'DatePicker' => (new DatePicker('date'))->value($payload)->min($payload)->max($payload),
    'DateRange' => (new DateRange('range'))->value([$payload, $payload])->min($payload)->max($payload),
    'DateTimeRange' => (new DateTimeRange('datetime'))->value([$payload, $payload])->min($payload)->max($payload),
    'SelectDate' => (new SelectDate('select-date'))->label($payload)->value($payload)->min($payload)->max($payload),
];

echo "\nDate picker Wonder senza script inline\n";

check('i renderer demandano l\'inizializzazione alla lib', function () use ($fields): bool {
    foreach ($fields as $name => $field) {
        $html = $field->render('wonder');

        if (str_contains($html, '<script')) {
            throw new RuntimeException("{$name} contiene ancora uno script inline");
        }
    }

    return true;
});

check('i valori ostili restano confinati negli attributi HTML escapati', function () use ($fields, $payload): bool {
    foreach ($fields as $name => $field) {
        $html = $field->render('wonder');

        if (str_contains($html, $payload) || !str_contains($html, '&lt;/script&gt;')) {
            throw new RuntimeException("{$name} non confina il payload nell'HTML escapato");
        }
    }

    return true;
});

check('ogni renderer espone alla lib il proprio tipo di date picker', function () use ($fields): bool {
    $markers = [
        'DatePicker' => 'data-wi-date-picker="true"',
        'DateRange' => 'data-wi-date-range="true"',
        'DateTimeRange' => 'data-wi-date-time-range="true"',
        'SelectDate' => 'data-wi-select-date="true"',
    ];

    foreach ($fields as $name => $field) {
        if (!str_contains($field->render('wonder'), $markers[$name])) {
            throw new RuntimeException("{$name} non espone {$markers[$name]}");
        }
    }

    return true;
});

check('TextList escapa data-wi-name', function (): bool {
    $html = (new TextList('name" onfocus="alert(1)'))
        ->options(['1' => 'Uno'])
        ->render('wonder');

    return str_contains($html, 'data-wi-name="name&quot; onfocus=&quot;alert(1)-text"')
        && !str_contains($html, 'data-wi-name="name" onfocus="alert(1)-text"');
});

summary();
