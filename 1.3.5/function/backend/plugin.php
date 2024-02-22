<?php

    function returnBadge($text, $classIcon, $bootstrapColor) {

        $RETURN = (object) array();
        $RETURN->color = bootstrapColor($bootstrapColor);
        $RETURN->bootstrapColor = $bootstrapColor;
        $RETURN->text = $text;
        $RETURN->classIcon = $classIcon;

        $RETURN->icon = "<i class='$RETURN->classIcon'></i>";
        $RETURN->tooltip = "<i class='$RETURN->classIcon' data-bs-toggle='tooltip' data-bs-placement='top' title='$RETURN->text'></i>";
        $RETURN->badge = "<span class='badge bg-$RETURN->bootstrapColor'>".strtoupper($RETURN->text)."</span>";
        $RETURN->badgeIcon = "<span class='badge bg-$RETURN->bootstrapColor'>$RETURN->icon</span>";
        $RETURN->automaticResize = "<span class='phone-none badge bg-$RETURN->bootstrapColor'>".strtoupper($RETURN->text)."</span><span class='pc-none badge bg-$RETURN->bootstrapColor'>$RETURN->icon</span>";

        return $RETURN;

    }

    function returnButton($text, $action, $bootstrapColor = "", $line = false) {

        $RETURN = (object) array();
        $bootstrapColor = empty($bootstrapColor) ? "" : "text-".$bootstrapColor;
        $RETURN->action = $action;
        $RETURN->button = $line ? "<div class='dropdown-divider'></div>" : "";
        $RETURN->button .= "<a class='dropdown-item $bootstrapColor' $action role='button'>$text</a>";

        return $RETURN;

    }

    function createAddButton($title) {

        global $PATH;
        global $NAME;
        global $PAGE;
        
        return "
        <a href='$PATH->backend/$NAME->folder/?redirect=$PAGE->uriBase64' type='button' class='btn btn-dark btn-sm float-end'>
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

            $text = "Visibile";
            $textButton = "Nascondi";
            $classIcon = "bi bi-eye";
            $bootstrapColor = "success";

        } else {

            $text = "Nascosto";
            $textButton = "Mostra";
            $classIcon = "bi bi-eye-slash";
            $bootstrapColor = "danger";

        }

        $RETURN = (object) array_merge( (array) returnBadge($text, $classIcon, $bootstrapColor), (array) returnButton($textButton, $action));
    
        return $RETURN;

    }

    function active($active, $id) {

        global $NAME;
        global $PATH;

        $action = "onclick=\"ajaxRequest('$PATH->app/api/backend/active.php?table=$NAME->table&id=$id')\"";

        if ($active == 'true') {

            $text = "Abilitato";
            $textButton = "Disabilita";
            $classIcon = "bi bi-check-circle";
            $bootstrapColor = "success";

        } else {

            $text = "Disabilitato";
            $textButton = "Abilita";
            $classIcon = "bi bi-x-circle";
            $bootstrapColor = "danger";

        }
        
        $RETURN = (object) array_merge( (array) returnBadge($text, $classIcon, $bootstrapColor), (array) returnButton($textButton, $action));

        return $RETURN;

    }

    function delete($id) {

        global $NAME;
        global $TEXT;
        global $PATH;

        $action = "onclick=\"modal('Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS?' ,'$PATH->app/api/backend/delete.php?table=$NAME->table&id=$id')\"";

        $RETURN = returnButton("Elimina", $action, 'danger', true);

        return $RETURN;

    }

    function removeAuthorization($id, $authority, $area) {

        global $NAME;
        global $TEXT;
        global $PATH;

        $action = "onclick=\"modal('Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS?' ,'$PATH->app/api/backend/authority.php?table=$NAME->table&id=$id";
        
        if ($authority != '') { $action .= "&authority=$authority"; }
        if ($area != '') { $action .= "&area=$area"; }

        $action .= "')\"";

        $RETURN = returnButton("Elimina", $action, 'danger', true);

        return $RETURN;

    }

    function isEmpty($tables, $column, $id, $multiple = false) {

        global $TEXT;

        $ARRAY = [];

        foreach ($tables as $table => $filter) {

            $column = isset($filter['column']) ? $filter['column'] : $column;
            $multiple = isset($filter['multiple']) ? $filter['multiple'] : $multiple;

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