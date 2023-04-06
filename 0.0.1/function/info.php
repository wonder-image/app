<?php

    function infoPage() {

        $PAGE = (object) array();

        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $url = "https://";  
        }else{
            $url = "http://";
        }
        
        $url.= $_SERVER['HTTP_HOST'];
        $url.= $_SERVER['REQUEST_URI'];

        $PAGE->root = $_SERVER['DOCUMENT_ROOT'];
        $PAGE->url = $url;
        $PAGE->base64 = base64_encode($url);
        $PAGE->uriBase64 = base64_encode($_SERVER['REQUEST_URI']);

        if (!empty($_GET['redirect'])) { 
            $PAGE->redirectBase64 = $_GET['redirect']; 
            $PAGE->redirect = base64_decode($PAGE->redirectBase64); 
        }

        return $PAGE;

    }

    function infoSociety() {
        
        $RETURN = (object) array();

        $TABLE = ["society", "society_address", "society_legal_address", "society_social"];

        foreach ($TABLE  as $key => $table) {
            
            $SQL = sqlSelect($table, ['id' => 1], 1);
            foreach ($SQL->row as $column => $value) { $RETURN->$column = isset($value) ? $value : ''; }

        }

        $RETURN->social = [];
        $SQL = sqlSelect("society_social", ['id' => 1], 1);
        foreach ($SQL->row as $column => $value) { 
            if ($column != 'id' && $column != 'deleted' && $column != 'last_modified' && $column != 'site' && $column != 'creation' && !empty($value)) {
                $RETURN->social[$column] = isset($value) ? $value : ''; 
            }
        }

        $RETURN->domain = empty($RETURN->site) ? '' : parse_url($RETURN->site)['host'];

        $RETURN->address = "$RETURN->street $RETURN->number, $RETURN->cap $RETURN->city ($RETURN->province)";
        $RETURN->prettyAddress = "$RETURN->street $RETURN->number, <br> $RETURN->city ($RETURN->province)";

        $RETURN->addressLegal = "$RETURN->legal_street $RETURN->legal_number, $RETURN->legal_cap $RETURN->legal_city ($RETURN->legal_province)";
        $RETURN->prettyLegalAddress = "$RETURN->legal_street $RETURN->legal_number, <br> $RETURN->legal_city ($RETURN->legal_province)";

        return $RETURN;

    }

    function infoSeo() {

        global $PATH;

        $SQL = sqlSelect('seo', ['id' => 1], 1);
        
        $RETURN = (object) array();
        foreach ($SQL->row as $column => $value) { $RETURN->$column = isset($value) ? $value : ''; }
        
        $RETURN->image = $PATH->logo;
        $RETURN->url = $PATH->site.$_SERVER['REQUEST_URI'];
        $RETURN->date = date('d/m/Y',strtotime("-1 days"));;

        return $RETURN;

    }


    function info($table, $column, $value) {

        $SQL = sqlSelect($table, [$column => $value], 1);
        
        $RETURN = (object) array();
        foreach ($SQL->row as $column => $value) { $RETURN->$column = isset($value) ? $value : ''; }
        
        return $RETURN;
        
    }

?>