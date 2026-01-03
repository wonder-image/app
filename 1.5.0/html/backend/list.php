<?php

    if (!isset($NAME->database) || empty($NAME->database)) { 
        $NAME->database = 'main';
    } else {
        $mysqli = $MYSQLI_CONNECTION[$NAME->database];
    }

    if (!isset($PAGE_TABLE)) {
        $table = strtoupper($NAME->table);
        $PAGE_TABLE = $TABLE->$table;
    }

    if ((isset($USER_FILTER->authority) && !empty($USER_FILTER->authority)) || 
        (isset($USER_FILTER->area) && !empty($USER_FILTER->area))) {
            
        $QUERY_CUSTOM = isset($QUERY_CUSTOM) ? $QUERY_CUSTOM.' AND ' : '';

        if (!empty($USER_FILTER->authority)) {
            if (is_array($USER_FILTER->authority)) {

                $QUERY_CUSTOM .= '(';
                foreach ($USER_FILTER->authority as $k => $v) { $QUERY_CUSTOM .= "`authority` LIKE '%\"$v\"%' OR "; }
                $QUERY_CUSTOM = substr($QUERY_CUSTOM, 0, -4).')';

            } else {

                $QUERY_CUSTOM .= "`authority` LIKE '%\"$USER_FILTER->authority\"%'";

            }
        }

        if (!empty($USER_FILTER->authority) && !empty($USER_FILTER->area)) {
            $QUERY_CUSTOM .= " AND ";
        }

        if (!empty($USER_FILTER->area)) {
            if (is_array($USER_FILTER->area)) {

                $QUERY_CUSTOM .= '(';
                foreach ($USER_FILTER->area as $k => $v) { $QUERY_CUSTOM .= "`area` LIKE '%\"$v\"%' OR "; }
                $QUERY_CUSTOM = substr($QUERY_CUSTOM, 0, -4).')';

            } else {

                $QUERY_CUSTOM .= "`area` LIKE '%\"$USER_FILTER->area\"%'";
                
            }
        }
        
    }

    use Wonder\Backend\Table\Table;
    $TABLE = new Table( $NAME->table, $mysqli );

    $TABLE->endpoint( $API->DataTables );

    if (isset($USER_FILTER->area)) { $TABLE->addEndpointValue('user_area', $USER_FILTER->area); }
    if (isset($USER_FILTER->authority)) { $TABLE->addEndpointValue('user_authority', $USER_FILTER->authority); }

    $currentDir = str_replace('backend/', '', $PAGE->dir);

    $TABLE->addLink( 'view', $PATH->backend.'/'.$currentDir.'/view.php?redirect={redirectBase64}&id={rowId}' );
    $TABLE->addLink( 'modify', $PATH->backend.'/'.$currentDir.'/?redirect={redirectBase64}&modify={rowId}' );
    $TABLE->addLink( 'duplicate', $PATH->backend.'/'.$currentDir.'/?redirect={redirectBase64}&duplicate={rowId}' );
    $TABLE->addLink( 'download', $PATH->backend.'/'.$currentDir.'/download.php?id={rowId}' );
    $TABLE->addLink( 'file', $PATH->upload.'/'.$NAME->folder );
    
    $QUERY_CUSTOM = empty($QUERY_CUSTOM) ? "`deleted` = 'false'" : $QUERY_CUSTOM." AND `deleted` = 'false'";
    $TABLE->query($QUERY_CUSTOM);

    $COLUMN_DEF = "creation";
    $DIRECTION_DEF = "DESC";

    $ARROW_POSITION = sqlColumnExists($NAME->table, 'position');
    
    if (isset($FILTER_ORDER) && !empty($FILTER_ORDER)) {

        $COLUMN_DEF = $FILTER_ORDER;

        if (isset($FILTER_DIRECTION) && !empty($FILTER_DIRECTION)) {
            $DIRECTION_DEF = $FILTER_DIRECTION;
        } else {
            $DIRECTION_DEF = "ASC";
        }

    }

    if ($ARROW_POSITION) {

        $COLUMN = "position";
        $DIRECTION = "ASC";

    } else {

        $COLUMN = $COLUMN_DEF;
        $DIRECTION = $DIRECTION_DEF;

    }

    $TABLE->queryOrder($COLUMN, $DIRECTION, $COLUMN_DEF, $DIRECTION_DEF);

    $TABLE->text(
        $TEXT->titleS,
        $TEXT->titleP,
        $TEXT->last,
        $TEXT->all,
        $TEXT->article,
        $TEXT->full,
        $TEXT->empty,
        $TEXT->this
    );

    $TABLE->title(true);
    $TABLE->titleNResult(true);

    if (isset($BUTTON_ADD) && $BUTTON_ADD) {
        $TABLE->buttonAdd(
            $PATH->backend.'/'.$currentDir.'/?redirect='.$PAGE->uriBase64,
            'Aggiungi '.$TEXT->titleS
        );
    }

    if (isset($BUTTON_CUSTOM) && $BUTTON_CUSTOM) {
        foreach ($BUTTON_CUSTOM as $key => $button) {
            $TABLE->addButtonCustom( $button['value'], isset($button['html']) ? $button['html'] : false, isset($button['action']) ? $button['action'] : '', isset($button['color']) ? $button['color'] : 'dark' );
        }
    }

    if (isset($BUTTON_DOWNLOAD) && $BUTTON_DOWNLOAD) {
        $TABLE->buttonDownload(true);
    }

    if (isset($FILTER_SEARCH) && !empty($FILTER_SEARCH)) { $TABLE->filterSearch(true, $FILTER_SEARCH); }

    $TABLE->filterLimit(true);

    if (isset($FILTER_TYPE) && $FILTER_TYPE == 'date') { 
        $TABLE->filterDate(true, isset($HOW_MANY_DAYS) ? $HOW_MANY_DAYS : 30, isset($FILTER_COLUMN) ? $FILTER_COLUMN : 'creation'); 
    }

    # Filtri custom
        if (isset($FILTER_CUSTOM) && !empty($FILTER_CUSTOM)) { 
            
            foreach ($FILTER_CUSTOM as $column => $options) {

                # Creo gli array del valore del filtro

                    $type = isset($options['type']) ? $options['type'] : 'select';
                    $search = isset($options['search']) ? $options['search'] : false;
                    $columnType = isset($options['column_type']) ? $options['column_type'] : null;
                    
                    if ( isset($options['array']) && !empty($options['array']) ) {

                        $array = $options['array'];

                    } else {

                        $array = ($type == 'radio' || $type == 'select') ? [ '' => "Tutti" ] : [];

                        if ( isset($options['function']) && !empty($options['function']) ) {

                            $array = array_merge($array, call_user_func($options['function']));
            
                        } else if ( isset($options['database']) && !empty($options['database']) ) {
                            
                            $SQL = sqlSelect( $NAME->table, [ 'deleted' => 'false' ], null, 'name', 'ASC' );
            
                            foreach ($SQL->row as $key => $row) {
                                
                                $f = $row['id'];
                                $v = $row['name'];
            
                                $array[$f] = $v;
            
                            }
            
                        } else if ($column == 'visible') {

                            $array['true'] = 'Visibile';
                            $array['false'] = 'Nascosto';

                        }  else if ($column == 'active') {

                            $array['true'] = 'Abilitati';
                            $array['false'] = 'Disabilitati';

                        } elseif ($column == 'evidence') {

                            $array['true'] = 'Si';
                            $array['false'] = 'No';
                        
                        } else {

                            $array = [];

                        }

                    }

                #

                $TABLE->addFilter( $options['name'], $column, $array, $type, $search, $columnType );

            }

        }

    #

    # Colonne
        if ($ARROW_POSITION) {

            $TABLE->addColumn( '', 'position-up', false, '', 'mobile', 'little' );
            $TABLE->addColumn( '', 'position-down', false, '', 'mobile', 'little' );

        }

        foreach ($TABLE_FIELD as $column => $value) {

            $class = "";

            $label = (isset($value['label']) && !empty($value['label'])) ? $value['label'] : '';
            $format = isset($value['format']) ? $value['format'] : '';
            $orderable = isset($value['orderable']) ? $value['orderable'] : false;
            $phone = isset($value['phone']) ? $value['phone'] : true;
            $tablet = isset($value['tablet']) ? $value['tablet'] : true;
            $pc = isset($value['pc']) ? $value['pc'] : true;

            $dimension = !empty($value['dimension']) ? $value['dimension'] : '';

            if (empty($dimension)) {
                if ($column == 'authority' || $column == 'active' || $column == 'visible' || $column == 'empty' || $column == 'evidence' || $format == 'image') {
                    $dimension = 'little';
                }
            }

            $hiddenDevice = "";
            
            if (!$phone) { $hiddenDevice = 'mobile'; }
            if (!$tablet) { $hiddenDevice = 'tablet'; }
            if (!$pc) { $hiddenDevice = 'desktop'; }

            $TABLE->addColumn( $label, $column, $orderable, $class, $hiddenDevice, $dimension, $value );

        }

        if (!empty($TABLE_ACTION)) {

            $row = false;

            foreach ($TABLE_ACTION as $action => $link) {
                if (is_array($link) || $link == true) {
                    $row = true;
                    break;
                }
            }
            
            if ($row) { $TABLE->addColumn( '', 'menu', false, '', '', 'little', $TABLE_ACTION ); }

        }

    #

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

    <div class="row">
        <?=$TABLE->generate()?>
    </div>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>