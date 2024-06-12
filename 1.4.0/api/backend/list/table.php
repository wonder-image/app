<?php

    $BACKEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";
    
    use Wonder\Backend\Table\SSP;
    use Wonder\Backend\Table\Field;

    # Importo tutte le variabili che mi servono

        $NAME = (object) array();
        $NAME->id = $_POST['id'];
        $NAME->table = $_POST['config']['table'];
        $NAME->database = $_POST['config']['database'];
        $NAME->connection = $MYSQLI_CONNECTION[$NAME->database];

        $NAME->link = $_POST['default']['link'];

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
        $USER->area = isset($_POST['custom']['user_area']) ? $_POST['custom']['user_area'] : "";
        $USER->authority = isset($_POST['custom']['user_authority']) ? $_POST['custom']['user_authority'] : "";

        $CUSTOM = (object) array();
        $CUSTOM->field = $_POST['fields'];
        $CUSTOM->arrow = ($_POST['default']['order'] == 'position') ? true : false;
        $CUSTOM->action = isset($_POST['custom']['action']) ? $_POST['custom']['action'] : [];
        $CUSTOM->query = base64_decode($_POST['config']['query']);
        $CUSTOM->query_filter = base64_decode($_POST['config']['query_filter']);
        $CUSTOM->query_all = base64_decode($_POST['config']['query_custom']);
        $CUSTOM->search_field = isset($_POST['config']['search_column']) ? json_decode(base64_decode($_POST['config']['search_column']), true) : [];
        
        $CUSTOM->order_column = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : $_POST['default']['order'];
        $CUSTOM->order_direction = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : $_POST['default']['order_direction'];

    #

    # Se si sta effettuando una ricerca
        if (isset($_POST['search']) && $_POST['search']['value'] != '') { $CUSTOM->arrow = false; }   
    #

    # Se i filtri sono attivi
        if (!empty($CUSTOM->query_filter)) { $CUSTOM->arrow = false; }
    #

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
            $PARAMS['wi-order'] = $_POST['order'][0]['name'];
            $PARAMS['wi-order-dir'] = $_POST['order'][0]['dir'];
        }

        $URL_PARTS['query'] = http_build_query($PARAMS);

        $PAGE->redirect = $PATH->site . $URL_PARTS['path'].'?'.$URL_PARTS['query'];
        $PAGE->redirectBase64 = base64_encode($PAGE->redirect);

        $NAME->page = $PAGE_NUMBER;
        $NAME->length = $length;

    #

    # Conto le linee 
        $CUSTOM->query_ln = sqlCount($NAME->table, $CUSTOM->query, 'id', true);
        $CUSTOM->query_all_ln = sqlCount($NAME->table, $CUSTOM->query_all, 'id', true);

    #

    # Campi tabella
        $TABLE_FIELD = new Field($NAME, $PATH, $TEXT, $USER, $PAGE);

        $columnN = 0;
        $COLUMNS = [];

        foreach ($CUSTOM->field as $column => $format) {

            $columnName = $format['name'];
            $other = isset($format['other']) ? $format['other'] : [];

            if ($columnName == 'position-up') {
                
                $columnName = 'id';
                $format = [
                    'visible' => $CUSTOM->arrow,
                    'lines' => $CUSTOM->query_ln
                ];

                $formatter = function ($row, $column, $format) {

                    global $TABLE_FIELD;
        
                    return $TABLE_FIELD->newField($row, 'position_arrow_up', $format);

                };

            } else if ($columnName == 'position-down') {
                
                $columnName = 'id';
                $format = [
                    'visible' => $CUSTOM->arrow,
                    'lines' => $CUSTOM->query_ln
                ];

                $formatter = function ($row, $column, $format) {

                    global $TABLE_FIELD;
        
                    return $TABLE_FIELD->newField($row, 'position_arrow_down', $format);

                };

            } else if ($columnName == 'menu') {

                $columnName = 'id';
                $format = $other;

                $formatter = function ($row, $column, $format) {

                    global $TABLE_FIELD;
        
                    return $TABLE_FIELD->newField($row, 'action_button', $format);

                };

            } else {  

                $format = $other;

                $formatter = function ($row, $column, $format) {

                    global $TABLE_FIELD;
        
                    return $TABLE_FIELD->newField($row, $column, $format);

                };

            }


            $field = [
                'db' => $columnName, 
                'dt' => $columnN,
                'format' => $format,
                'formatter' => $formatter
            ];  

            array_push($COLUMNS, $field);

            $columnN++;

        }

    #
    
    # Connessione SQL
        $sql_details = array(
            'user' => $DB->username,
            'pass' => $DB->password,
            'db'   => $NAME->database,
            'host' => $DB->hostname
        );

        echo json_encode(
            SSP::complex(
                $_POST, 
                $sql_details, 
                $NAME->table, 
                'id',
                $COLUMNS, 
                $CUSTOM->search_field, 
                $CUSTOM->query_filter, 
                $CUSTOM->query_all, 
                $CUSTOM->order_column, 
                $CUSTOM->order_direction
            )
        );

    #