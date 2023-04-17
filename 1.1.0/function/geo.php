<?php

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
    
?>