<?php

    function filter() {

        global $TEXT;
        global $NAME;

        $TITLE = "Lista $TEXT->titleP";

        $filter = filterCustom();

        $QUERY_ALL = $filter->query_all;
        $QUERY_FILTER = $filter->query_filter;
        $QUERY_ORDER = $filter->query_order;
        $QUERY_ORDER_COL = $filter->query_order_col;
        $QUERY_ORDER_DIR = $filter->query_order_dir;

        $QUERY = empty($QUERY_FILTER) ? $QUERY_ALL.$QUERY_ORDER : $QUERY_ALL.'AND '.$QUERY_FILTER.$QUERY_ORDER;

        $ARROW = $filter->arrow;
        
        $RETURN = (object) array();
        $RETURN->query = $QUERY;

        # Campi utilizzati dalla tabella lista in backend
            $RETURN->query_all = $QUERY_ALL;
            $RETURN->query_filter = $QUERY_FILTER;
            $RETURN->query_order = $QUERY_ORDER;
            $RETURN->query_order_col = $QUERY_ORDER_COL;
            $RETURN->query_order_dir = $QUERY_ORDER_DIR;
        #

        $RETURN->lines = sqlCount($NAME->table, $QUERY_ALL, 'id', true);
        $RETURN->selected_lines = sqlCount($NAME->table, $QUERY, 'id', true);
        $RETURN->arrow = $ARROW;
        $RETURN->title = $TITLE;

        return $RETURN;

    }

    function filterOrder($ARROW) {

        global $NAME;

        global $FILTER_ORDER;
        global $FILTER_DIRECTION;

        $COLUMN = "creation";
        $DIRECTION = "DESC";
        
        if ($ARROW == true && sqlColumnExists($NAME->table, 'position')) {

            $COLUMN = "position";
            $DIRECTION = "ASC";

        } elseif (isset($FILTER_ORDER) && !empty($FILTER_ORDER)) {

            $COLUMN = $FILTER_ORDER;

            if (isset($FILTER_DIRECTION) && !empty($FILTER_DIRECTION)) {
                $DIRECTION = $FILTER_DIRECTION;
            } else {
                $DIRECTION = "ASC";
            }

        }

        $RETURN = (object) array();
        $RETURN->query = "ORDER BY `$COLUMN` $DIRECTION ";
        $RETURN->column = $COLUMN;
        $RETURN->direction = $DIRECTION;

        return $RETURN;

    }

    function filterDate() {

        global $FILTER_COLUMN;

        global $HOW_MANY_DAYS;

        global $TEXT;
        global $NAME;

        global $QUERY_CUSTOM;

        $ARROW = false;

        $QUERY_ALL = empty($QUERY_CUSTOM) ? "`deleted` = 'false' " : $QUERY_CUSTOM." AND `deleted` = 'false' ";

        $DAYS = isset($HOW_MANY_DAYS) ? $HOW_MANY_DAYS : 30;
        $COLUMN = isset($FILTER_COLUMN) ? $FILTER_COLUMN : 'creation';

        $from = isset($_GET['wi-from']) ? $_GET['wi-from'] : '';
        $to = isset($_GET['wi-to']) ? $_GET['wi-to'] : '';
        $YEAR = isset($_GET['wi-year']) ? $_GET['wi-year'] : '';
        $MONTH = isset($_GET['wi-month']) ? $_GET['wi-month'] : '';

        $QUERY_STRING = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : "";

        $URL_QUERY = [];
        parse_str($QUERY_STRING, $URL_QUERY);
        
        $QUERY_INPUT = "";
        $QUERY_URL = "";

        $QUERY_DATE = [ 'wi-from', 'wi-to', 'wi-year', 'wi-month' ];

        foreach ($URL_QUERY as $key => $value) {
            if (!in_array($key, $QUERY_DATE) && $key != 'wi-limit') {

                if (is_array($value)) {

                    $key .= '[]';

                    foreach ($value as $v) { 
                        $QUERY_INPUT .= "<input type='hidden' name='$key' value='$v'>"; 
                        $QUERY_URL .= "&$key=$v";
                    }

                } else {

                    $QUERY_INPUT .= "<input type='hidden' name='$key' value='$value'>";
                    $QUERY_URL .= "&$key=$value";
    
                }
                
            }
        }

        # Array bottoni
            $ARRAY_MONTH = [];
            $ARRAY_YEAR = [];
            $BUTTONS_MONTH = "";
            $OTHER_BUTTONS_MONTH = "";
            $BUTTONS_YEAR = "";
            $OTHER_BUTTONS_YEAR = "";

            $TABLE_INFO = sqlTableInfo($NAME->table);
            $LINES = sqlCount($NAME->table, $QUERY_ALL, 'id', true);

            $im = 1;
            $iy = 1;

            $firstDate = strtotime($TABLE_INFO->create_time);
            $lastDate = strtotime(date('Y-m-d'));

            while ($lastDate >= $firstDate) {

                $month = date("F", $lastDate);
                $year = date("Y", $lastDate);

                $date = "$month $year";
                array_push($ARRAY_MONTH, $date);

                $mese = translateDate("01-$month-$year", 'month');

                if (isset($_GET['wi-month']) && isset($_GET['wi-year']) && $month == $_GET['wi-month'] && $year == $_GET['wi-year']) {
                    $outline = "";
                    $active = "active";
                }else{
                    $outline = "-outline";
                    $active = "";
                }

                if ($im < 5) {
                    $BUTTONS_MONTH .= "<a href='?wi-month=$month&wi-year=$year$QUERY_URL' class='btn btn$outline-dark btn-sm col' tabindex='-1' role='button'> $mese $year </a>";
                } else {
                    $OTHER_BUTTONS_MONTH .= "<a href='?wi-month=$month&wi-year=$year$QUERY_URL' class='dropdown-item $active'>$mese $year</a>";
                }
                
                $im++;

                $lastDate = strtotime("-1 month", $lastDate);

            }

            $first = date('Y', strtotime($TABLE_INFO->create_time));
            $last = date('Y');

            while ($last >= $first) {

                $year = $last;
                array_push($ARRAY_YEAR, $year);

                if (isset($_GET['wi-year']) && $year == $_GET['wi-year'] && empty($_GET['wi-month'])) {
                    $outline = "";
                    $active = "active";
                } else {
                    $outline = "-outline";
                    $active = "";
                }

                if ($iy < 5) {
                    $BUTTONS_YEAR .= "<a href='?wi-year=$year$QUERY_URL' class='btn btn$outline-dark btn-sm col'' tabindex='-1' role='button'> $year </a>";
                } else {
                    $OTHER_BUTTONS_YEAR .= "<a href='?wi-year=$year$QUERY_URL' class='dropdown-item $active'>$year</a>";
                }

                $iy++;

                $last--;

            }

            if (!empty($OTHER_BUTTONS_MONTH)) {
                    
                $BUTTONS_MONTH .= "
                <div class='dropdown col p-0'>
                    <button type='button' class='btn btn-outline-dark btn-sm dropdown-toggle' data-bs-toggle='dropdown' aria-expanded='false'>
                        Altro
                    </button>
                    <div class='dropdown-menu'>
                        $OTHER_BUTTONS_MONTH
                    </div>
                </div>
                ";

            }

            if (!empty($OTHER_BUTTONS_YEAR)) {
                    
                $BUTTONS_YEAR .= "
                <div class='dropdown col p-0' role='group'>
                    <button type='button' class='btn btn-outline-dark btn-sm dropdown-toggle' data-bs-toggle='dropdown' aria-expanded='false'>
                        Altro
                    </button>
                    <div class='dropdown-menu'>
                        $OTHER_BUTTONS_YEAR
                    </div>
                </div>";
                
            }

        # Filtro

            if (empty($MONTH) && !empty($YEAR)) {

                $from = '01/01/'.$YEAR;
                $to = '31/12/'.$YEAR;

                $TITLE = ucwords($TEXT->titleP)." del ".$YEAR;

            }

            if (!empty($MONTH) && !empty($YEAR)) {

                $date = '1 '.$MONTH.' '.$YEAR;
                $from = date('01/m/Y', strtotime($date));
                $to = date('t/m/Y', strtotime($date));

                $mese = translateDate($MONTH, 'month');
                $TITLE = ucwords($TEXT->titleP)." di $mese ".$YEAR;

            }

            if (empty($from) && empty($to)) {

                $from = date('d/m/Y', strtotime("-$DAYS days"));
                $to = date('d/m/Y');

                $DAYS++;

                if ($DAYS == 1) {
                    $TITLE = ucwords($TEXT->titleP)." di oggi";
                } else {
                    $TITLE = ucwords($TEXT->titleP)." ultimi $DAYS giorni";
                }

            }

            $TITLE = empty($TITLE) ? ucwords($TEXT->titleP)." dal $from al $to" : $TITLE;

            list($day,$month,$year) = explode("/", $from);
            $SQL_from = "$year-$month-$day";
            list($day,$month,$year) = explode("/", $to);
            $SQL_to = "$year-$month-$day";

            $QUERY_ALL .= "AND `$COLUMN` BETWEEN '$SQL_from 00:00:00' AND '$SQL_to 23:59:59' ";

            $filter = filterCustom();

            $QUERY_FILTER = $filter->query_filter;
            $QUERY_ORDER = $filter->query_order;
            $QUERY_ORDER_COL = $filter->query_order_col;
            $QUERY_ORDER_DIR = $filter->query_order_dir;

            $QUERY = empty($QUERY_FILTER) ? $QUERY_ALL.$QUERY_ORDER : $QUERY_ALL.'AND '.$QUERY_FILTER.$QUERY_ORDER;
    
        #

        $RETURN = (object) array();
        $RETURN->selected_lines = sqlSelect($NAME->table, $QUERY)->Nrow;
        $RETURN->lines = $LINES;
        $RETURN->from = $from;
        $RETURN->to = $to;
        $RETURN->title = $TITLE;
        $RETURN->query = $QUERY;

        # Campi utilizzati dalla tabella lista in backend
            $RETURN->query_all = $QUERY_ALL;
            $RETURN->query_filter = $QUERY_FILTER;
            $RETURN->query_order = $QUERY_ORDER;
            $RETURN->query_order_col = $QUERY_ORDER_COL;
            $RETURN->query_order_dir = $QUERY_ORDER_DIR;
        #
        
        $RETURN->arrow = $ARROW;

        $RETURN->array = (object) array();
        $RETURN->array->month = $ARRAY_MONTH;
        $RETURN->array->year = $ARRAY_YEAR;

        $RETURN->html = "
        <div class='col-5'>
            <form method='get'>
                $QUERY_INPUT
                <div class='input-group input-group-sm input-daterange wi-daterange-filter'>
                    <span class='input-group-text'>Da</span>
                    <input type='text' class='form-control bg-transparent' name='wi-from' value='$from' readonly>
                    <span class='input-group-text'>A</span>
                    <input type='text' class='form-control bg-transparent' name='wi-to' value='$to' readonly>
                    <button type='submit' class='btn btn-dark'><i class='bi bi-search'></i> Cerca</button>
                </div>
            </form>
            <script>
                $('.input-daterange').datepicker({
                    format: 'dd/mm/yyyy',
                    language: 'it',  
                    orientation: 'bottom left'
                });
            </script>
        </div>
        <div class='col-12'></div>
        <div class='col-6'>
            <span>Filtra per mese:</span>
            <div class='container mt-1' style='max-width: 100%;'>
                <div class='row row-cols-auto gap-2'>
                    $BUTTONS_MONTH
                </div>
            </div>
        </div>";
        
        // $RETURN->html .= "<div class='col-6'>
        //     <span>Filtra per anno:</span>
        //     <div class='container mt-1' style='max-width: 100%;'>
        //         <div class='row row-cols-auto gap-2'>
        //             $BUTTONS_YEAR
        //         </div>
        //     </div>
        // </div>";

        return $RETURN;

    }

    function filterCustom() {

        global $NAME;
        global $TEXT;
        global $FILTER_CUSTOM;
        global $QUERY_CUSTOM;

        $ARROW = sqlColumnExists($NAME->table, 'position') ? true : false;

        $QUERY_FILTER = "";

        $QUERY_ALL = empty($QUERY_CUSTOM) ? "`deleted` = 'false' " : $QUERY_CUSTOM." AND `deleted` = 'false' ";

        $FILTER_ID = [];
        
        if (is_array($FILTER_CUSTOM)) {

            $MULTIPLE_FILTER = []; // Array per le colonne multiple

            foreach ($FILTER_CUSTOM as $table => $value) {
                
                $column = isset($value['column']) ? $value['column'] : $table;
                $filter = isset($_GET[$table]) ? $_GET[$table] : '';

                if ($value['type'] == "checkbox" && is_array($filter)) {
                    unset($filter[0]);
                }

                if (!empty($filter)) {

                    if ($value['type'] == "checkbox" || $value['type'] == "tree") {
                        
                        if (isset($value['column_type']) && $value['column_type'] == "multiple") {
                        
                            if (empty($QUERY_CUSTOM)) {
                                $Q = "`deleted` = 'false' ";
                            } else {
                                $Q = $QUERY_CUSTOM." AND `deleted` = 'false' ";
                            }

                            $SQL = sqlSelect($NAME->table, $Q);
                            
                            $MULTIPLE_FILTER[$column] = []; 

                            foreach ($SQL->row as $k => $row) {
                                
                                $array = empty($row[$column]) ? [] : json_decode($row[$column], true);

                                foreach ($array as $value) {
                                    if (in_array($value, $filter) && !in_array($row['id'], $MULTIPLE_FILTER[$column])) {
                                        array_push($MULTIPLE_FILTER[$column], $row['id']);
                                    }
                                }

                            }

                        } else {

                            $QUERY_FILTER .=  empty($QUERY_FILTER) ? "`$column` IN (" : "AND `$column` IN (";

                            foreach ($filter as $value) { $QUERY_FILTER .= "'$value', "; }
                            
                            $QUERY_FILTER = substr($QUERY_FILTER, 0, -2);
                            $QUERY_FILTER .= ") ";

                        }
                        
                    } else {

                        if (isset($value['column_type']) && $value['column_type'] == "multiple") {
                        
                            $SQL = sqlSelect($NAME->table, $QUERY_ALL);
                            
                            $MULTIPLE_FILTER[$column] = []; 

                            foreach ($SQL->row as $key => $row) {
                                
                                $array = empty($row[$column]) ? [] : json_decode($row[$column], true);

                                foreach ($array as $k => $value) {
                                    if ($value == $filter && !in_array($row['id'], $MULTIPLE_FILTER[$column])) {
                                        array_push($MULTIPLE_FILTER[$column], $row['id']);
                                    }
                                }

                            }

                        } else {

                            $QUERY_FILTER .=  empty($QUERY_FILTER) ? "`$column` = '$filter' " : "AND `$column` = '$filter' ";

                        }

                    }
                    
                    $ARROW = false;

                }

            }

            // Creo un array con tutti i valori che ci sono in tutte le colonne dichiarate nel filtro
            $X = [];
            foreach ($MULTIPLE_FILTER as $column => $array) {
                $X = array_merge($X, $array);
            }

            // Conto quante volte sono ripetuti i valori
            $Y = array_count_values($X);
            $N_column = count($MULTIPLE_FILTER);
            foreach ($Y as $id => $value) {
                if ($value == $N_column) {
                    array_push($FILTER_ID, $id);
                }
            }

        }
        
        if (!empty($FILTER_ID)) {

            $filter = implode(" ,", $FILTER_ID);

            if (!empty($filter)) {
                $QUERY_FILTER .= empty($QUERY_FILTER) ? "id IN ($filter) " : "AND id IN ($filter) ";
            } else {
                $QUERY_FILTER .= empty($QUERY_FILTER) ? "id = '' " : "AND id = '' ";
            }

        }

        $ORDER = filterOrder($ARROW);

        $QUERY_ORDER = $ORDER->query;
        $QUERY_ORDER_COL = $ORDER->column;
        $QUERY_ORDER_DIR = $ORDER->direction;
        
        $RETURN = (object) array();
        
        # Campi utilizzati dalla tabella lista in backend
            $RETURN->query_all = $QUERY_ALL;
            $RETURN->query_filter = $QUERY_FILTER;
            $RETURN->query_order = $QUERY_ORDER;
            $RETURN->query_order_col = $QUERY_ORDER_COL;
            $RETURN->query_order_dir = $QUERY_ORDER_DIR;
        #

        $RETURN->arrow = $ARROW;
        $RETURN->title = ucwords($TEXT->all)." $TEXT->article $TEXT->titleP";

        return $RETURN;

    }

    function createFilterCustom() {

        global $FILTER_CUSTOM;

        $FILTER_USED = 0;

        $filter = "";
        $script = "<script>";

        foreach ($FILTER_CUSTOM as $table => $x) {
                        
            $name = isset($x['name']) ? $x['name'] : '';
            $value = isset($_GET[$table]) ? $_GET[$table] : '';
            $search = isset($x['search']) ? $x['search'] : '';
            $type = isset($x['type']) ? $x['type'] : '';
            $card = isset($x['card']) ? $x['card'] : '';
            $db = isset($x['database']) ? $x['database'] : '';
            $f = isset($x['function']) ? $x['function'] : '';
            $checkbox = isset($x['array']) ? $x['array'] : '';

            if ($table == "category" && array_key_exists("section", $FILTER_CUSTOM)) {
                
                $checkbox = [];

                $SQL = sqlSelect('category', ['deleted' => 'false'], null, 'name', 'ASC');

                foreach ($SQL->row as $key => $row) {
                    
                    $id = $row['id'];
                    $v = $row['name'];
                    $sectionF = $row['section_id'];

                    if (!isset($sectionF)) {
                        $sectionF = [];
                    }else{
                        $sectionF = explode(",", $sectionF);
                    }

                    $sectionF = json_encode($sectionF);
                    
                    $checkbox[$id] = [];
                    $checkbox[$id]['name'] = $v;
                    $checkbox[$id]['filter'] = [];
                    $checkbox[$id]['filter']['section'] = $sectionF;

                }

                if (array_key_exists('subcategory', $FILTER_CUSTOM)) {
                    $subFilter = "filterSubcategory();";
                }

                $script .= "
                function disabledCheckbox(element) {
                    element.disabled = true;
                    element.classList.remove('bg-danger');
                    element.classList.remove('border-danger');
                    element.setAttribute('onclick', '');
                    element.parentElement.style.display= 'none';
                }

                function filterCategory() {
                    document.querySelectorAll('.category').forEach(element => {
                        
                        var section = JSON.parse(element.dataset.section);
                        var sectionFilter = []
                        var checkboxes = document.querySelectorAll('.section:checked');
        
                        for (var i = 0; i < checkboxes.length; i++) {
                            sectionFilter.push(checkboxes[i].value)
                        }
        
                        if (section.some(r=> sectionFilter.includes(r))) {
                            var showSection = true;
                        }else{
                            var showSection = false;
                        }
        
                        if (showSection) {
                            if (element.checked) {
                                element.classList.remove('bg-danger');
                                element.classList.remove('border-danger');
                                element.setAttribute('onclick', '');
                            }
                            element.parentElement.style.display = 'block';
                            element.disabled = false;
                        }else{
                            if (element.checked) {
                                element.disabled = false;
                                element.classList.add('bg-danger');
                                element.classList.add('border-danger');
                                element.setAttribute('onclick', \"disabledCheckbox(this)\");
                                element.parentElement.style.display = 'block';
                            } else {
                                element.disabled = true;
                                element.parentElement.style.display = 'none';
                            }
                        }
        
                        $subFilter
        
                    });
                }
                
                filterCategory();
                $('.section').click(function(){
                    filterCategory();
                });";

            } elseif ($table == "subcategory" && array_key_exists("section", $FILTER_CUSTOM)) {

                $checkbox = [];

                $SQL = sqlSelect('subcategory', ['deleted' => 'false'], null, 'name', 'ASC');

                foreach ($SQL->row as $key => $row) {
                    
                    $id = $row['id'];
                    $v = $row['name'];
                    $sectionF = $row['section_id'];
                    $categoryF = $row['category_id'];

                    if (!isset($sectionF)) {
                        $sectionF = [];
                    }else{
                        $sectionF = explode(",", $sectionF);
                    }

                    if (!isset($categoryF)) {
                        $categoryF = [];
                    }else{
                        $categoryF = explode(",", $categoryF);
                    }

                    $sectionF = json_encode($sectionF);
                    $categoryF = json_encode($categoryF);
                    
                    $checkbox[$id] = [];
                    $checkbox[$id]['name'] = $v;
                    $checkbox[$id]['filter'] = [];
                    $checkbox[$id]['filter']['section'] = $sectionF;
                    $checkbox[$id]['filter']['category'] = $categoryF;

                }

                $script .= "
                function filterSubcategory() {
                    document.querySelectorAll('.subcategory').forEach(element => {
                        
                        var section = JSON.parse(element.dataset.section);
                        var category = JSON.parse(element.dataset.category);
                        
                        var sectionFilter = [];
                        var sectionCategory = [];
        
                        var checkboxes = document.querySelectorAll('.section:checked');
        
                        for (var i = 0; i < checkboxes.length; i++) {
                            sectionFilter.push(checkboxes[i].value)
                        }
        
                        if (section.some(r=> sectionFilter.includes(r))) {
                            var showSection = true;
                        }else{
                            var showSection = false;
                        }
        
                        var checkboxes = document.querySelectorAll('.category:checked');
        
                        for (var i = 0; i < checkboxes.length; i++) {
                            sectionCategory.push(checkboxes[i].value)
                        }
        
                        if (category.some(r=> sectionCategory.includes(r))) {
                            var showCategory = true;
                        }else{
                            var showCategory = false;
                        }
        
                        if (showSection && showCategory) {
                            if (element.checked) {
                                element.classList.remove('bg-danger');
                                element.classList.remove('border-danger');
                                element.setAttribute('onclick', '');
                            }
                            element.parentElement.style.display = 'block';
                            element.disabled = false;
                        }else{
                            if (element.checked) {
                                element.disabled = false;
                                element.classList.add('bg-danger');
                                element.classList.add('border-danger');
                                element.setAttribute('onclick', \"disabledCheckbox(this)\");
                                element.parentElement.style.display = 'block';
                            } else {
                                element.disabled = true;
                                element.parentElement.style.display = 'none';
                            }
                        }
        
                    });
                }

                filterSubcategory();
                $('.category').click(function(){
                    filterCategory();
                });

                ";
                
            } elseif ($table == "visible") {
                
                $checkbox = [
                    '' => "Tutti",
                    'true' => "Visibile",
                    'false' => "Nascosto",
                ];
                
            } elseif ($table == "active") {
                
                $checkbox = [
                    '' => "Tutti",
                    'true' => "Abilitati",
                    'false' => "Disabilitati",
                ];
                
            } elseif ($table == "evidence") {
                
                $checkbox = [
                    '' => "Tutti",
                    'true' => "Si",
                    'false' => "No",
                ];
                
            } else {

                if ($db) {

                    $checkbox = ($type == 'radio') ? ['' => "Tutti"] : [];

                    $SQL = sqlSelect($table, ['deleted' => 'false'], null, 'name', 'ASC');

                    foreach ($SQL->row as $key => $row) {
                        
                        $f = $row['id'];
                        $v = $row['name'];

                        $checkbox[$f] = $v;

                    }

                } elseif (!empty($f)) {

                    $checkbox = ($type == 'radio') ? [ '' => "Tutti" ] : [];
                    $checkbox = array_merge($checkbox, call_user_func($f));

                }
                
            }

            if (count($checkbox) < 5 && $type == 'radio' && $search != true) {

                $HTML = select($name, $table, $checkbox, 'old', null, $value);

            } else {

                if ($type == 'checkbox' || $type == 'radio') {
                    $HTML = check($name, $table, $checkbox, null, $type, $search, $value);
                } else if ($type == 'select') {
                    $HTML = select($name, $table, $checkbox, 'old', null, $value);
                } else if ($type == 'tree') {
                    $HTML = checkTree($name, $table, $checkbox, null, 'checkbox', true, $value);
                }

            }

            $filter .= "
            <div class='col-3'>
                $HTML
            </div>";

            if (isset($_GET[$table]) && !empty($_GET[$table])) { 
                if (is_array($_GET[$table])) {
                    if (isset($_GET[$table][1])) {
                        $FILTER_USED++; 
                    }
                } else {
                    $FILTER_USED++; 
                }
            }

        }

        $script .= "</script>";
        
        $QUERY_STRING = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : "";

        $URL_QUERY = [];
        parse_str($QUERY_STRING, $URL_QUERY);
        
        $QUERY_INPUT = "";

        foreach ($URL_QUERY as $key => $value) {
            if (!array_key_exists(str_replace('[]', '', $key), $FILTER_CUSTOM)) {
                $QUERY_INPUT .= "<input type='hidden' name='$key' value='$value'>";
            }
        }

        $HTML = "
        <div class='col-12 collapse filter-container mt-3 border-top border-bottom'>
            <form action='' method='get' onsubmit='loadingSpinner()' class='my-3'>
                <div class='row g-3'>

                    $QUERY_INPUT
                    $filter

                    <div class='col-3'>
                        <button type='submit' class='btn btn-dark btn-sm'>
                            <i class='bi bi-search'></i> Applica filtri
                        </button>
                    </div>

                </div>
            </form>
        </div>
        $script";

        $button = "<button type='button' class='position-relative btn btn-secondary btn-sm' data-bs-toggle='collapse' data-bs-target='.filter-container' aria-expanded='false'>";
        $button .= "<i class='bi bi-filter'></i>"; # Icona filtri
        $button .= " Filtri";
        $button .= ($FILTER_USED > 0) ? "<span class='position-absolute top-0 start-0 translate-middle badge rounded-pill bg-primary' style='--bs-badge-font-size: 0.7em;'>$FILTER_USED <span class='visually-hidden'>unread messages</span></span>" : "";
        $button .= "</button>";

        $RETURN = (object) array();
        $RETURN->button = $button;
        $RETURN->html = $HTML;

        return $RETURN;

    }

    # Funzioni obsolete
        function filterLimit() {

            global $QUERY_CUSTOM;

            global $QUERY_ORDER;
            global $QUERY_DIRECTION;

            global $TEXT;
            global $NAME;

            $LIMIT = isset($_GET['limit']) ? $_GET['limit'] : '';
            $range = [25 => '25', 50 => '50', 100 => '100', 250 => '500', 500 => '500', 'all' => 'tutti'];
            $ARROW = true;

            if (empty($QUERY_CUSTOM)) {
                $QUERY = "`deleted` = 'false' ";
            }else{
                $QUERY = $QUERY_CUSTOM." AND `deleted` = 'false' ";
            }

            $LINES = sqlCount($NAME->table, $QUERY, 'id', true);

            if ($LIMIT != 'all') {

                $SQL_LIMIT = 'LIMIT ';
                $LIMIT = empty($LIMIT) ? 25 : $LIMIT;
                $SQL_LIMIT .= $LIMIT;

                $TITLE = ucwords($TEXT->last)." $LIMIT $TEXT->titleP";

                $SELECTED_LINES = ($LINES < $LIMIT) ? $LINES : $LIMIT;

                if (isset($QUERY_ORDER) && !empty($QUERY_ORDER)) {

                    $QUERY .= "ORDER BY $QUERY_ORDER ";

                    if (isset($QUERY_DIRECTION) && !empty($QUERY_DIRECTION)) {
                        $QUERY .= "$QUERY_DIRECTION ";
                    } else {
                        $QUERY .= "ASC ";
                    }

                } else {

                    $QUERY .= "ORDER BY `creation` DESC ";

                }

                $QUERY .= "LIMIT $LIMIT";

                $ARROW = false;

            }else{  

                if (!empty($_GET['q'])) {
                    $filter = filterSearch();
                } else {
                    $filter = filterCustom();
                }

                $TITLE = $filter->title;
                $SELECTED_LINES = $filter->selected_lines;
                $QUERY = $filter->query;
                $ARROW = $filter->arrow;

            }
            
            $buttons = "";

            foreach ($range as $key => $text) {

                if ($key != $LIMIT) {
                    $outline = "-outline";
                }else{
                    $outline = "";
                }

                if ($key != 'all') {
                    $text = "$TEXT->last $key";
                }

                $text = ucwords($text);

                $buttons .= "<a href='?limit=$key' class='btn btn$outline-dark btn-sm col' tabindex='-1' role='button'>
                    $text
                </a>";

            }

            if (sqlColumnExists($NAME->table, 'position') && $ARROW) {
                $ARROW = true;
            } else {
                $ARROW = false;
            }
            
            $RETURN = (object) array();
            $RETURN->html = $buttons;
            $RETURN->query = $QUERY;
            $RETURN->selected_lines = $SELECTED_LINES;
            $RETURN->lines = $LINES;
            $RETURN->arrow = $ARROW;
            $RETURN->title = $TITLE;

            return $RETURN;

        }
        
        function createSearchBar() {

            $value = isset($_GET['q']) ? sanitize($_GET['q']) : '';

            $form = "
            <form action='' method='get' onsubmit='loadingSpinner()'>

                <input type='hidden' name='wi-limit' value='all'>

                <div class='input-group input-group-sm'>
                    <input type='text' class='form-control' id='input-search' name='q' value='$value' onkeyup='search()' aria-describedby='button-search'>
                    <button type='submit' class='btn btn-dark' aria-describedby='button-search'>
                        <i class='bi bi-search'></i> Cerca
                    </button>
                </div>

            </form>";

            $script = "
            <script>
                function search() {
                    
                    var value = document.getElementById('input-search').value.toLowerCase();
                    
                    document.querySelectorAll('.search-here').forEach(element => {

                        var keyword = element.dataset.keyword.toLowerCase();

                        if (keyword.includes(value)) {
                            element.style.display = 'table-row';
                        }else{
                            element.style.display = 'none';
                        }

                    });
                    
                }
            </script>
            ";

            return "$script $form";

        }

        function filterSearch() {

            global $NAME;
            global $TEXT;
            global $FILTER_SEARCH;
            global $FILTER_ORDER;
            global $FILTER_DIRECTION;
            global $QUERY_CUSTOM;

            $ARROW = true;

            if (empty($QUERY_CUSTOM)) {
                $QUERY = "`deleted` = 'false' ";
            } else {
                $QUERY = $QUERY_CUSTOM." AND `deleted` = 'false' ";
            }

            $searchValue = isset($_GET['q']) ? sanitize($_GET['q']) : '';

            if (!empty($searchValue)) {

                $QUERY_COLUMN = "AND CONCAT_WS(' ',";

                foreach ($FILTER_SEARCH as $key => $value) { $QUERY_COLUMN .= "`$value`, "; }

                $QUERY_COLUMN = substr($QUERY_COLUMN, 0, -2).") LIKE";

                $searchArray = explode(' ', $searchValue);

                foreach ($searchArray as $key => $search) { $QUERY .= $QUERY_COLUMN." '%$search%' "; }

                $QUERY = substr($QUERY, 0, -1);

                $ARROW = false;

            }

            if (isset($FILTER_ORDER) && !empty($FILTER_ORDER)) {

                $QUERY .= "ORDER BY `$FILTER_ORDER` ";

                if (isset($FILTER_DIRECTION) && !empty($FILTER_DIRECTION)) {
                    $QUERY .= "$FILTER_DIRECTION ";
                } else {
                    $QUERY .= "ASC ";
                }

            } else {

                $QUERY .= "ORDER BY `creation` DESC ";

            }

            $SQL = sqlSelect($NAME->table, $QUERY);
            
            $RETURN = (object) array();
            $RETURN->query = $QUERY;
            $RETURN->selected_lines = $SQL->Nrow;
            $RETURN->arrow = $ARROW;
            $RETURN->title = ucwords($TEXT->titleP)." inerenti alla tua ricerca: $searchValue";

            return $RETURN;

        }

    #

?>