<?php

    function bootstrapColor($bootstrapColor) {

        if ($bootstrapColor == "primary") {
            $color = "#007bff";
        }elseif($bootstrapColor == "secondary") {
            $color = "#6c757d";
        }elseif($bootstrapColor == "success") {
            $color = "#28a745";
        }elseif($bootstrapColor == "danger") {
            $color = "#dc3545";
        }elseif($bootstrapColor == "warning") {
            $color = "#ffc107";
        }elseif($bootstrapColor == "info") {
            $color = "#17a2b8";
        }elseif($bootstrapColor == "light") {
            $color = "#f8f9fa";
        }elseif($bootstrapColor == "dark") {
            $color = "#343a40";
        }

        return $color;

    }

?>