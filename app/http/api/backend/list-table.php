<?php

use Wonder\App\Table as AppTable;
use Wonder\App\Support\ApiRequest;
use Wonder\Backend\Table\ListProvider;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$name = (object) [];
$name->id = $_POST['id'] ?? '';
$name->table = $_POST['config']['table'] ?? '';
$name->database = $_POST['config']['database'] ?? 'main';
$name->connection = $MYSQLI_CONNECTION[$name->database];
$name->link = $_POST['default']['link'] ?? [];
$name->schema = $_POST['custom']['schema'] ?? '';

$nameTable = strtoupper((string) $name->table);
$legacyField = isset($TABLE->$nameTable) ? $TABLE->$nameTable : [];
$tableField = AppTable::$list[strtolower((string) $name->table)] ?? [];
$resourceField = is_string($name->schema) && trim($name->schema) !== ''
    ? (AppTable::$list[strtolower(trim($name->schema))] ?? [])
    : [];

$name->field = array_replace_recursive(
    is_array($legacyField) ? $legacyField : [],
    is_array($tableField) ? $tableField : [],
    is_array($resourceField) ? $resourceField : []
);

$text = (object) [];
$text->titleS = $_POST['text']['titleS'] ?? '';
$text->titleP = $_POST['text']['titleP'] ?? '';
$text->last = $_POST['text']['last'] ?? '';
$text->all = $_POST['text']['all'] ?? '';
$text->article = $_POST['text']['article'] ?? '';
$text->full = $_POST['text']['full'] ?? '';
$text->empty = $_POST['text']['empty'] ?? '';
$text->this = $_POST['text']['this'] ?? '';

$user = (object) [];
$user->area = $_POST['custom']['user_area'] ?? '';
$user->authority = $_POST['custom']['user_authority'] ?? '';

$mysqli = $name->connection;

$start = (int) ($_POST['start'] ?? 0);
$length = max((int) ($_POST['length'] ?? 10), 1);
$pageNumber = $start === 0 ? 0 : (int) ($start / $length);
$url = (string) ($_POST['url'] ?? '');
$urlParts = parse_url($url);

if (isset($urlParts['query'])) {
    parse_str($urlParts['query'], $params);
} else {
    $params = [];
}

$params[$name->table.'__page'] = $pageNumber;
$params[$name->table.'__length'] = $length;
$params[$name->table.'__search'] = urlencode((string) ($_POST['search']['value'] ?? ''));

if (isset($_POST['order'][0])) {
    $params[$name->table.'__order'] = $_POST['order'][0]['name'] ?? '';
    $params[$name->table.'__order_dir'] = $_POST['order'][0]['dir'] ?? '';
}

$urlParts['query'] = http_build_query($params);
$PAGE->redirect = 'https://www.'.$PAGE->domain.($urlParts['path'] ?? '').'?'.$urlParts['query'];
$PAGE->redirectBase64 = base64_encode($PAGE->redirect);

$name->page = $pageNumber;
$name->length = $length;

echo json_encode(
    ListProvider::fetch($_POST, $name, $text, $user, $PAGE, $PATH),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
