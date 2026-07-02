<?php

    /**
     * @deprecated Usare Wonder\Backend\Table\Badge\BooleanBadge.
     */
    function returnBadge($text, $classIcon, $bootstrapColor) {

        return \Wonder\Backend\Table\Badge\BooleanBadge::make(true)
            ->on((string) $text, (string) $classIcon, (string) $bootstrapColor)
            ->legacyObject();

    }

    /**
     * Contesto tabella per le action legacy: legge i global via LegacyGlobals
     * con fallback safe (niente warning se il contesto manca, es. nel flusso
     * Resource dove il render passa da Field con contesto iniettato).
     */
    function legacyTableContext(): ?object {

        $NAME = \Wonder\App\LegacyGlobals::get('NAME');
        $PATH = \Wonder\App\LegacyGlobals::get('PATH');

        if (!is_object($NAME) || empty($NAME->table) || !is_object($PATH) || empty($PATH->api)) {
            return null;
        }

        return (object) [ 'table' => $NAME->table, 'api' => $PATH->api ];

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
        <a href='$PATH->backend/$NAME->folder/?redirect=$PAGE->uriBase64' type='button' class='btn btn-dark btn-sm'>
            <i class='bi bi-plus-lg'></i> Aggiungi $title
        </a>";

    }

    /**
     * @deprecated Usare TableColumn::visibleBadge() / BooleanBadge::visible().
     */
    function visible($visible, $id) {

        $ctx = legacyTableContext();
        $action = $ctx === null ? '' : "onclick=\"ajaxRequest('{$ctx->api}/backend/visible/?table={$ctx->table}&id=$id')\"";

        return \Wonder\Backend\Table\Badge\BooleanBadge::visible($visible)->action($action)->legacyObject();

    }

    /**
     * @deprecated Usare TableColumn::activeBadge() / BooleanBadge::active().
     */
    function active($active, $id) {

        $ctx = legacyTableContext();
        $action = $ctx === null ? '' : "onclick=\"ajaxRequest('{$ctx->api}/backend/active/?table={$ctx->table}&id=$id')\"";

        return \Wonder\Backend\Table\Badge\BooleanBadge::active($active)->action($action)->legacyObject();

    }

    /**
     * @deprecated Usare TableColumn::evidenceBadge() / BooleanBadge::evidence().
     */
    function evidence($evidence, $id) {

        $ctx = legacyTableContext();
        $action = $ctx === null ? '' : "onclick=\"ajaxRequest('{$ctx->api}/backend/change/boolean/?table={$ctx->table}&column=evidence&id=$id')\"";

        return \Wonder\Backend\Table\Badge\BooleanBadge::evidence($evidence)->action($action)->legacyObject();

    }

    function delete($id) {

        global $NAME;
        global $TEXT;
        global $PATH;

        $action = "onclick=\"modal('Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS?' ,'$PATH->api/backend/delete/?table=$NAME->table&id=$id')\"";

        $RETURN = returnButton("Elimina", $action, 'danger', true);

        return $RETURN;

    }

    function removeAuthorization($id, $authority, $area) {

        global $NAME;
        global $TEXT;
        global $PATH;

        $action = "onclick=\"modal('Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS?' ,'$PATH->api/backend/authority/?table=$NAME->table&id=$id";
        
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

            $table = is_array($filter) ? $table : $filter;
            $filter = is_array($filter) ? $filter : [];

            $column = $filter['column'] ?? $column;

            $FILTER_SQL = (sqlColumnExists($table, 'deleted')) ? " `deleted` = 'false' " : "";

            foreach ($filter as $col => $value) { 

                if (sqlColumnExists($table, $col)) {
                    if (empty($FILTER_SQL)) {
                        $and = "";
                    } else {
                        $and = "AND";
                    }

                    $FILTER_SQL .= "$and `$col` = '$value' ";

                }
            }

            $sql = sqlSelect($table, $FILTER_SQL);

            if ($sql->exists) {

                foreach ($sql->row as $key => $row) {

                    $value = $row[$column];

                    if (!empty($value)) {

                        $values = json_decode($value, true);

                        if (is_array($values)) {
                            foreach ($values as $key => $v) {
                                if (!in_array($v, $ARRAY)) {
                                    array_push($ARRAY, $v);
                                }
                            }
                        } else {
                        
                            $v = $value;
    
                            if (!in_array($v, $ARRAY)) {
                                array_push($ARRAY, $v);
                            }
    
                        }

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
