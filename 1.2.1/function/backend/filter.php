<?php

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

        $SQL = sqlSelect($NAME->table, $QUERY);
        $LINES = $SQL->Nrow;

        if ($LIMIT != 'all') {

            $SQL_LIMIT = 'LIMIT ';
            if (empty($LIMIT)) {
                $LIMIT = 25;
            }
            $SQL_LIMIT .= $LIMIT;
            $TITLE = ucwords($TEXT->last)." $LIMIT $TEXT->titleP";

            if ($SQL->Nrow < $LIMIT) {
                $SELECTED_LINES = $SQL->Nrow;
                $LINES = $SQL->Nrow;
            }else{
                $SELECTED_LINES = $LIMIT;
                $LINES = $SQL->Nrow;
            }

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

    function filterDate() {

        global $QUERY_CUSTOM;

        global $QUERY_ORDER;
        global $QUERY_DIRECTION;

        global $FILTER_COLUMN;

        global $HOW_MANY_DAYS;

        global $TEXT;
        global $NAME;

        $ARROW = false;

        if (empty($QUERY_CUSTOM)) {
            $QUERY = "`deleted` = 'false' ";
        }else{
            $QUERY = $QUERY_CUSTOM." AND `deleted` = 'false' ";
        }

        $DAYS = isset($HOW_MANY_DAYS) ? $HOW_MANY_DAYS : 30;
        $COLUMN = isset($FILTER_COLUMN) ? $FILTER_COLUMN : 'creation';

        $from = isset($_GET['from']) ? $_GET['from'] : '';
        $to = isset($_GET['to']) ? $_GET['to'] : '';
        $YEAR = isset($_GET['year']) ? $_GET['year'] : '';
        $MONTH = isset($_GET['month']) ? $_GET['month'] : '';
        $LIMIT = isset($_GET['limit']) ? $_GET['limit'] : '';

        // Array filter
            $ARRAY_MONTH = [];
            $ARRAY_YEAR = [];
            $BUTTONS_MONTH = "";
            $BUTTONS_YEAR = "";

            $SQL = sqlSelect($NAME->table, $QUERY);
            $LINES = $SQL->Nrow;

            foreach ($SQL->row as $key => $row){

                $m = date("F", strtotime($row[$COLUMN]));
                $y = date("Y", strtotime($row[$COLUMN]));

                $date = "$m $y";

                if (!in_array($date, $ARRAY_MONTH)) {
                    array_push($ARRAY_MONTH, $date);

                    $mese = translateDate($m, 'month');

                    if ($m == $MONTH && $y == $YEAR) {
                        $outline = "";
                    }else{
                        $outline = "-outline";
                    }

                    $BUTTONS_MONTH .= "
                    <a href='?month=$m&year=$y' class='btn btn$outline-dark btn-sm col' tabindex='-1' role='button'>
                        $mese $y
                    </a>
                    ";
                    
                }

                if (!in_array($y, $ARRAY_YEAR)) {

                    array_push($ARRAY_YEAR, $y);

                    if ($y == $YEAR && empty($MONTH)) {
                        $outline = "";
                    }else{
                        $outline = "-outline";
                    }

                    $BUTTONS_YEAR .= "
                    <a href='?year=$y' class='btn btn$outline-dark btn-sm col' tabindex='-1' role='button'>
                        $y
                    </a>
                    ";

                }

            }

        //

        if ($LIMIT != 'all') {

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

                $from = date('d/m/Y',mktime(0,0,0,date('m'),date('d')-$DAYS,date('Y')));
                $to = date('d/m/Y',mktime(0,0,0,date('m'),date('d'),date('Y')));

                $TITLE = ucwords($TEXT->titleP)." ultimi $DAYS giorni";

            }

            if (empty($TITLE)) {
                $TITLE = ucwords($TEXT->titleP)." dal $from al $to";
            }
    
            list($day,$month,$year) = explode("/", $from);
            $SQL_from = "$year-$month-$day";
            list($day,$month,$year) = explode("/", $to);
            $SQL_to = "$year-$month-$day";

            $QUERY .= "AND `$COLUMN` BETWEEN '$SQL_from 00:00:00' AND '$SQL_to 23:59:59' ";

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

            $limitAllOutline = "-outline";

        } else {

            $first = sqlSelect($NAME->table, null, 1, $COLUMN, 'ASC')->row[$COLUMN];
            $last = sqlSelect($NAME->table, null, 1, $COLUMN, 'DESC')->row[$COLUMN];
            
            $from = date("d/m/Y", strtotime($first));
            $to = date("d/m/Y", strtotime($last));
            
            if (!empty($_GET['q'])) {
                $filter = filterSearch();
            } else {
                $filter = filterCustom();
            }

            $TITLE = $filter->title;
            $SELECTED_LINES = $filter->selected_lines;
            $QUERY = $filter->query;
            $ARROW = $filter->arrow;
            
            $limitAllOutline = "";

        }

        if (sqlColumnExists($NAME->table, 'position') && $ARROW) {
            $ARROW = true;
        } else {
            $ARROW = false;
        }

        $RETURN = (object) array();
        $RETURN->selected_lines = !empty($SELECTED_LINES) ? $SELECTED_LINES : sqlSelect($NAME->table, $QUERY)->Nrow;
        $RETURN->lines = $LINES;
        $RETURN->from = $from;
        $RETURN->to = $to;
        $RETURN->title = $TITLE;
        $RETURN->query = $QUERY;
        $RETURN->arrow = $ARROW;

        $RETURN->array = (object) array();
        $RETURN->array->month = $ARRAY_MONTH;
        $RETURN->array->year = $ARRAY_YEAR;

        $RETURN->html = "
        <div class='col-5 p-0'>
            <form method='get'>
                <div class='input-group input-group-sm input-daterange wi-daterange-filter'>
                    <span class='input-group-text'>Da</span>
                    <input type='text' class='form-control bg-transparent' name='from' value='$from' readonly>
                    <span class='input-group-text'>A</span>
                    <input type='text' class='form-control bg-transparent' name='to' value='$to' readonly>
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
        <div class='col-12 p-0'>
            <span>Filtra per anno:</span>
            <div class='container mt-1' style='max-width: 100%;'>
                <div class='row row-cols-auto gap-2'>
                    $BUTTONS_YEAR
                    <a href='?limit=all' class='btn btn$limitAllOutline-dark btn-sm col' tabindex='-1' role='button'>
                        Mostra tutti
                    </a>
                </div>
            </div>
        </div>
        <div class='col-12 p-0'>
            <span>Filtra per mese:</span>
            <div class='container mt-1' style='max-width: 100%;'>
                <div class='row row-cols-auto gap-2'>
                    $BUTTONS_MONTH
                    <a href='?limit=all' class='btn btn$limitAllOutline-dark btn-sm col' tabindex='-1' role='button'>
                        Mostra tutti
                    </a>
                </div>
            </div>
        </div>";

        return $RETURN;

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
        }else{
            $QUERY = $QUERY_CUSTOM." AND `deleted` = 'false' ";
        }

        $searchValue = isset($_GET['q']) ? trim($_GET['q']) : '';

        if (!empty($searchValue)) {

            $QUERY .= "AND CONCAT_WS(' ',";

            foreach ($FILTER_SEARCH as $key => $value) {
                $QUERY .= "`$value`, ";
            }

            $QUERY = substr($QUERY, 0, -2).") LIKE '%$searchValue%' ";

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

    function filterCustom() {

        global $NAME;
        global $TEXT;
        global $FILTER_CUSTOM;
        global $FILTER_ORDER;
        global $FILTER_DIRECTION;
        global $QUERY_CUSTOM;

        $ARROW = true;

        if (empty($QUERY_CUSTOM)) {
            $QUERY = "`deleted` = 'false' ";
        }else{
            $QUERY = $QUERY_CUSTOM." AND `deleted` = 'false' ";
        }

        $FILTER_ID = [];
        
        if (is_array($FILTER_CUSTOM)) {

            $MULTIPLE_FILTER = []; // Array per le colonne multiple

            foreach ($FILTER_CUSTOM as $table => $value) {
                
                $column = $value['column'];
                $filter = isset($_GET[$table]) ? $_GET[$table] : '';

                if (!empty($filter)) {

                    if ($value['type'] == "checkbox") {
                        
                        if (isset($value['column_type']) && $value['column_type'] == "multiple") {
                        
                            if (empty($QUERY_CUSTOM)) {
                                $Q = "`deleted` = 'false' ";
                            }else{
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

                            $QUERY .= "AND `$column` IN (";

                            foreach ($filter as $value) {
                                $QUERY .= "'$value', ";
                            }
                            
                            $QUERY = substr($QUERY, 0, -2);
                            $QUERY .= ") ";

                        }
                        
                    } else {

                        if (isset($value['column_type']) && $value['column_type'] == "multiple") {
                        
                            if (empty($QUERY_CUSTOM)) {
                                $Q = "`deleted` = 'false' ";
                            }else{
                                $Q = $QUERY_CUSTOM." AND `deleted` = 'false' ";
                            }

                            $SQL = sqlSelect($NAME->table, $Q);
                            
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

                            $QUERY .= "AND `$column` = '$filter' ";

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
                $QUERY .= "AND id IN ($filter) ";
            }else{
                $QUERY .= "AND id = '' ";
            }
        }
        
        if ($ARROW == true && sqlColumnExists($NAME->table, 'position')) {

            $QUERY .= "ORDER BY `position` ASC ";

        } elseif (isset($FILTER_ORDER) && !empty($FILTER_ORDER)) {

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
        $RETURN->title = ucwords($TEXT->all)." $TEXT->article $TEXT->titleP";

        return $RETURN;

    }

    function createSearchBar() {

        $value = isset($_GET['q']) ? $_GET['q'] : '';

        $form = "
        <form action='' method='get' onsubmit='loadingSpinner()'>

            <input type='hidden' name='limit' value='all'>

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

    function createFilterCustom() {

        global $FILTER_CUSTOM;

        $button = "<button type='button' class='btn btn-secondary btn-sm' data-bs-toggle='collapse' data-bs-target='.filter-container' aria-expanded='false'>
            <i class='bi bi-filter'></i>
            Filtri
        </button>";

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
                
            } else {

                if ($db) {

                    if ($type == 'radio') {
                        $checkbox = ['' => "Tutti"];
                    }else{
                        $checkbox = [];
                    }

                    $SQL = sqlSelect($table, ['deleted' => 'false'], null, 'name', 'ASC');

                    foreach ($SQL->row as $key => $row) {
                        
                        $f = $row['id'];
                        $v = $row['name'];

                        $checkbox[$f] = $v;

                    }

                } elseif (!empty($f)) {

                    if ($type == 'radio') {
                        $checkbox = ['' => "Tutti"];
                    }else{
                        $checkbox = [];
                    }

                    $checkbox = array_merge($checkbox, call_user_func($f));

                }
                
            }

            if (count($checkbox) > 3) {
                $HTML = check($name, $table, $checkbox, '', $type, $search, $value);
            } else {
                $HTML = select($name, $table, $checkbox, 'old', null, $value);
            }

            $filter .= "
            <div class='col-3'>
                $HTML
            </div>";

        }

        $script .= "</script>";

        if (isset($_GET['limit']) && $_GET['limit'] == 'all') {
            $collapseShow = "show";
        }else{
            $collapseShow = "";
        }

        $HTML = "
        <div class='col-12 collapse $collapseShow filter-container mt-3 pt-3 border-top'>
            <form action='' method='get' onsubmit='loadingSpinner()'>
                <div class='row g-3'>

                    <input type='hidden' name='limit' value='all'>

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

        $RETURN = (object) array();
        $RETURN->button = $button;
        $RETURN->html = $HTML;

        return $RETURN;

    }

?>