<?php

use Wonder\App\PageSchema\CorporateDataPageSchema;
use Wonder\App\Models\Config\Society;
use Wonder\App\Models\Config\SocietyAddress;
use Wonder\App\Models\Config\SocietyLegalAddress;
use Wonder\App\Models\Config\SocietySocial;
use Wonder\App\Table;

$title = 'Dati aziendali';
$tables = [
    'society' => Society::class,
    'society_address' => SocietyAddress::class,
    'society_legal_address' => SocietyLegalAddress::class,
    'society_social' => SocietySocial::class,
];

$values = [];

foreach ($tables as $table => $modelClass) {
    $row = $modelClass::findById(1);

    if (is_array($row)) {
        $values = array_merge($values, $row);
    }
}

$companyFormSchema = CorporateDataPageSchema::companyFormSchema();
$legalFormSchema = CorporateDataPageSchema::legalFormSchema();
$legalAddressFormSchema = CorporateDataPageSchema::legalAddressFormSchema((string) ($values['legal_country'] ?? 'IT'));
$addressFormSchema = CorporateDataPageSchema::addressFormSchema((string) ($values['country'] ?? 'IT'));
$socialFormSchema = CorporateDataPageSchema::socialFormSchema();

$timeTables = [];
$time = empty($values['timetable']) ? [] : json_decode((string) $values['timetable'], true);
$position = 1;

foreach ((array) $time as $day => $items) {
    foreach ((array) $items as $item) {
        $timeTables[] = [
            'position' => $position,
            'time-day' => $day,
            'time-from' => $item['from'] ?? '',
            'time-to' => $item['to'] ?? '',
        ];

        $position++;
    }
}

if (isset($_POST['modify'])) {
    $timeTables = [];
    $jsonTimeTables = [];

    foreach ((array) ($_POST['time-day'] ?? []) as $key => $day) {
        $from = (string) ($_POST['time-from'][$key] ?? '');
        $to = (string) ($_POST['time-to'][$key] ?? '');

        if ($from === '' || $to === '') {
            continue;
        }

        $currentPosition = (int) ($_POST['position'][$key] ?? ($key + 1));

        $timeTables[$currentPosition] = [
            'position' => $currentPosition,
            'time-day' => (string) $day,
            'time-from' => $from,
            'time-to' => $to,
        ];
    }

    ksort($timeTables);

    foreach ($timeTables as $item) {
        $day = (string) ($item['time-day'] ?? '');

        if ($day === '') {
            continue;
        }

        if (!array_key_exists($day, $jsonTimeTables)) {
            $jsonTimeTables[$day] = [];
        }

        $jsonTimeTables[$day][] = [
            'from' => (string) ($item['time-from'] ?? ''),
            'to' => (string) ($item['time-to'] ?? ''),
        ];
    }

    $post = $_POST;
    $post['timetable'] = json_encode($jsonTimeTables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    unset(
        $post['id'],
        $post['position'],
        $post['time-day'],
        $post['time-from'],
        $post['time-to']
    );

    foreach ($tables as $table => $modelClass) {
        $prepared = Table::key($table)->prepare($post);
        $exists = $modelClass::findById(1);

        if (is_array($exists) && $exists !== []) {
            sqlModify($table, $prepared, 'id', 1);
        } else {
            sqlInsert($table, array_merge(['id' => 1], $prepared));
        }

        if (!empty($ALERT)) {
            break;
        }
    }

    if (empty($ALERT)) {
        header('Location: '.__r('backend.config.corporate-data').'?alert=651');
        exit();
    }

    $values = array_merge($values, $post);
}

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/config/corporate-data.php', [
    'TITLE' => $title,
    'BACK_URL' => '',
    'VALUES' => $values,
    'TIMETABLES' => $timeTables,
    'COMPANY_FORM_SCHEMA' => $companyFormSchema,
    'LEGAL_FORM_SCHEMA' => $legalFormSchema,
    'LEGAL_ADDRESS_FORM_SCHEMA' => $legalAddressFormSchema,
    'ADDRESS_FORM_SCHEMA' => $addressFormSchema,
    'SOCIAL_FORM_SCHEMA' => $socialFormSchema,
    'TIMETABLE_FORM_SCHEMA' => CorporateDataPageSchema::timetableFormSchema(),
])->render();
