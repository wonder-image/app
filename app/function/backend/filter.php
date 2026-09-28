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
            $DIRECTION = filterOrderDirection($FILTER_DIRECTION ?? null);

        }

        $RETURN = (object) array();
        $RETURN->query = "ORDER BY ".\Wonder\Sql\Query::escapeIdentifier((string) $COLUMN)." $DIRECTION ";
        $RETURN->column = $COLUMN;
        $RETURN->direction = $DIRECTION;

        return $RETURN;

    }

    /**
     * @deprecated Usare Wonder\Backend\Table\Table::filterDate(), che passa da Wonder\Backend\Filter\FilterDate.
     */
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

        # Valori del GET validati come in FilterDate: quelli non validi valgono come assenti
        $from = \Wonder\Backend\Filter\FilterDate::parseDate($_GET['wi-from'] ?? null)?->format('d/m/Y') ?? '';
        $to = \Wonder\Backend\Filter\FilterDate::parseDate($_GET['wi-to'] ?? null)?->format('d/m/Y') ?? '';
        $YEAR = \Wonder\Backend\Filter\FilterDate::parseInteger($_GET['wi-year'] ?? null, \Wonder\Backend\Filter\FilterDate::MIN_YEAR, \Wonder\Backend\Filter\FilterDate::MAX_YEAR);
        $MONTH = \Wonder\Backend\Filter\FilterDate::parseInteger($_GET['wi-month'] ?? null, 1, 12);

        $QUERY_STRING = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : "";

        # Gli altri parametri restano nei link dei mesi e nei campi nascosti del form
        $URL_QUERY = [];
        parse_str($QUERY_STRING, $URL_QUERY);
        unset($URL_QUERY['wi-from'], $URL_QUERY['wi-to'], $URL_QUERY['wi-year'], $URL_QUERY['wi-month'], $URL_QUERY['wi-limit']);

        $QUERY_INPUT = filterHiddenInputs($URL_QUERY);

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

                # Nel link il mese e' numerico: il nome inglese non passerebbe la validazione
                $monthNumber = (int) date("n", $lastDate);
                $href = htmlspecialchars('?'.http_build_query([ 'wi-month' => $monthNumber, 'wi-year' => (int) $year ] + $URL_QUERY, '', '&'), ENT_QUOTES, 'UTF-8');

                if ($monthNumber === $MONTH && (int) $year === $YEAR) {
                    $outline = "";
                    $active = "active";
                }else{
                    $outline = "-outline";
                    $active = "";
                }

                if ($im < 5) {
                    $BUTTONS_MONTH .= "<a href='$href' class='btn btn$outline-dark btn-sm col' tabindex='-1' role='button'> $mese $year </a>";
                } else {
                    $OTHER_BUTTONS_MONTH .= "<a href='$href' class='dropdown-item $active'>$mese $year</a>";
                }
                
                $im++;

                $lastDate = strtotime("-1 month", $lastDate);

            }

            $first = date('Y', strtotime($TABLE_INFO->create_time));
            $last = date('Y');

            while ($last >= $first) {

                $year = $last;
                array_push($ARRAY_YEAR, $year);

                $href = htmlspecialchars('?'.http_build_query([ 'wi-year' => (int) $year ] + $URL_QUERY, '', '&'), ENT_QUOTES, 'UTF-8');

                if ((int) $year === $YEAR && $MONTH === null) {
                    $outline = "";
                    $active = "active";
                } else {
                    $outline = "-outline";
                    $active = "";
                }

                if ($iy < 5) {
                    $BUTTONS_YEAR .= "<a href='$href' class='btn btn$outline-dark btn-sm col' tabindex='-1' role='button'> $year </a>";
                } else {
                    $OTHER_BUTTONS_YEAR .= "<a href='$href' class='dropdown-item $active'>$year</a>";
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

            if ($MONTH === null && $YEAR !== null) {

                $from = '01/01/'.$YEAR;
                $to = '31/12/'.$YEAR;

                $TITLE = ucwords($TEXT->titleP)." del ".$YEAR;

            }

            if ($MONTH !== null && $YEAR !== null) {

                $date = mktime(0, 0, 0, $MONTH, 1, $YEAR);
                $from = date('01/m/Y', $date);
                $to = date('t/m/Y', $date);

                $mese = translateDate(date('Y-m-d', $date), 'month');
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

            if (empty($TITLE)) {
                if ($from !== '' && $to !== '') {
                    $TITLE = ucwords($TEXT->titleP)." dal $from al $to";
                } else if ($from !== '') {
                    $TITLE = ucwords($TEXT->titleP)." dal $from";
                } else {
                    $TITLE = ucwords($TEXT->titleP)." fino al $to";
                }
            }

            # Con un solo estremo valido la condizione diventa >= o <=
            $CONDITION = \Wonder\Backend\Filter\FilterDate::buildCondition($COLUMN, $from, $to);

            if ($CONDITION !== '') {
                $QUERY_ALL .= "AND ".$CONDITION;
            }

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

        $FROM_VALUE = htmlspecialchars($from, ENT_QUOTES, 'UTF-8');
        $TO_VALUE = htmlspecialchars($to, ENT_QUOTES, 'UTF-8');

        $RETURN->html = "
        <div class='col-5'>
            <form method='get'>
                $QUERY_INPUT
                <div class='input-group input-group-sm input-daterange wi-daterange-filter'>
                    <span class='input-group-text'>Da</span>
                    <input type='text' class='form-control bg-transparent' name='wi-from' value='$FROM_VALUE' readonly>
                    <span class='input-group-text'>A</span>
                    <input type='text' class='form-control bg-transparent' name='wi-to' value='$TO_VALUE' readonly>
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
                $COLUMN_SQL = \Wonder\Sql\Query::escapeIdentifier((string) $column);
                $type = isset($value['type']) ? $value['type'] : '';
                $filter = isset($_GET[$table]) ? $_GET[$table] : '';

                if ($type == "checkbox" && is_array($filter)) {
                    unset($filter[0]);
                }

                # Valori fuori dalle opzioni del filtro: valgono come assenti
                $filter = filterCustomValue($table, $value, $filter);

                if (!empty($filter)) {

                    if ($type == "checkbox" || $type == "tree") {
                        
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

                            $QUERY_FILTER .=  empty($QUERY_FILTER) ? "$COLUMN_SQL IN (" : "AND $COLUMN_SQL IN (";

                            foreach ($filter as $item) { $QUERY_FILTER .= "'".filterSqlEscape($item)."', "; }
                            
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

                            $VALUE_SQL = filterSqlEscape($filter);

                            $QUERY_FILTER .=  empty($QUERY_FILTER) ? "$COLUMN_SQL = '$VALUE_SQL' " : "AND $COLUMN_SQL = '$VALUE_SQL' ";

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
            $checkbox = filterCustomOptions($table, $x);

            # Un filtro senza opzioni note ha '': per i campi e' un elenco vuoto
            if (!is_array($checkbox)) {
                $checkbox = [];
            }

            if ($table == "category" && array_key_exists("section", $FILTER_CUSTOM)) {
                
                $subFilter = array_key_exists('subcategory', $FILTER_CUSTOM) ? "filterSubcategory();" : "";

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
                } else {
                    # Senza type, o con un type sconosciuto, il filtro non ha un campo
                    $HTML = "";
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
        
        # I filtri li scrivono i loro campi: nei campi nascosti restano gli altri parametri
        $QUERY_INPUT = filterHiddenInputs(array_diff_key($URL_QUERY, $FILTER_CUSTOM));

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

    # Supporto dei filtri legacy: campi nascosti, escape SQL, opzioni dei filtri personalizzati
        function filterHiddenInputs(array $params) {

            $HTML = "";

            # Le coppie di http_build_query(): anche i parametri a piu' valori restano nel form
            foreach (explode('&', http_build_query($params, '', '&')) as $PAIR) {

                if ($PAIR === '') { continue; }

                [ $key, $value ] = explode('=', $PAIR, 2);

                $HTML .= "<input type='hidden' name='".htmlspecialchars(urldecode($key), ENT_QUOTES, 'UTF-8')."' value='".htmlspecialchars(urldecode($value), ENT_QUOTES, 'UTF-8')."'>";

            }

            return $HTML;

        }

        function filterSqlEscape($value) {

            global $mysqli;

            # La connessione di sqlSelect(): l'escape segue charset e modalita' SQL del server
            $connection = ($mysqli instanceof \mysqli) ? $mysqli : \Wonder\Sql\Connection::Connect('main');

            return $connection->real_escape_string((string) $value);

        }

        function filterOrderDirection($direction) {

            # Solo ASC o DESC: il resto vale come assente
            if (is_string($direction) && in_array(strtoupper(trim($direction)), [ 'ASC', 'DESC' ], true)) {
                return trim($direction);
            }

            return "ASC";

        }

        function filterCustomOptions($table, $x) {

            global $FILTER_CUSTOM;

            $type = isset($x['type']) ? $x['type'] : '';
            $db = isset($x['database']) ? $x['database'] : '';
            $f = isset($x['function']) ? $x['function'] : '';
            $checkbox = isset($x['array']) ? $x['array'] : '';

            if ($table == "category" && array_key_exists("section", $FILTER_CUSTOM)) {

                $checkbox = [];

                $SQL = sqlSelect('category', ['deleted' => 'false'], null, 'name', 'ASC');

                foreach ($SQL->row as $key => $row) {

                    $checkbox[$row['id']] = [
                        'name' => $row['name'],
                        'filter' => [
                            'section' => json_encode(isset($row['section_id']) ? explode(",", $row['section_id']) : []),
                        ],
                    ];

                }

            } elseif ($table == "subcategory" && array_key_exists("section", $FILTER_CUSTOM)) {

                $checkbox = [];

                $SQL = sqlSelect('subcategory', ['deleted' => 'false'], null, 'name', 'ASC');

                foreach ($SQL->row as $key => $row) {

                    $checkbox[$row['id']] = [
                        'name' => $row['name'],
                        'filter' => [
                            'section' => json_encode(isset($row['section_id']) ? explode(",", $row['section_id']) : []),
                            'category' => json_encode(isset($row['category_id']) ? explode(",", $row['category_id']) : []),
                        ],
                    ];

                }

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

            } elseif ($db) {

                $checkbox = ($type == 'radio') ? ['' => "Tutti"] : [];

                $SQL = sqlSelect($table, ['deleted' => 'false'], null, 'name', 'ASC');

                foreach ($SQL->row as $key => $row) {
                    $checkbox[$row['id']] = $row['name'];
                }

            } elseif (!empty($f)) {

                $checkbox = ($type == 'radio') ? [ '' => "Tutti" ] : [];
                $options = call_user_func($f);

                # Una funzione che non restituisce un array non da' opzioni
                $checkbox = array_merge($checkbox, is_array($options) ? $options : []);

            }

            return $checkbox;

        }

        function filterOptionValues(array $options) {

            $VALUES = [];

            foreach ($options as $key => $label) {

                $VALUES[] = (string) $key;

                # Nei filtri ad albero i figli stanno in 'child'
                if (is_array($label) && isset($label['child']) && is_array($label['child'])) {
                    $VALUES = array_merge($VALUES, filterOptionValues($label['child']));
                }

            }

            return $VALUES;

        }

        function filterCustomValue($table, $x, $filter) {

            if (empty($filter)) {
                return $filter;
            }

            # Con opzioni note passano solo i loro valori ('' e' la scelta vuota); senza, resta l'escape
            $OPTIONS = filterCustomOptions($table, $x);
            $ALLOWED = is_array($OPTIONS) ? array_merge([ '' ], filterOptionValues($OPTIONS)) : null;

            $isAllowed = function ($item) use ($ALLOWED) {
                return (is_string($item) || is_int($item)) && ($ALLOWED === null || in_array((string) $item, $ALLOWED, true));
            };

            if (in_array($x['type'] ?? '', [ 'checkbox', 'tree' ], true)) {
                return is_array($filter) ? array_filter($filter, $isAllowed) : '';
            }

            return $isAllowed($filter) ? $filter : '';

        }

    #

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

            # Solo i valori dei bottoni: il resto vale come assente (gli ultimi 25)
            if (!(is_string($LIMIT) || is_int($LIMIT)) || !in_array((string) $LIMIT, array_map('strval', array_keys($range)), true)) {
                $LIMIT = '';
            }

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
                    $TITLE = $filter->title;
                } else {
                    # Query completa e righe selezionate le calcola filter(): filterCustom() da' solo i pezzi
                    $filter = filter();
                    $TITLE = ucwords($TEXT->all)." $TEXT->article $TEXT->titleP";
                }

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

            $value = (isset($_GET['q']) && is_string($_GET['q'])) ? htmlspecialchars(trim($_GET['q']), ENT_QUOTES, 'UTF-8') : '';

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

            $searchValue = (isset($_GET['q']) && is_string($_GET['q'])) ? $_GET['q'] : '';

            # sanitize() senza l'addslashes() finale: l'apice lo escapa la connessione
            $searchSql = stripslashes(sanitize($searchValue));

            if (!empty($searchSql)) {

                $QUERY_COLUMN = "AND CONCAT_WS(' ',";

                foreach ($FILTER_SEARCH as $key => $value) { $QUERY_COLUMN .= \Wonder\Sql\Query::escapeIdentifier((string) $value).", "; }

                $QUERY_COLUMN = substr($QUERY_COLUMN, 0, -2).") LIKE";

                $searchArray = explode(' ', $searchSql);

                foreach ($searchArray as $key => $search) { $QUERY .= $QUERY_COLUMN." '%".filterSqlEscape($search)."%' "; }

                $QUERY = substr($QUERY, 0, -1);

                $ARROW = false;

            }

            if (isset($FILTER_ORDER) && !empty($FILTER_ORDER)) {

                $QUERY .= "ORDER BY ".\Wonder\Sql\Query::escapeIdentifier((string) $FILTER_ORDER)." ".filterOrderDirection($FILTER_DIRECTION ?? null)." ";

            } else {

                $QUERY .= "ORDER BY `creation` DESC ";

            }

            $SQL = sqlSelect($NAME->table, $QUERY);
            
            $RETURN = (object) array();
            $RETURN->query = $QUERY;
            $RETURN->selected_lines = $SQL->Nrow;
            $RETURN->arrow = $ARROW;
            $RETURN->title = ucwords($TEXT->titleP)." inerenti alla tua ricerca: ".htmlspecialchars(trim($searchValue), ENT_QUOTES, 'UTF-8');

            return $RETURN;

        }

    #