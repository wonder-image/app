
<?php

    if (isset($USER_FILTER->authority) && isset($USER_FILTER->area)) {

        $QUERY_CUSTOM = isset($QUERY_CUSTOM) ? $QUERY_CUSTOM.' AND ' : '';

        if (!empty($USER_FILTER->authority)) {
            if (is_array($USER_FILTER->authority)) {

                $QUERY_CUSTOM .= '(';
                foreach ($USER_FILTER->authority as $k => $v) { $QUERY_CUSTOM .= "`authority` LIKE '%$v%' OR "; }
                $QUERY_CUSTOM = substr($QUERY_CUSTOM, 0, -4).')';

            } else {

                $QUERY_CUSTOM .= "`authority` LIKE '%$USER_FILTER->authority%'";

            }
        }

        if (!empty($USER_FILTER->authority) && !empty($USER_FILTER->area)) {
            $QUERY_CUSTOM .= " AND ";
        }

        if (!empty($USER_FILTER->area)) {
            if (is_array($USER_FILTER->area)) {

                $QUERY_CUSTOM .= '(';
                foreach ($USER_FILTER->area as $k => $v) { $QUERY_CUSTOM .= "`area` LIKE '%$v%' OR "; }
                $QUERY_CUSTOM = substr($QUERY_CUSTOM, 0, -4).')';

            } else {

                $QUERY_CUSTOM .= "`area` LIKE '%$USER_FILTER->area%'";
                
            }
        }
        
    }

    if ($FILTER_TYPE == 'date') {
        $FILTER = filterDate();
    } else {
        $FILTER = filter();
    }

    if (!isset($PAGE_TABLE)) {
        $table = strtoupper($NAME->table);
        $PAGE_TABLE = $TABLE->$table;
    }

    if (!empty($FILTER_CUSTOM)) { $CUSTOM = createFilterCustom(); }

    # Headers
        $TABLE_COLUMNS = [];

        $COLUMN_N = 0;

        if ($FILTER->arrow && $FILTER->selected_lines > 1) {

            array_push($TABLE_COLUMNS, [
                'data' => 0,
                'name' => 'position-up',
                'title' => '',
                'orderable' => false,
                'className' => 'little'
            ]);

            array_push($TABLE_COLUMNS, [
                'data' => 1,
                'name' => 'position-down',
                'title' => '',
                'orderable' => false,
                'className' => 'little'
            ]);


            $COLUMN_N = 2;

        }

        foreach ($TABLE_FIELD as $column => $value) {

            $class = "";

            $label = !empty($value['label']) ? $value['label'] : '';
            $orderable = isset($value['orderable']) ? $value['orderable'] : false;
            $phone = isset($value['phone']) ? $value['phone'] : true;
            $tablet = isset($value['tablet']) ? $value['tablet'] : true;
            $pc = isset($value['pc']) ? $value['pc'] : true;

            $dimension = !empty($value['dimension']) ? $value['dimension'] : '';

            if (empty($dimension)) {
                if ($column == 'authority' || $column == 'active' || $column == 'visible' || $column == 'empty') {
                    $dimension = 'little';
                }
            }

            $class .= $dimension;

            if (!$phone) { $class .= ' min-mobile'; }
            if (!$tablet) { $class .= ' min-tablet'; }
            if (!$pc) { $class .= ' max-tablet'; }

            if ($pc && $tablet && $phone) { $class .= ' all'; }

            array_push($TABLE_COLUMNS, [
                'data' => $COLUMN_N,
                'name' => $column,
                'title' => $label,
                'orderable' => $orderable,
                'className' => $class
            ]);

            $COLUMN_N++;

        }

        if (!empty($TABLE_ACTION)) {

            $row = false;

            foreach ($TABLE_ACTION as $action => $link) {
                if (is_array($link) || $link == true) {
                    $row = true;
                    break;
                }
            }
            
            if ($row) { 
                
                array_push($TABLE_COLUMNS, [
                    'data' => $COLUMN_N,
                    'name' => 'menu',
                    'title' => '',
                    'orderable' => false,
                    'className' => 'little all'
                ]);

            }

        }

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista <?=$TEXT->titleP?></title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>

    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <div class="row g-3">

        <wi-card class="col-12">


            <div class="col-<?=!empty($BUTTON_ADD) ? "8" : "12"?>"> 
                <h3><?=$FILTER->title?></h3>
                <figcaption class="text-muted">
                    Risultati: <span id="wi-table-result"></span>
                </figcaption>
            </div>

            <?php if (!empty($BUTTON_ADD)) { echo "<div class='col-4'>".createAddButton($TEXT->titleS)."</div>"; } ?>

            <?php if (isset($FILTER->html) && !empty($FILTER->html)) :?>
            <div class="col-12">
                <div class="container" style="max-width: 100%;">
                    <div class="row row-cols-auto gap-2">
                        <?=$FILTER->html?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php


                if (!empty($BUTTON_CUSTOM)) {

                    echo "<div class='col-12 d-flex gap-2 justify-content-end'>";

                    if (!empty($BUTTON_CUSTOM)) {
                        foreach ($BUTTON_CUSTOM as $key => $v) {

                            $html = isset($v['html']) ? $v['html'] : false;
                            $value = isset($v['value']) ? $v['value'] : '';
                            $action = isset($v['action']) ? $v['action'] : '';
                            $color = isset($v['color']) ? $v['color'] : 'dark';

                            if ($html) {
                                echo $value;
                            } else {
                                echo "<a $action type='button' class='btn btn-$color btn-sm'>$value</a>";
                            }
                            
                        }
                        
                    }

                    echo "</div>";

                }
                
                if (!empty($FILTER_CUSTOM)) {
                    
                    echo "<div class='col-auto pe-0'>".$CUSTOM->button."</div>";

                }

                if (!empty($FILTER_SEARCH)) { 
                    
                    echo "<div class='col-4 me-auto'>
                        <div class='input-group input-group-sm'>
                            <span class='input-group-text user-select-none'>Cerca: </span>
                            <input type='text' class='form-control' id='wi-table-search'>
                        </div>
                    </div>"; 
                
                }

                echo '<div class="col-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text user-select-none">Mostra:</span>
                        <select class="form-select" id="wi-table-length">
                            <option value="10">10 elementi</option>
                            <option value="25">25 elementi</option>
                            <option value="50">50 elementi</option>
                            <option value="100">100 elementi</option>
                        </select>
                    </div>
                </div>';

                if (!empty($FILTER_CUSTOM)) { echo $CUSTOM->html; }

            ?>

            <div class="col-12">

                <table id="wi-table" class="table table-hover w-100">
                    <thead></thead>
                    <tbody class="table-group-divider"></tbody>
                </table>

            </div>

        </wi-card>
        

    </div>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

    <script>

        <?PHP

            $USER_AUTHORITY = isset($USER_FILTER->authority) ? $USER_FILTER->authority : "";
            
        ?>

        var dataPost = {
            folder: '<?=$NAME->folder?>',
            table: '<?=$NAME->table?>',
            arrow: <?=empty($FILTER->arrow) ? 'false' : 'true'?>,
            url: '<?=$PAGE->uri?>',
            text: {
                titleS: '<?=$TEXT->titleS?>',
                titleP: '<?=$TEXT->titleP?>',
                last: '<?=$TEXT->last?>',
                all: '<?=$TEXT->all?>',
                article: '<?=$TEXT->article?>',
                full: '<?=$TEXT->full?>',
                empty: '<?=$TEXT->empty?>',
                this: '<?=$TEXT->this?>'
            },
            user: {
                area: '<?=isset($USER_FILTER->area) ? $USER_FILTER->area : ''?>',
                authority: <?=is_array($USER_AUTHORITY) ? "JSON.parse('".json_encode($USER_AUTHORITY)."')" : "'$USER_AUTHORITY'"?>
            },
            custom: {
                query_filter: '<?=base64_encode($FILTER->query_filter)?>',
                query_all: '<?=base64_encode($FILTER->query_all)?>',
                field: JSON.parse('<?=json_encode($TABLE_FIELD)?>'),
                action: JSON.parse('<?=json_encode($TABLE_ACTION)?>'),
                search_field: JSON.parse('<?=json_encode($FILTER_SEARCH)?>'),
                order_column: '<?=$FILTER->query_order_col?>',
                order_direction: '<?=$FILTER->query_order_dir?>'
            }
        }

        var tablePage = <?=(isset($_GET['wi-page']) && !empty($_GET['wi-page'])) ? $_GET['wi-page'] : 0 ?>;
        var tableLength = <?=(isset($_GET['wi-length']) && !empty($_GET['wi-length'])) ? $_GET['wi-length'] : 10 ?>;
        var tableSearch = '<?=(isset($_GET['wi-search']) && !empty($_GET['wi-search'])) ? $_GET['wi-search'] : '' ?>';

        var tableOrderName = '<?=(isset($_GET['wi-order']) && !empty($_GET['wi-order'])) ? $_GET['wi-order'] : $FILTER->query_order_col ?>';
        var tableOrderDir = '<?=(isset($_GET['wi-order-dir']) && !empty($_GET['wi-order-dir'])) ? $_GET['wi-order-dir'] : $FILTER->query_order_dir ?>';

        window.addEventListener('load', (event) => {
                
            var wiTable = new DataTable('#wi-table', {
                serverSide: true,
                processing: true,
                scroller: true,
                lengthChange: true, // Creo io il lenght change #wi-search-input
                searching: true, // Creo io la search bar #wi-input-length
                ajax: {
                    url: pathApp+'/api/backend/list/table.php',
                    type: 'POST',
                    data: dataPost,
                    error: function (XMLHttpRequest) { ajaxRequestError(XMLHttpRequest); }
                },
                idSrc: 'id',
                columns: JSON.parse('<?=json_encode($TABLE_COLUMNS)?>'),
                lengthMenu: [10, 25, 50, 100],
                displayStart: tableLength * tablePage, // Pagina di partenza
                pageLength: tableLength, // Lunghezza pagina
                pagingType: "full_numbers", // Tipologia paginazione
                search: { search: tableSearch },
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.12.1/i18n/it-IT.json',
                    paginate: {
                        next: '<i class="bi bi-chevron-right"></i>',
                        previous: '<i class="bi bi-chevron-left"></i>',
                        first: '<i class="bi bi-chevron-double-left"></i>',
                        last: '<i class="bi bi-chevron-double-right"></i>'
                    }
                },
                scrollX: true,
                scrollY: true,
                order: {
                    name: tableOrderName,
                    dir: tableOrderDir
                },
                createdRow: function (row, data, index) {
                    $(row).addClass('align-middle')
                },
                layout: {
                    topEnd: null,
                    topStart: null
                }
            })

            $('input#wi-table-search').keyup( function() { 

                wiTable.search(this.value).draw(); 

                var pageUrl = new URL(window.location.href);
                pageUrl.searchParams.set('wi-search', this.value);
                
                setListRedirect(pageUrl);
            
            });

            $('input#wi-table-search').val(tableSearch)

            $('select#wi-table-length').change( function() {
                
                wiTable.page.len(this.value).draw(); 

                var pageUrl = new URL(window.location.href);
                pageUrl.searchParams.set('wi-length', this.value);

                setListRedirect(pageUrl);
            
            });

            $('select#wi-table-length').val(tableLength)

            wiTable.on('draw', (event) => { 

                var page = wiTable.page.info();

                var nPage = page.page;

                var start = page.start + 1;
                var end = page.end;
                
                var recordsTotal = page.recordsTotal;
                var recordsDisplay = page.recordsDisplay;

                var result = 'da '+start+' a '+end+' di '+recordsDisplay;

                document.querySelector('figcaption span#wi-table-result').innerHTML = result;

                var pageUrl = new URL(window.location.href);
                pageUrl.searchParams.set('wi-page', nPage);

                var orderColumn = wiTable.order()[0][0];
                var orderDirection = wiTable.order()[0][1];
                
                setListRedirect(pageUrl);

                bootstrapTooltip();

            });

        });

    </script>

</body>
</html>
