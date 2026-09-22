<?php

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

$name->field = ListProvider::fields((string) $name->table, (string) $name->schema);

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
$PAGE->redirect = ListProvider::redirect(
    (string) ($_POST['url'] ?? ''),
    (string) $PAGE->domain,
    (string) $name->table,
    [
        'page'            => $pageNumber,
        'length'          => $length,
        'search'          => (string) ($_POST['search']['value'] ?? ''),
        'order'           => $_POST['order'][0]['name'] ?? '',
        'order_direction' => $_POST['order'][0]['dir'] ?? '',
    ]
);
$PAGE->redirectBase64 = base64_encode($PAGE->redirect);

$name->page = $pageNumber;
$name->length = $length;

echo json_encode(
    ListProvider::fetch($_POST, $name, $text, $user, $PAGE, $PATH),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
