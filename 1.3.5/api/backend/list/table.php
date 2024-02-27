<?php

    $BACKEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";
    
    use Wonder\Table\SSP;
    use Wonder\Table\Field;

    # Importo tutte le variabili che mi servono

        $NAME = (object) array();
        $NAME->folder = $_POST['folder'];
        $NAME->table = $_POST['table'];

        $nameTable = strtoupper($NAME->table);
        $NAME->field = $TABLE->$nameTable;

        $TEXT = (object) array();
        $TEXT->titleS = $_POST['text']['titleS'];
        $TEXT->titleP = $_POST['text']['titleP'];
        $TEXT->last = $_POST['text']['last'];
        $TEXT->all = $_POST['text']['all'];
        $TEXT->article = $_POST['text']['article'];
        $TEXT->full = $_POST['text']['full'];
        $TEXT->empty = $_POST['text']['empty'];
        $TEXT->this = $_POST['text']['this'];

        $USER = (object) array();
        $USER->area = $_POST['user']['area'];
        $USER->authority = is_array($_POST['user']['authority']) ? $_POST['user']['authority'] : $_POST['user']['authority'];

        $CUSTOM = (object) array();
        $CUSTOM->arrow = ($_POST['arrow'] == 'false') ? false : true;
        $CUSTOM->field = json_decode(base64_decode($_POST['custom']['field']), true);
        $CUSTOM->action = $_POST['custom']['action'];
        $CUSTOM->query = base64_decode($_POST['custom']['query_filter']);
        $CUSTOM->query_all = base64_decode($_POST['custom']['query_all']);
        $CUSTOM->search_field = isset($_POST['custom']['search_field']) ? $_POST['custom']['search_field'] : [];
        
        $CUSTOM->order_column = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : $_POST['custom']['order_column'];
        $CUSTOM->order_direction = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : $_POST['custom']['order_direction'];

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

        $PARAMS['wi-page'] = $PAGE_NUMBER;
        $PARAMS['wi-length'] = $length;
        $PARAMS['wi-search'] = urlencode($_POST['search']['value']);

        if (isset($_POST['order'])) {
            $PARAMS['wi-order'] = $_POST['order'][0]['name'];
            $PARAMS['wi-order-dir'] = $_POST['order'][0]['dir'];
        }

        $URL_PARTS['query'] = http_build_query($PARAMS);

        $PAGE->redirect = $PATH->site . $URL_PARTS['path'].'?'.$URL_PARTS['query'];
        $PAGE->redirectBase64 = base64_encode($PAGE->redirect);

    #

    # Conto le linee 

        if (empty($CUSTOM->query) && empty($CUSTOM->query_all)) {
            $query = "";
        } else if (empty($CUSTOM->query) && !empty($CUSTOM->query_all)) {
            $query = $CUSTOM->query_all;
        } else if (!empty($CUSTOM->query) && empty($CUSTOM->query_all)) {
            $query = $CUSTOM->query;
        } else if (!empty($CUSTOM->query) && !empty($CUSTOM->query_all)) {
            $query = $CUSTOM->query.' AND '.$CUSTOM->query_all;
        }

        $CUSTOM->query_ln = sqlCount($NAME->table, $query, 'id', true);
        $CUSTOM->query_all_ln = sqlCount($NAME->table, $CUSTOM->query_all, 'id', true);

    #

    $TABLE_FIELD = new Field($NAME, $PATH, $TEXT, $USER, $PAGE);

    $columnN = 0;
    $COLUMNS = [];

    # Frecce posizione
        if ($CUSTOM->arrow && $CUSTOM->query_ln > 1) {
            
            array_push($COLUMNS, [
                'db' => 'id', 
                'dt' => 0,
                'format' => [
                    'visible' => $CUSTOM->arrow,
                    'lines' => $CUSTOM->query_ln
                ],
                'formatter' => function($row, $column, $format) {

                    global $TABLE_FIELD;

                    return $TABLE_FIELD->newField($row, 'position_arrow_up', $format);

                }
            ]);

            array_push($COLUMNS, [
                'db' => 'id', 
                'dt' => 1,
                'format' => [
                    'visible' => $CUSTOM->arrow,
                    'lines' => $CUSTOM->query_ln
                ],
                'formatter' => function($row, $column, $format) {

                    global $TABLE_FIELD;

                    return $TABLE_FIELD->newField($row, 'position_arrow_down', $format);

                }
            ]);

            $columnN = 2;
            
        }

    # Campi personalizzati
        foreach ($CUSTOM->field as $column => $format) {

            $field = [
                'db' => $column, 
                'dt' => $columnN,
                'format' => $format,
                'formatter' => function($row, $column, $format) {

                    global $TABLE_FIELD;
        
                    return $TABLE_FIELD->newField($row, $column, $format);

                }
            ];

            array_push($COLUMNS, $field);

            $columnN++;

        }

    # Menu
    array_push($COLUMNS, [
        'db' => 'id', 
        'dt' => $columnN,
        'format' => $CUSTOM->action,
        'formatter' => function($row, $column, $format) {

            global $TABLE_FIELD;

            return $TABLE_FIELD->newField($row, 'action_button', $format);

        }
    ]);
    
    // SQL server connection information
    $sql_details = array(
        'user' => $DB->username,
        'pass' => $DB->password,
        'db'   => $DB->database['main'],
        'host' => $DB->hostname
        // ,'charset' => 'utf8' // Depending on your PHP and MySQL config, you may need this
    );

    echo json_encode(
        SSP::complex(
            $_POST, 
            $sql_details, 
            $NAME->table, 
            'id',
            $COLUMNS, 
            $CUSTOM->search_field, 
            $CUSTOM->query, 
            $CUSTOM->query_all, 
            $CUSTOM->order_column, 
            $CUSTOM->order_direction
        )
    );

?>