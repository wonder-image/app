<?php

    function infoPage() {

        $PAGE = (object) array();

        $PAGE->root = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';

        $PAGE->uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        if (isset($_SERVER['HTTP_HOST'])) {

            $URL = new \Wonder\Http\UrlParser();

            $PAGE->url = $URL->getUrl();
            $PAGE->path = $URL->getPath() ?? '';
            $PAGE->domain = $URL->getDomain() ?? '';
            $PAGE->query = $URL->getQuery() ?? '';
            $file = $URL->getFile();
            $PAGE->fileName = (!empty($file) && pathinfo($file, PATHINFO_EXTENSION) != "") ? $file : "";

        } else {

            $PAGE->url = "";
            $PAGE->path = '';
            $PAGE->domain = '';
            $PAGE->query = "";
            $PAGE->fileName = '';

        }

        $PAGE->base64 = base64_encode($PAGE->url);
        $PAGE->uriBase64 = base64_encode($PAGE->uri);

        if (isset($_GET['redirect'])) { 
            $PAGE->redirectBase64 = $_GET['redirect']; 
            $PAGE->redirect = base64_decode($PAGE->redirectBase64); 
        }

        $PAGE->dir = mb_substr(substr(str_replace($PAGE->fileName, '',$PAGE->path), 0, -1), 1);

        return $PAGE;

    }

    function infoSociety() {

        global $PATH;
        
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

        $LOGOS = sqlSelect('logos', [ 'id' => '1'], 1)->row;

        $LOGO = [];

        foreach ($LOGOS as $key => $value) {
            if (!empty($value) && !empty(json_decode($value)) && is_array(json_decode($value))) {
                $logo = json_decode($value)[0];
                $LOGO[$key] = $logo;
            }
        }

        $RETURN->logo = isset($LOGO['main']) ? $PATH->upload.'/logos/'.$LOGO['main'] : "";
        $RETURN->logoBlack = isset($LOGO['black']) ? $PATH->upload.'/logos/'.$LOGO['black'] : "";
        $RETURN->logoWhite = isset($LOGO['white']) ? $PATH->upload.'/logos/'.$LOGO['white'] : "";
        
        $RETURN->icon = isset($LOGO['icon']) ? $PATH->upload.'/logos/'.$LOGO['icon'] : "";
        $RETURN->iconBlack = isset($LOGO['icon_black']) ? $PATH->upload.'/logos/'.$LOGO['icon_black'] : "";
        $RETURN->iconWhite = isset($LOGO['icon_white']) ? $PATH->upload.'/logos/'.$LOGO['icon_white'] : "";

        $RETURN->favicon = isset($LOGO['favicon']) ? $PATH->site.'/'.$LOGO['favicon'] : "";
        $RETURN->appIcon = isset($LOGO['app_icon']) ? $PATH->upload.'/logos/'.$LOGO['app_icon'] : "";

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
        
        $RETURN = (object) [];
        $RETURN->exists = $SQL->exists;
        foreach ($SQL->row as $column => $value) { $RETURN->$column = normalizeDB($value); }
        
        return $RETURN;
        
    }
