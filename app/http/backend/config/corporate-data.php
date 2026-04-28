<?php

use Wonder\App\PageSchema\CorporateDataPageSchema;
use Wonder\App\Models\Config\Society;
use Wonder\App\Models\Config\SocietyAddress;
use Wonder\App\Models\Config\SocietyLegalAddress;
use Wonder\App\Models\Config\SocietySocial;
use Wonder\App\Models\Config\SocietyTimetable;
use Wonder\App\Support\Repeater;
use Wonder\App\ResourceSchema\RepeaterRelation;
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
$timetableRepeaterField = CorporateDataPageSchema::timetableRepeaterField();
$timetableRelation = RepeaterRelation::make('society_timetable', 'society_address_id')
    ->model(SocietyTimetable::class)
    ->positionKey('position');
$hasTimetableTable = sqlTableExists('society_timetable');

$timeTables = [];

if ($hasTimetableTable) {
    $timeTables = Repeater::loadRelatedRows($timetableRelation, 1);
}

if ($timeTables === []) {
    $time = empty($values['timetable']) ? [] : json_decode((string) $values['timetable'], true);
    $position = 1;

    foreach ((array) $time as $day => $items) {
        foreach ((array) $items as $item) {
            $timeTables[] = [
                'position' => $position,
                'day' => $day,
                'from_time' => $item['from'] ?? '',
                'to_time' => $item['to'] ?? '',
            ];

            $position++;
        }
    }
}

if (isset($_POST['modify'])) {
    $timeTables = [];
    $jsonTimeTables = [];
    $repeaterRows = Repeater::rowsFromRequest('timetables', $_POST, $_FILES);

    foreach ((array) $repeaterRows as $key => $row) {
        $day = (string) ($row['day'] ?? '');
        $from = (string) ($row['from_time'] ?? '');
        $to = (string) ($row['to_time'] ?? '');

        if ($from === '' || $to === '') {
            continue;
        }

        $timeRow = [
            'position' => $key + 1,
            'day' => $day,
            'from_time' => $from,
            'to_time' => $to,
        ];

        if (!empty($row['id'])) {
            $timeRow['id'] = (int) $row['id'];
        }

        $timeTables[] = $timeRow;
    }

    foreach ($timeTables as $item) {
        $day = (string) ($item['day'] ?? '');

        if ($day === '') {
            continue;
        }

        if (!array_key_exists($day, $jsonTimeTables)) {
            $jsonTimeTables[$day] = [];
        }

        $jsonTimeTables[$day][] = [
            'from' => (string) ($item['from_time'] ?? ''),
            'to' => (string) ($item['to_time'] ?? ''),
        ];
    }

    $post = $_POST;
    $post['timetable'] = json_encode($jsonTimeTables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    unset(
        $post['id'],
        $post['timetables']
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

    if (empty($ALERT) && $hasTimetableTable) {
        Repeater::syncRelatedRows(
            $timetableRelation,
            1,
            $timeTables
        );
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
    'TIMETABLE_REPEATER_FIELD' => $timetableRepeaterField,
])->render();
