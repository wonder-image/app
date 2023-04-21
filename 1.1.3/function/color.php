<?php

    function hexToRgb($hex) {

        list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
        return "$r, $g, $b";

    }

?>