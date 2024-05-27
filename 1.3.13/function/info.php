<?php

    function infoPage() {

        $PAGE = (object) array();

        if (isset($_SERVER['HTTP_HOST'])) {

            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                $url = "https://";  
            } else {
                $url = "http://";
            }
            
            $url .= $_SERVER['HTTP_HOST'];
            $url .= $_SERVER['REQUEST_URI'];
    
        } else {

            $url = "";

        }

        $PAGE->root = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';

        $PAGE->url = $url;
        $PAGE->uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $PAGE->path = empty($PAGE->url) ? '' : parse_url($url)['path'];
        $PAGE->domain = empty($PAGE->url) ? '' : str_replace("www.", "", parse_url($url)['host']);

        if (!empty($PAGE->url) && isset(parse_url($PAGE->url)['query'])) {
            $PAGE->query = parse_url($PAGE->url)['query'];
        } else {
            $PAGE->query = "";
        }

        $PAGE->base64 = base64_encode($url);
        $PAGE->uriBase64 = base64_encode($PAGE->uri);

        if (isset($_GET['redirect'])) { 
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

        $address = prettyAddress($RETURN->street, $RETURN->number, $RETURN->cap, $RETURN->city, $RETURN->province, $RETURN->country);
        $RETURN->address = "$RETURN->street $RETURN->number, $RETURN->cap $RETURN->city ($RETURN->province)";
        $RETURN->prettyAddress = $address->pretty;
        $RETURN->prettyAddressPDF = $address->prettyPDF;

        $legalAddress = prettyAddress($RETURN->legal_street, $RETURN->legal_number, $RETURN->legal_cap, $RETURN->legal_city, $RETURN->legal_province, $RETURN->legal_country);
        $RETURN->addressLegal = "$RETURN->legal_street $RETURN->legal_number, $RETURN->legal_cap $RETURN->legal_city ($RETURN->legal_province)";
        $RETURN->prettyLegalAddress = $legalAddress->pretty;
        $RETURN->prettyLegalAddressPDF = $legalAddress->prettyPDF;

        $RETURN->prettyLegal = "";

        if (!empty($RETURN->legal_name)) { $RETURN->prettyLegal .= $RETURN->legal_name; }

        if (!empty($RETURN->pi) || !empty($RETURN->cf)) {
            if ($RETURN->pi == $RETURN->cf) {
                $RETURN->prettyLegal .= ' - P.Iva e C.Fiscale '.$RETURN->pi;
            } else {
                if (!empty($RETURN->pi)) { $RETURN->prettyLegal .= ' - P.Iva '.$RETURN->pi; } 
                if (!empty($RETURN->cf)) { $RETURN->prettyLegal .= ' - C.Fiscale '.$RETURN->cf; }
            }
        }

        $RETURN->timetable = empty($RETURN->timetable) ? [] : json_decode($RETURN->timetable, true);

        $PRETTY_TIMEGROUP = prettyTimeTable($RETURN->timetable);

        $RETURN->timeGroup = $PRETTY_TIMEGROUP->timeGroup;
        $RETURN->prettyTime = $PRETTY_TIMEGROUP->prettyTime;
        $RETURN->prettyTimeGroup = $PRETTY_TIMEGROUP->prettyTimeGroup;

        return $RETURN;

    }

    function infoSeo() {

        global $PATH;

        $SQL = sqlSelect('seo', ['id' => 1], 1);
        
        $RETURN = (object) array();
        foreach ($SQL->row as $column => $value) { $RETURN->$column = isset($value) ? $value : ''; }
        
        $RETURN->image = $PATH->logo;
        $RETURN->uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';;
        $RETURN->url = $PATH->site.$RETURN->uri;
        $RETURN->date = date('d/m/Y',strtotime("-1 days"));

        return $RETURN;

    }

    function info($table, $column, $value) {

        $SQL = sqlSelect($table, [$column => $value], 1);
        
        $RETURN = (object) array();
        $RETURN->exists = $SQL->exists;
        foreach ($SQL->row as $column => $value) { $RETURN->$column = isset($value) ? sanitizeEcho($value) : ''; }
        
        return $RETURN;
        
    }