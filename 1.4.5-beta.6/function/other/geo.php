<?php

    function country( $iso2 ) {
        
        $iso2 = strtoupper($iso2);

        if (!isset($_SESSION['system_cache']['geo']['country'])) { $_SESSION['system_cache']['geo']['country'] = []; }

        if (isset($_SESSION['system_cache']['geo']['country'][$iso2])) {

            return $_SESSION['system_cache']['geo']['country'][$iso2];

        } else {

            $API = json_decode(wiApi('/service/csc/country/', [
                'iso2' => $iso2
            ]), true);

            $COUNTRY = [];

            if ($API['success'] == true) { $COUNTRY = $API['response']; }

            $_SESSION['system_cache']['geo']['country'][$iso2] = $COUNTRY;

            return $COUNTRY;

        }

    }

    function countries() {

        global $PATH;

        if (isset($_SESSION['system_cache']['geo']['countries'])) {

            return $_SESSION['system_cache']['geo']['countries'];

        } else {

            $COUNTRIES = (!$JSON = @file_get_contents("$PATH->appResources/geo/countries.json")) ? [] : json_decode($JSON, TRUE);

            $_SESSION['system_cache']['geo']['countries'] = $COUNTRIES;

            return $COUNTRIES;

        }

    }


    function state( $countryIso2, $stateIso2 ) {

        $countryIso2 = strtoupper($countryIso2);
        $stateIso2 = strtoupper($stateIso2);

        if (!isset($_SESSION['system_cache']['geo']['country_states'])) { $_SESSION['system_cache']['geo']['country_states'] = []; }
        if (!isset($_SESSION['system_cache']['geo']['country_states'][$countryIso2])) { $_SESSION['system_cache']['geo']['country_states'][$countryIso2] = []; }

        if (isset($_SESSION['system_cache']['geo']['country_states'][$countryIso2][$stateIso2])) {

            return $_SESSION['system_cache']['geo']['country_states'][$countryIso2][$stateIso2];

        } else {

            $API = json_decode(wiApi('/service/csc/state/', [
                'country' => $countryIso2,
                'state' => $stateIso2
            ]), true);

            $STATE = [];
    
            if ($API['success'] == true) { $STATE = $API['response']; }

            $_SESSION['system_cache']['geo']['country_states'][$countryIso2][$stateIso2] = $STATE;

            return $STATE;

        }

    }

    function states( $countryIso2 ) {

        global $PATH;

        $countryIso2 = strtoupper($countryIso2);

        if (!isset($_SESSION['system_cache']['geo']['states'])) { $_SESSION['system_cache']['geo']['states'] = []; }

        if (isset($_SESSION['system_cache']['geo']['states'][$countryIso2])) {

            return $_SESSION['system_cache']['geo']['states'][$countryIso2];

        } else {

            $STATES = (!$JSON = @file_get_contents("$PATH->appResources/geo/states/$countryIso2.json")) ? [] : json_decode($JSON, TRUE);

            $_SESSION['system_cache']['geo']['states'][$countryIso2] = $STATES;

            return $STATES;

        }

    }

    function countryPhonePrefix( $iso2 ) {

        global $PATH;

        $iso2 = strtoupper($iso2);

        if (!isset($_SESSION['system_cache']['geo']['countries_phone_prefix'])) { $_SESSION['system_cache']['geo']['countries_phone_prefix'] = []; }

        if (isset($_SESSION['system_cache']['geo']['countries_phone_prefix'][$iso2])) {

            return $_SESSION['system_cache']['geo']['countries_phone_prefix'][$iso2];

        } else {

            $COUNTRY_PHONE_PREFIX = (!$JSON = @file_get_contents("$PATH->appResources/geo/countriesPhonePrefix.json")) ? [] : json_decode($JSON, TRUE);

            $phoneCode = "";
    
            if (isset($COUNTRY_PHONE_PREFIX [$iso2])) {

                $phoneCode = $COUNTRY_PHONE_PREFIX [$iso2];
    
            }

            $_SESSION['system_cache']['geo']['countries_phone_prefix'][$iso2] = $phoneCode;

            return $phoneCode;

        }

    }


    function countriesPhonePrefix() {

        global $PATH;

        if (isset($_SESSION['system_cache']['geo']['countries_phone_prefix'])) {

            return $_SESSION['system_cache']['geo']['countries_phone_prefix'];

        } else {

            $COUNTRY_PHONE_PREFIX = (!$JSON = @file_get_contents("$PATH->appResources/geo/countriesPhonePrefix.json")) ? [] : json_decode($JSON, TRUE);
            
            $_SESSION['system_cache']['geo']['countries_phone_prefix'] = $COUNTRY_PHONE_PREFIX;

            return $COUNTRY_PHONE_PREFIX;

        }

    }

    function phonePrefix() {

        global $PATH;

        if (isset($_SESSION['system_cache']['geo']['phone_prefix'])) {

            return $_SESSION['system_cache']['geo']['phone_prefix'];

        } else {

            $PHONE_PREFIX = (!$JSON = @file_get_contents("$PATH->appResources/geo/phonePrefix.json")) ? [] : json_decode($JSON, TRUE);

            $_SESSION['system_cache']['geo']['phone_prefix'] = $PHONE_PREFIX;

            return $PHONE_PREFIX;

        }

    }


    // Vecchie funzioni 
        function geoContinent($KEY = "iso2", $VALUE = "name") {
        
            $JSON = file_get_contents("https://www.wonderimage.it/shared/ecommerce/v1.1/api/geo/continent.json");
            $CONTINENT = json_decode($JSON, TRUE);

            $RETURN = [];

            foreach ($CONTINENT as $key => $value) {
                $RETURN[$value[$KEY]] = $value[$VALUE];
            }

            return $RETURN;

        }

        function geoInfoContinent($VALUE, $KEY = 'iso2') {

            $JSON = file_get_contents("https://www.wonderimage.it/shared/ecommerce/v1.1/api/geo/continent.json");
            $CONTINENT = json_decode($JSON, TRUE);

            foreach ($CONTINENT as $key => $value) {
                if ($value[$KEY] == $VALUE) {
                    $RETURN = $value;
                }
            }

            return $RETURN;

        }

        function geoCountry($continent, $KEY = "iso2", $VALUE = "country-name") {

            $RETURN = [];

            if (is_array($continent)) {
                foreach ($continent as $value) {

                    $JSON = file_get_contents("https://www.wonderimage.it/shared/ecommerce/v1.1/api/geo/country/$value.json");
                    $COUNTRY = json_decode($JSON, TRUE);

                    foreach ($COUNTRY as $key => $value) {
                        $RETURN[$KEY] = $value[$VALUE];
                    }

                }

            }else{

                $JSON = file_get_contents("https://www.wonderimage.it/shared/ecommerce/v1.1/api/geo/country/$continent.json");
                $COUNTRY = json_decode($JSON, TRUE);

                foreach ($COUNTRY as $key => $value) {
                    $RETURN[$value[$KEY]] = $value[$VALUE];
                }

            }

            return $RETURN;

        }

        function geoInfoCountry($VALUE, $KEY = 'iso2') {

            $CONTINENT = geoContinent();

            foreach ($CONTINENT as $key => $value) {

                $JSON = file_get_contents("https://www.wonderimage.it/shared/ecommerce/v1.1/api/geo/country/$key.json");
                $COUNTRY = json_decode($JSON, TRUE);

                foreach ($COUNTRY as $k => $v) {
                    if ($v[$KEY] == $VALUE) {
                        $RETURN = $v;
                    }
                }

            }

            return $RETURN;

        }

        function geoProvince($country, $KEY = "sigla", $VALUE = "nome") {

            $RETURN = [];

            $JSON = file_get_contents("https://www.wonderimage.it/shared/ecommerce/v1.1/api/geo/province/$country.json");
            $PROVINCE = json_decode($JSON, TRUE);

            foreach ($PROVINCE as $key => $value) {
                $RETURN[$value[$KEY]] = $value[$VALUE];
            }

            return $RETURN;

        }

    // 