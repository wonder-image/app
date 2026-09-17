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

    function infoSociety(int|string|null $location = null) {

        global $PATH;

        $LOCATION = $location === null || $location === ''
            ? \Wonder\App\Support\SocietyLocations::default()
            : (\Wonder\App\Support\SocietyLocations::find($location) ?? \Wonder\App\Support\SocietyLocations::default());

        $RETURN = (object) array();

        foreach (get_object_vars($LOCATION) as $column => $value) {
            if (is_scalar($value) || $value === null) { $RETURN->$column = $value ?? ''; }
        }

        $RETURN->social = [];

        foreach (\Wonder\App\Support\SocietyLocationResolver::LINK_FIELDS as $column) {
            if ($column !== 'site' && !empty($RETURN->$column ?? '')) {
                $RETURN->social[$column] = $RETURN->$column;
            }
        }

        foreach ([
            'street',
            'number',
            'cap',
            'city',
            'province',
            'country',
            'gmaps',
            'legal_street',
            'legal_number',
            'legal_cap',
            'legal_city',
            'legal_province',
            'legal_country',
            'name',
            'legal_name',
            'email',
            'site',
            'pi',
            'cf',
        ] as $field) {
            if (!isset($RETURN->$field)) {
                $RETURN->$field = '';
            }
        }

        $RETURN->domain = empty($RETURN->site) ? '' : (parse_url($RETURN->site, PHP_URL_HOST) ?? '');

        $address = prettyAddress($RETURN->street, $RETURN->number, $RETURN->cap, $RETURN->city, $RETURN->province, $RETURN->country);
        $RETURN->address = "$RETURN->street $RETURN->number, $RETURN->cap $RETURN->city ($RETURN->province)";
        $RETURN->prettyAddress = $address->pretty;
        $RETURN->prettyAddressPDF = $address->prettyPDF;

        if (empty($RETURN->gmaps) && !empty($RETURN->google_place_id)) {
            $RETURN->gmaps = \Wonder\App\Support\GoogleMapsLink::forPlace((string) $RETURN->google_place_id, $RETURN->address);
        }

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

        $RETURN->timetable = \Wonder\App\Support\OpeningHours::timetable((array) ($LOCATION->hours ?? []));

        $PRETTY_TIMEGROUP = prettyTimeTable($RETURN->timetable);

        $RETURN->timeGroup = $PRETTY_TIMEGROUP->timeGroup;
        $RETURN->prettyTime = $PRETTY_TIMEGROUP->prettyTime;
        $RETURN->prettyTimeGroup = $PRETTY_TIMEGROUP->prettyTimeGroup;

        $RETURN->location = (object) [
            'id' => (int) ($LOCATION->id ?? 0),
            'slug' => (string) ($LOCATION->slug ?? ''),
            'name' => (string) ($LOCATION->label ?? ''),
            'is_default' => (string) ($LOCATION->is_default ?? '') === 'true',
        ];
        $RETURN->google_place_id = (string) ($LOCATION->google_place_id ?? '');
        $RETURN->hours = (array) ($LOCATION->hours ?? []);
        $RETURN->specialHours = \Wonder\App\Support\OpeningHours::upcomingSpecial(
            (array) ($LOCATION->specialHours ?? []),
            new DateTimeImmutable('today')
        );
        $RETURN->businessStatus = (string) ($LOCATION->business_status ?? 'operational');

        $LOGOS = sqlSelect('logos', [ 'id' => '1'], 1)->row;

        $LOGO = [];

        foreach ((array) $LOGOS as $key => $value) {
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

    /** Tutte le sedi visibili, nel formato di `infoSociety()`. */
    function infoSocietyLocations(): array {

        $LOCATIONS = [];

        foreach (\Wonder\App\Support\SocietyLocations::all() as $location) {
            $LOCATIONS[] = infoSociety((int) $location->id);
        }

        return $LOCATIONS;

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
