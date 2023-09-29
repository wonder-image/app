<?php

    function createAddButton($title) {

        global $PATH;
        global $NAME;
        global $PAGE;
        
        return "
        <a href='$PATH->backend/$NAME->folder/?redirect=$PAGE->uriBase64' type='button' class='btn btn-success btn-sm'>
            <i class='bi bi-plus-lg'></i> Aggiungi $title
        </a>";

    }

    function visible($visible, $id) {

        global $NAME;
        global $PATH;

        $action = "onclick=\"ajaxRequest('$PATH->app/api/backend/visible.php?table=$NAME->table&id=$id')\"";

        $return = (object) array();
        $return->action = $action;

        if ($visible == 'true') {

            $name = "Visibile";
            $button = "Nascondi";
            $icon = "<i class='bi bi-eye'></i>";
            $bg = "bg-success";
            $tx = "tx-light";
            $color = "success";

        }else{

            $name = "Nascosto";
            $button = "Mostra";
            $icon = "<i class='bi bi-eye-slash'></i>";
            $bg = "bg-danger";
            $tx = "tx-light";
            $color = "danger";

        }

        $RETURN = (object) array();
        $RETURN->action = $action;
        $RETURN->icon = $icon;
        $RETURN->name = $name;
        $RETURN->bg = $bg;
        $RETURN->tx = $tx;
        $RETURN->color = $color;
        $RETURN->button = "<a class='dropdown-item' $RETURN->action role='button'>$button</a>";
        $RETURN->badge = "<a $RETURN->action role='button'class='badge $RETURN->bg $RETURN->tx'>$RETURN->name</a>";
        $RETURN->badgeIcon = "<a $RETURN->action role='button' class='badge $RETURN->bg $RETURN->tx'>$RETURN->icon</a>";

        $RETURN->automaticResize = "<a $RETURN->action role='button'class='phone-none badge $RETURN->bg $RETURN->tx text-decoration-none'>$RETURN->name</a><a $RETURN->action role='button' class='pc-none badge $RETURN->bg $RETURN->tx'>$RETURN->icon</a>";

        return $RETURN;

    }

    function active($active, $id) {

        global $NAME;
        global $PATH;

        $action = "onclick=\"ajaxRequest('$PATH->app/api/backend/active.php?table=$NAME->table&id=$id')\"";

        $return = (object) array();
        $return->action = $action;

        if ($active == 'true') {

            $name = "Abilitato";
            $button = "Disabilita";
            $icon = "<i class='bi bi-check-circle'></i>";
            $bg = "bg-success";
            $tx = "tx-light";
            $color = "success";

        }else{

            $name = "Disabilitato";
            $button = "Abilita";
            $icon = "<i class='bi bi-x-circle'></i>";
            $bg = "bg-danger";
            $tx = "tx-light";
            $color = "danger";

        }

        $RETURN = (object) array();
        $RETURN->action = $action;
        $RETURN->icon = $icon;
        $RETURN->name = $name;
        $RETURN->bg = $bg;
        $RETURN->tx = $tx;
        $RETURN->color = $color;

        $RETURN->button = "<a class='dropdown-item' $action role='button'>$button</a>";
        $RETURN->badge = "<a $RETURN->action role='button' class='badge text-bg-$RETURN->color text-decoration-none'>$RETURN->name</a>";
        $RETURN->badgeIcon = "<a $RETURN->action role='button' class='badge text-bg-$RETURN->color text-decoration-none'>$RETURN->icon</a>";

        $RETURN->automaticResize = "<a $RETURN->action role='button' class='phone-none badge text-bg-$RETURN->color text-decoration-none'>$RETURN->name</a><a $RETURN->action role='button' class='pc-none badge text-bg-$RETURN->color text-decoration-none'>$RETURN->icon</a>";

        return $RETURN;

    }

    function delete($id) {

        global $NAME;
        global $TEXT;
        global $PATH;

        $action = "onclick=\"modal('Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS?' ,'$PATH->app/api/backend/delete.php?table=$NAME->table&id=$id')\"";

        $return = (object) array();
        $return->action = $action;
        $return->button = "
        <div class='dropdown-divider'></div>
        <a class='dropdown-item text-danger' $action role='button'>Elimina</a>";

        return $return;

    }

    function removeAuthorization($id, $authority, $area) {

        global $NAME;
        global $TEXT;
        global $PATH;

        $action = "onclick=\"modal('Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS?' ,'$PATH->app/api/backend/authority.php?table=$NAME->table&id=$id";
        
        if ($authority != '') { $action .= "&authority=$authority"; }
        if ($area != '') { $action .= "&area=$area"; }

        $action .= "')\"";

        $return = (object) array();
        $return->action = $action;
        $return->button = "
        <div class='dropdown-divider'></div>
        <a class='dropdown-item text-danger' $action role='button'>Elimina</a>";

        return $return;

    }

    function isEmpty($tables, $column, $id, $multiple = false) {

        global $TEXT;

        $ARRAY = [];

        foreach ($tables as $table => $filter) {

            if (sqlColumnExists($table, 'deleted')) {
                $FILTER_SQL = " `deleted` = 'false' ";
            }else{
                $FILTER_SQL = "";
            }

            foreach ($filter as $col => $value) { 
                if (sqlColumnExists($table, $col)) {
                    if (empty($FILTER_SQL)) {
                        $and = "";
                    }else{
                        $and = "AND";
                    }
                    $FILTER_SQL .= "$and `$col` = '$value' ";
                }
            }

            $sql = sqlSelect($table, $FILTER_SQL);

            foreach ($sql->row as $key => $row) {

                $value = $row[$column];
                
                if ($multiple) {

                    if (!empty($value)) {
                        
                        $values = json_decode($value, true);

                        foreach ($values as $key => $v) {
                            if (!in_array($v, $ARRAY)) {
                                array_push($ARRAY, $v);
                            }
                        }

                    }
                    
                }else{

                    $v = $value;

                    if (!in_array($v, $ARRAY)) {
                        array_push($ARRAY, $v);
                    }

                }

            }
           
        }

        $return = (object) array();

        if (in_array($id, $ARRAY)) {
            $return->return = false;
            $return->icon = '<i class="bi bi-folder-fill text-muted"  data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="'.ucwords($TEXT->titleS).' '.$TEXT->full.'"></i>';
        }else{
            $return->return = true;
            $return->icon = '<i class="bi bi-folder text-muted"  data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="'.ucwords($TEXT->titleS).' '.$TEXT->empty.'"></i>';
        }

        $return->array = $ARRAY;

        return $return;

    }

?>