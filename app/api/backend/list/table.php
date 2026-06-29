<?php

    header('Access-Control-Allow-Origin: *');

    $BACKEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";
    
    use Wonder\App\Table as AppTable;
    use Wonder\Backend\Table\ListProvider;

    # Importo tutte le variabili che mi servono

        $NAME = (object) [];
        $NAME->id = $_POST['id'];
        $NAME->table = $_POST['config']['table'];
        $NAME->database = $_POST['config']['database'];
        $NAME->connection = $MYSQLI_CONNECTION[$NAME->database];

        $NAME->link = $_POST['default']['link'] ?? [];
        $NAME->schema = $_POST['custom']['schema'] ?? '';

        $nameTable = strtoupper($NAME->table);
        $legacyField = isset($TABLE->$nameTable)
            ? $TABLE->$nameTable
            : [];
        $tableField = AppTable::$list[strtolower($NAME->table)] ?? [];
        $resourceField = is_string($NAME->schema) && trim($NAME->schema) !== ''
            ? (AppTable::$list[strtolower(trim($NAME->schema))] ?? [])
            : [];

        $NAME->field = array_replace_recursive(
            is_array($legacyField) ? $legacyField : [],
            is_array($tableField) ? $tableField : [],
            is_array($resourceField) ? $resourceField : []
        );

        $TEXT = (object) [];
        $TEXT->titleS = $_POST['text']['titleS'];
        $TEXT->titleP = $_POST['text']['titleP'];
        $TEXT->last = $_POST['text']['last'];
        $TEXT->all = $_POST['text']['all'];
        $TEXT->article = $_POST['text']['article'];
        $TEXT->full = $_POST['text']['full'];
        $TEXT->empty = $_POST['text']['empty'];
        $TEXT->this = $_POST['text']['this'];

        $USER = (object) [];
        $USER->area = $_POST['custom']['user_area'] ?? "";
        $USER->authority = $_POST['custom']['user_authority'] ?? "";

    # Cambio la connessione al database se necessario
        $mysqli = $NAME->connection;

    #

    # Calcolo il numero della pagina e setto il redirect

        $start = $_POST['start'];
        $length = $_POST['length'];

        $PAGE_NUMBER = ($start == 0) ? 0 : $start / $length;

        $URL = $_POST['url'];

        $URL_PARTS = parse_url($URL);
        
        if (isset($URL_PARTS['query'])) {
            parse_str($URL_PARTS['query'], $PARAMS);
        } else {
            $PARAMS = [];
        }

        $PARAMS[$NAME->table.'__page'] = $PAGE_NUMBER;
        $PARAMS[$NAME->table.'__length'] = $length;
        $PARAMS[$NAME->table.'__search'] = urlencode($_POST['search']['value']);

        if (isset($_POST['order'])) {
            $PARAMS[$NAME->table.'__order'] = $_POST['order'][0]['name'];
            $PARAMS[$NAME->table.'__order_dir'] = $_POST['order'][0]['dir'];
        }

        $URL_PARTS['query'] = http_build_query($PARAMS);

        $PAGE->redirect = 'https://www.'.$PAGE->domain . $URL_PARTS['path'].'?'.$URL_PARTS['query'];
        $PAGE->redirectBase64 = base64_encode($PAGE->redirect);

        $NAME->page = $PAGE_NUMBER;
        $NAME->length = $length;

    #

    # Risultato server-side (colonne + SSP) delegato a ListProvider
        echo json_encode(
            ListProvider::fetch($_POST, $NAME, $TEXT, $USER, $PAGE, $PATH)
        );

    #
