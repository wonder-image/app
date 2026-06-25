<?php

use Wonder\App\PageSchema\SqlDownloadPageSchema;

$title = 'Scarica tabelle';
$tableOptions = [];

foreach ((array) ($TABLE ?? []) as $key => $value) {
    if (!is_string($key) || trim($key) === '') {
        continue;
    }

    $tableKey = strtolower(trim($key));
    $tableOptions[$tableKey] = trim($key);
}

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/config/sql-download.php', [
    'TITLE' => $title,
    'BACK_URL' => '',
    'FORM_SCHEMA' => SqlDownloadPageSchema::formSchema($tableOptions),
])->render();
