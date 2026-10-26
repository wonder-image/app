<?php

use Wonder\App\Credentials;
use Wonder\App\Support\ApiRequest;
use Wonder\Backend\Table\ListProvider;
use Wonder\Backend\Table\ListTableRequest;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

// Solo tabelle registrate in Table (anche lazy), prima di qualsiasi query.
$table = ListTableRequest::table($_POST['config']['table'] ?? null);

if ($table === null) {
    ApiRequest::error('Tabella non consentita.', 422);
}

$name = (object) [];
$name->id = $_POST['id'] ?? '';
$name->table = $table;
// Database e connessione fissi: `config.database` del client è ignorato.
$name->database = (string) (Credentials::database()->database['main'] ?? '');
$name->connection = $MYSQLI_CONNECTION['main'];
$name->link = $_POST['default']['link'] ?? [];
$name->schema = ListTableRequest::schema($_POST['custom']['schema'] ?? null);

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

// Filtro utenti non più dal POST: vuoto come nel prerender di Table.
$user = (object) [];
$user->area = '';
$user->authority = '';

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
