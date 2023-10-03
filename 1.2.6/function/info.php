<?php

    function infoPage() {

        global $PATH;

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
        $PAGE->path = empty($url) ? '' : parse_url($url)['path'];
        $PAGE->domain = empty($url) ? '' : parse_url($url)['host'];

        $PAGE->base64 = base64_encode($url);
        $PAGE->uriBase64 = base64_encode(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');

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

        $RETURN->address = "$RETURN->street $RETURN->number, $RETURN->cap $RETURN->city ($RETURN->province)";
        $RETURN->prettyAddress = "$RETURN->street $RETURN->number, <br> $RETURN->city ($RETURN->province)";

        $RETURN->addressLegal = "$RETURN->legal_street $RETURN->legal_number, $RETURN->legal_cap $RETURN->legal_city ($RETURN->legal_province)";
        $RETURN->prettyLegalAddress = "$RETURN->legal_street $RETURN->legal_number, <br> $RETURN->legal_city ($RETURN->legal_province)";

        $RETURN->prettyLegal = "";

        if (!empty($RETURN->legal_name)) { $RETURN->prettyLegal .= $RETURN->legal_name; }

        if (!empty($RETURN->pi) && !empty($RETURN->cf)) {
            $RETURN->prettyLegal .= ' - P.Iva e C.Fiscale '.$RETURN->pi;
        } elseif (!empty($RETURN->pi) || !empty($RETURN->cf)) {
            if (!empty($RETURN->pi)) { $RETURN->prettyLegal .= ' - P.Iva '.$RETURN->pi; } 
            elseif (!empty($RETURN->cf)) { $RETURN->prettyLegal .= ' - C.Fiscale '.$RETURN->cf; }
        }

        $RETURN->timetable = empty($RETURN->timetable) ? [] : json_decode($RETURN->timetable, true);

        $RETURN->timeGroup = [];
        
        foreach ($RETURN->timetable as $day => $value) {

            $X = "";
            
            foreach ($value as $key => $hour) { $X .= $hour['from'].'=>'.$hour['to'].'/'; }

            $X = substr($X, 0, -1);
            
            if (!array_key_exists($X, $RETURN->timeGroup)) { $RETURN->timeGroup[$X] = []; }
            
            array_push($RETURN->timeGroup[$X], $day);

        }

        $RETURN->prettyTime = "";
        
        foreach ($RETURN->timetable as $day => $value) {

            $RETURN->prettyTime .= "<b>".translateDate($day, 'day').":</b> ";

            foreach ($value as $key => $value) {
                $RETURN->prettyTime .= "dalle ".$value['from']." ";
                $RETURN->prettyTime .= "alle ".$value['to']."<br>";
            }

        }

        $RETURN->prettyTimeGroup = "";
        
        foreach ($RETURN->timeGroup as $hour => $day) {

            foreach ($day as $key => $d) {
                $RETURN->prettyTimeGroup .= '<b>'.substr(translateDate($d, 'day'), 0, 3).'</b>, ';
            }

            $RETURN->prettyTimeGroup = substr($RETURN->prettyTimeGroup, 0, -2).': ';

            $h = explode('/', $hour);

            foreach ($h as $k => $v) {
                $v = explode('=>', $v);
                $RETURN->prettyTimeGroup .= 'dalle '.$v[0].' alle '.$v[1].' | '; 
            }

            $RETURN->prettyTimeGroup = substr($RETURN->prettyTimeGroup, 0, -3).'<br>';

        }

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
        foreach ($SQL->row as $column => $value) { $RETURN->$column = isset($value) ? sanitizeEcho($value) : ''; }
        
        return $RETURN;
        
    }

?>