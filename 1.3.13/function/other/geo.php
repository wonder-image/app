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

        if (isset($_SESSION['system_cache']['geo']['countries'])) {

            return $_SESSION['system_cache']['geo']['countries'];

        } else {

            $API = json_decode(wiApi('/geo/countries/'), true);
    
            $COUNTRIES = [];

            if ($API['success'] == true) { $COUNTRIES = $API['response']; }

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

        $countryIso2 = strtoupper($countryIso2);

        if (!isset($_SESSION['system_cache']['geo']['states'])) { $_SESSION['system_cache']['geo']['states'] = []; }

        if (isset($_SESSION['system_cache']['geo']['states'][$countryIso2])) {

            return $_SESSION['system_cache']['geo']['states'][$countryIso2];

        } else {

            $API = json_decode(wiApi('/geo/states/', [
                'country' => $countryIso2
            ]), true);

            $STATES = [];

            if ($API['success'] == true) { $STATES = $API['response']; }

            $_SESSION['system_cache']['geo']['states'][$countryIso2] = $STATES;

            return $STATES;

        }

    }

    function countryPhonePrefix( $iso2 ) {

        $iso2 = strtoupper($iso2);

        if (!isset($_SESSION['system_cache']['geo']['country_phone_prefix'])) { $_SESSION['system_cache']['geo']['country_phone_prefix'] = []; }

        if (isset($_SESSION['system_cache']['geo']['country_phone_prefix'][$iso2])) {

            return $_SESSION['system_cache']['geo']['country_phone_prefix'][$iso2];

        } else {

            $API = json_decode(wiApi('/service/csc/country/', [
                'iso2' => $iso2
            ]), true);

            $phoneCode = "";
    
            if ($API['success'] == true) {
    
                $response = $API['response'];
                $phoneCode = $response['phonecode'];
                        
                if (strpos($phoneCode, 'and')) {
                    $array = explode(' and ', $phoneCode);
                    $phoneCode = $array[0];
                }
                
                $phoneCode = str_replace('+', '', $phoneCode);
                $phoneCode = '+'.$phoneCode;

            }

            $_SESSION['system_cache']['geo']['country_phone_prefix'][$iso2] = $phoneCode;

            return $phoneCode;

        }

    }

    function phonePrefix() {

        if (isset($_SESSION['system_cache']['geo']['phone_prefix'])) {

            return $_SESSION['system_cache']['geo']['phone_prefix'];

        } else {

            $API = json_decode(wiApi('/geo/phone_prefix/'), true);

            $PREFIX = [];

            if ($API['success'] == true) { $PREFIX = $API['response']; }

            $_SESSION['system_cache']['geo']['phone_prefix'] = $PREFIX;

            return $PREFIX;

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