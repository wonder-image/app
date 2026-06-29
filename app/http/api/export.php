<?php

use Wonder\App\Support\ApiRequest;

$table = ApiRequest::string('table');
$format = ApiRequest::string('format');

if ($table === '' || $format === '') {
    ApiRequest::error('Parametri non validi.', 422);
}

sqlExport($table, $format);
exit;
