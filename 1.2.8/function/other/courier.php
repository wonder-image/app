<?php

    function courier() {

        $JSON = file_get_contents("https://www.wonderimage.it/shared/ecommerce/v1.1/api/shipping/courier.php");
        $COURIER = json_decode($JSON, TRUE);

        return $COURIER;

    }

    function infoCourier($courier) {

        $JSON = file_get_contents("https://www.wonderimage.it/shared/ecommerce/v1.1/api/shipping/courier.php?courier=$courier");
        $COURIER = json_decode($JSON);

        return $COURIER;

    }

?>