<?php

    function country( $iso2 ) {

        $API = json_decode(wiApi('/service/csc/country/', [
            'iso2' => $iso2
        ]), true);

        if ($API['success'] == true) {
            return $API['response'];
        } else {
            return [];
        }

    }

    function countries() {

        $API = json_decode(wiApi('/service/csc/countries/'), true);

        if ($API['success'] == true) {

            $COUNTRIES = [];
            foreach ($API['response'] as $key => $value) { 

                $name = empty($value['native']) ? $value['name'] : $value['native'];
                $COUNTRIES[$value['iso2']] = $name; 
            }

            # Ordine alfabetico
            asort($COUNTRIES);

            return $COUNTRIES;
            
        } else {
            return [];
        }

    }

    function state( $countryIso2, $stateIso2 ) {

        $API = json_decode(wiApi('/service/csc/state/', [
            'country' => $countryIso2,
            'state' => $stateIso2
        ]), true);

        if ($API['success'] == true) {
            return $API['response'];
        } else {
            return [];
        }

    }


    function states( $countryIso2 ) {

        $API = json_decode(wiApi('/service/csc/states/', [
            'country' => $countryIso2
        ]), true);

        if ($API['success'] == true) {

            $STATES = [];

            foreach ($API['response'] as $key => $value) { 

                if (is_numeric($value['iso2'])) {

                } else {
                    $STATES[$value['iso2']] = $value['name']; 
                }

            }

            # Ordine alfabetico
            asort($STATES);

            return $STATES;

        } else {
            return [];
        }

    }

    function countryPhonePrefix($iso2) {

        $API = json_decode(wiApi('/service/csc/country/', [
            'iso2' => $iso2
        ]), true);

        if ($API['success'] == true) {

            $response = $API['response'];
            $phoneCode = $response['phonecode'];
                    
            if (strpos($phoneCode, 'and')) {
                $array = explode(' and ', $phoneCode);
                $phoneCode = $array[0];
            }
            
            $phoneCode = str_replace('+', '', $phoneCode);
            $phoneCode = '+'.$phoneCode;

            return $phoneCode;

        } else {
            return "";
        }


    }

    function phonePrefix() {

        $API = json_decode(wiApi('/service/csc/countries/'), true);

        if ($API['success'] == true) {

            $PREFIX = [];
            foreach ($API['response'] as $key => $value) { 

                $array = explode(' and ', $value['phonecode']);

                foreach ($array as $phoneCode) {
                    
                    $phoneCode = str_replace('+', '', $phoneCode);
                    $phoneCode = '+'.$phoneCode;

                    $PREFIX[$phoneCode] = $phoneCode; 

                }

            }

            # Ordine alfabetico
            asort($PREFIX);

            return $PREFIX;
            
        } else {
            return [];
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