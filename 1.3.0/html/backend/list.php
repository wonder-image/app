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

    if ($FILTER_TYPE == 'limit') {
        $FILTER = filterLimit();
    } elseif ($FILTER_TYPE == 'date') {
        $FILTER = filterDate();
    }

    if (!isset($PAGE_TABLE)) {
        $table = strtoupper($NAME->table);
        $PAGE_TABLE = $TABLE->$table;
    }

    if ($BUTTON_ADD) { $BUTTON_ADD = createAddButton($TEXT->titleS); }
    if (!empty($FILTER_SEARCH)) { $SEARCH = createSearchBar(); }
    if (!empty($FILTER_CUSTOM)) { $CUSTOM = createFilterCustom(); }

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
            <div class="col-12">
                <h3><?=$FILTER->title?></h3>
                <figcaption class="text-muted">
                    <?="Risultati: $FILTER->selected_lines/$FILTER->lines"?>
                </figcaption>
            </div>
            <div class="col-12">
                <div class="container" style="max-width: 100%;">
                    <div class="row row-cols-auto gap-2">
                        <?=$FILTER->html?>
                    </div>
                </div>
            </div>
        </wi-card>

        <?php

            if (!empty($FILTER_SEARCH) || !empty($FILTER_CUSTOM) || !empty($BUTTON_ADD) || !empty($BUTTON_CUSTOM)) {
                
                echo "<wi-card class='col-12'>";

                if (!empty($FILTER_SEARCH)) {
                    echo "<div class='col-4'>$SEARCH</div>";
                }

                if (!empty($FILTER_CUSTOM) || !empty($BUTTON_ADD) || !empty($BUTTON_CUSTOM)) {

                    echo "<div class='col-8 d-flex gap-2 justify-content-end'>";
                    if (!empty($FILTER_CUSTOM)) { echo $CUSTOM->button; }
                    if (!empty($BUTTON_ADD)) { echo $BUTTON_ADD; }
                    if (!empty($BUTTON_CUSTOM)) {
                        foreach ($BUTTON_CUSTOM as $key => $v) {

                            $value = isset($v['value']) ? $v['value'] : '';
                            $action = isset($v['action']) ? $v['action'] : '';
                            $color = isset($v['color']) ? $v['color'] : 'dark';

                            echo "<a $action type='button' class='btn btn-$color btn-sm'>$value</a>";
                            
                        }
                        
                    }
                    echo "</div>";

                }

                if (!empty($FILTER_CUSTOM)) { 
                    echo $CUSTOM->html; 
                }

                echo "</wi-card>";

            }

        ?>

        <wi-card class="col-12">
            <div class="col-12">

                <table class="table table-hover">
                    <thead>
                        <tr>
                            <?php

                                if ($FILTER->arrow && $FILTER->selected_lines > 1) {
                                    echo "<th scope='col' class='phone-none little'></th>";
                                    echo "<th scope='col' class='phone-none little'></th>";
                                }

                                foreach ($TABLE_FIELD as $column => $value) {

                                    $class = "";

                                    $label = !empty($value['label']) ? $value['label'] : '';
                                    $phone = isset($value['phone']) ? $value['phone'] : true;
                                    $tablet = isset($value['tablet']) ? $value['tablet'] : true;
                                    $pc = isset($value['pc']) ? $value['pc'] : true;

                                    $dimension = !empty($value['dimension']) ? $value['dimension'] : '';

                                    if (empty($dimension)) {
                                        if ($column == 'authority' || $column == 'active' || $column == 'visible' || $column == 'empty') {
                                            $dimension = 'little ';
                                        }
                                    }

                                    $class .= $dimension;

                                    if (!$phone) { $class .= ' phone-none'; }
                                    if (!$tablet) { $class .= ' tablet-none'; }
                                    if (!$pc) { $class .= ' pc-none'; }

                                    echo "<th scope='col' class='$class'>$label</th>";

                                }

                                if (!empty($TABLE_ACTION)) {

                                    $row = false;

                                    foreach ($TABLE_ACTION as $action => $link) {
                                        if (is_array($link) || $link == true) {
                                            $row = true;
                                        }
                                    }
                                    
                                    if ($row) { echo "<th scope='col' class='little'></th>"; }

                                }

                            ?>
                        </tr>
                    </thead>
                    <tbody class="table-group-divider">
                        <?php
                        
                            $SQL = sqlSelect($NAME->table, $FILTER->query);

                            $lineN = 1;

                            foreach ($SQL->row as $key => $row) {

                                $ROW_ID = $row['id'];

                                $LINK = (object) array();
                                $LINK->view = "$PATH->backend/$NAME->folder/view.php?redirect=$PAGE->uriBase64&id=$ROW_ID";
                                $LINK->modify = "$PATH->backend/$NAME->folder/?redirect=$PAGE->uriBase64&modify=$ROW_ID";
                                $LINK->download = "$PATH->backend/$NAME->folder/download.php?id=$ROW_ID";

                                $DELETE_BUTTON = true;

                                $searchJS = "";

                                if (!empty($FILTER_SEARCH)) {
                                    
                                    $searchJS = "class='search-here' data-keyword='";

                                    foreach ($FILTER_SEARCH as $key => $value) {
                                        $searchJS .= $row[$value].' '; 
                                    }

                                    $searchJS = substr($searchJS, 0, -1)."'";

                                }

                                echo "<tr id='line-$lineN' $searchJS >";


                                if ($FILTER->arrow && $FILTER->selected_lines > 1) {

                                    $onclickUp = "<a class='bi bi-chevron-up text-dark' onclick=\"ajaxRequest('$PATH->app/api/backend/move.php?table=$NAME->table&id=$ROW_ID&action=up')\" role='button'></a>";
                                    $onclickDown = "<a class='bi bi-chevron-down text-dark' onclick=\"ajaxRequest('$PATH->app/api/backend/move.php?table=$NAME->table&id=$ROW_ID&action=down')\" role='button'></a>";

                                    if ($lineN == 1) {
                                        echo "<td scope='col' class='phone-none little'></td>";
                                        echo "<td scope='col' class='phone-none little'>$onclickDown</td>";
                                    } elseif ($lineN == $FILTER->selected_lines) {
                                        echo "<td scope='col' class='phone-none little'>$onclickUp</td>";
                                        echo "<td scope='col' class='phone-none little'></td>";
                                    } else {
                                        echo "<td scope='col' class='phone-none little'>$onclickUp</td>";
                                        echo "<td scope='col' class='phone-none little'>$onclickDown</a></td>";
                                    }
                                }

                                foreach ($TABLE_FIELD as $column => $value) {

                                    $VALUE = "";
                                    $CLASS = "";

                                    # Set value

                                        $v = isset($value['value']) ? $value['value'] : '';
                                        
                                        if (!empty($v)) {

                                            if (is_array($v)) {

                                                $COLUMN_VALUE = "";

                                                foreach ($v as $key => $v) {
                                                    if (sqlColumnExists($NAME->table, $v)) {
                                                        $COLUMN_VALUE .= $row[$v].' ';
                                                    } else {
                                                        $COLUMN_VALUE .= $v.' ';
                                                    }
                                                }

                                                $COLUMN_VALUE = substr($COLUMN_VALUE, 0, -1);

                                            }else{

                                                $COLUMN_VALUE = $row[$v];

                                            }

                                        } else {

                                            $COLUMN_VALUE = empty($row[$column]) ? "" : $row[$column];

                                        }

                                        if (isset($value['function']) && !empty($value['function'])) {

                                            $functionName = $value['function']['name'];

                                            if ($functionName == "empty") {

                                                $FUNCTION = isEmpty($value['function']['tables'], $value['function']['column'], $row['id'], isset($value['function']['multiple']) ? $value['function']['multiple'] : false);
                                                $VALUE = $FUNCTION->icon;
                                                if (!$FUNCTION->return) { $DELETE_BUTTON = false; }

                                            } elseif ($functionName == "permissions" || $functionName == "permissionsBackend" || $functionName == "permissionsFrontend") {

                                                $COLUMN_VALUE = json_decode($COLUMN_VALUE, true);
                                                $functionReturn = $value['function']['return'];

                                                foreach ($COLUMN_VALUE as $key => $value) {
                                                    $v = call_user_func_array($functionName, [$value]);
                                                    if (is_object($v)) { $VALUE .= $v->$functionReturn; }
                                                }
                                                
                                            } else if ($functionName == "active" || $functionName == "visible") {

                                                $functionReturn = $value['function']['return'];

                                                if ($NAME->table == 'user') {
                                                    if (count(json_decode($row['area'], true)) <= 1 || count(json_decode($row['authority'], true)) <= 1) {
                                                        $VALUE = call_user_func_array($functionName, [$COLUMN_VALUE, $row['id']])->$functionReturn; 
                                                    }
                                                } else {
                                                    $VALUE = call_user_func_array($functionName, [$COLUMN_VALUE, $row['id']])->$functionReturn; 
                                                }

                                            } else {

                                                $functionReturn = $value['function']['return'];
                                                $VALUE = call_user_func_array($functionName, [$row['id']])->$functionReturn;

                                            }

                                        } else {

                                            $VALUE = $COLUMN_VALUE;
                                            
                                        }

                                    # Set format
                                        if (isset($value['format']) && !empty($value['format'])) {

                                            $format = $value['format'];

                                            if ($format == 'image') {
                                                $VALUE = "<img src='$VALUE' class='img-thumbnail object-fit-cover' style='max-width: calc(((61.5px - 1rem) / 2) * 3) !important;width: calc(((61.5px - 1rem) / 2) * 3) !important; height: calc(61.5px - 1rem) !important;'>";
                                            }

                                        }

                                    # Set link to value

                                        if (isset($value['href']) && !empty($value['href'])) {

                                            $href = $value['href'];

                                            if ($href == 'modify') {
                                                $href = $LINK->modify;
                                            } elseif ($href == 'view') {
                                                $href = $LINK->view;
                                            } elseif ($href == 'mailto') {
                                                $href = "mailto:$VALUE";
                                            } elseif ($href == 'tel') {
                                                $href = "tel:$VALUE";
                                                $VALUE = prettyPhone($VALUE);
                                            }

                                            $VALUE = "<a href='$href' class='fw-semibold text-dark'>".$VALUE."</a>";

                                        }

                                    # Column resize
                                    
                                        $phone = isset($value['phone']) ? $value['phone'] : true;
                                        $tablet = isset($value['tablet']) ? $value['tablet'] : true;
                                        $pc = isset($value['pc']) ? $value['pc'] : true;
                                            
                                        if (!$phone) { $CLASS .= 'phone-none '; }
                                        if (!$tablet) { $CLASS .= 'tablet-none '; }
                                        if (!$pc) { $CLASS .= 'pc-none '; }

                                    # Column size

                                        $dimension = !empty($value['dimension']) ? $value['dimension'] : '';

                                        if (empty($dimension)) {
                                            if ($column == 'authority' || $column == 'active' || $column == 'visible' || $column == 'empty') {
                                                $dimension = 'little';
                                            }
                                        }

                                        $CLASS .= $dimension;

                                    // 
                                    
                                    echo "<td scope='col' class='$CLASS align-middle'>$VALUE</td>";

                                }

                                if (!empty($TABLE_ACTION)) {

                                    $BUTTONS = "";
                                    
                                    foreach ($TABLE_ACTION as $ACTION => $link) {

                                        if ($link && !is_array($link)) {

                                            if ($ACTION == 'view') { $BUTTONS .= "<a class='dropdown-item' href='$LINK->view' role='button'>Visualizza</a>"; }
                                            elseif ($ACTION == 'modify') { $BUTTONS .= "<a class='dropdown-item' href='$LINK->modify' role='button'>Modifica</a>"; }
                                            elseif ($ACTION == 'download') { $BUTTONS .= "<a class='dropdown-item' href='$LINK->download'  target='_blank' rel='noopener noreferrer' role='button'>Scarica</a>"; }
                                            elseif ($ACTION == 'visible') { $BUTTONS .= visible($row['visible'], $row['id'])->button; }
                                            elseif ($ACTION == 'delete' && $DELETE_BUTTON) { $BUTTONS .= delete($row['id'])->button; }
                                            elseif ($ACTION == 'authority' && $DELETE_BUTTON && isset($USER_FILTER->authority) && isset($USER_FILTER->area) && !is_array($USER_FILTER->area) && !is_array($USER_FILTER->authority)) { $BUTTONS .= removeAuthorization($row['id'], $USER_FILTER->authority, $USER_FILTER->area)->button; }
                                            elseif ($ACTION == 'active') { 
                                                if ($NAME->table == 'user') {
                                                    if (count(json_decode($row['area'], true)) <= 1 || count(json_decode($row['authority'], true)) <= 1) {
                                                        $BUTTONS .= active($row['active'], $row['id'])->button; 
                                                    }
                                                } else {
                                                    $BUTTONS .= active($row['active'], $row['id'])->button; 
                                                }
                                            }

                                        } elseif (is_array($link)) {

                                            $label = $link['label'];
                                            $action = $link['action'];

                                            $BUTTONS .= "<a class='dropdown-item' $action role='button'>$label</a>";

                                        }

                                    }

                                    if (!empty($BUTTONS)) {
                                        echo "
                                        <td scope='col' class='little align-middle'>
                                            <div class='btn-group'>
                                                <span class='badge text-dark' type='button' data-bs-toggle='dropdown' aria-bs-haspopup='true' aria-bs-expanded='false'>
                                                    <i class='bi bi-three-dots'></i>
                                                </span>
                                                <div class='dropdown-menu dropdown-menu-right'>
                                                    $BUTTONS
                                                </div>
                                            </div>  
                                        </td>";
                                    }
                                    
                                }

                                echo "</tr>";

                                $lineN++;

                            }
                            

                        ?>
                    </tbody>
                </table>

            </div>
        </wi-card>
        

    </div>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>