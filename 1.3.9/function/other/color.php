<?php

    function hexToRgb($hex) {

        list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
        return "$r, $g, $b";

    }

    function colorInfo($RGB) {

        $COLOR = (object) array();

        list($RED, $GREEN, $BLUE) = explode(",", str_replace(" ", "", $RGB));

        $R = $RED / 255;
        $G = $GREEN / 255;
        $B = $BLUE / 255;

        $Cmax = max($R,$G,$B);
        $Cmin = min($R,$G,$B);

        $Δ = $Cmax - $Cmin;

        # Lightness = Luminosità da 0 a 1 
            $lightness = ($Cmax - $Cmin) / 2;

        # Hue = Tinta da 0 a 360
            if ($Δ == 0) {
                $H = 0;
            }elseif ($Cmax == $R) {
                $H = 60 * (($G - $B) / $Δ);
            }elseif ($Cmax == $G) {
                $H = 60 * ((($B - $R) / $Δ) + 2);
            }elseif ($Cmax == $B) {
                $H = 60 * ((($R - $G) / $Δ) + 4);
            }

        # Saturation = Saturazione da 0 a 1
            if ($Δ == 0) {
                $S = 0;
            }else{
                $x = 2 * $lightness - 1;
                if ($x < 0) {
                    $x = -$x;
                }
                $S = $Δ / (1 - $x);
            }

        # Brightness
            $brightness = (($R * 299) + ($G * 587) + ($B * 114)) / 1000;

        $COLOR->rgb = $RGB;
        $COLOR->r = $RED;
        $COLOR->g = $GREEN;
        $COLOR->b = $BLUE;
        $COLOR->hue = $H;
        $COLOR->saturation = $S;
        $COLOR->lightness = $lightness;
        $COLOR->brightness = $brightness;

        # Neutral if write black or white with this background
            $COLOR->neutral = (object) array();
            if ($brightness > .6) {
                $COLOR->neutral->color = "black";
                $COLOR->neutral->rgb = "0,0,0";
                $COLOR->neutral->r = 0;
                $COLOR->neutral->g = 0;
                $COLOR->neutral->b = 0;
            } else {
                $COLOR->neutral->color = "white";
                $COLOR->neutral->rgb = "255,255,255";
                $COLOR->neutral->r = 255;
                $COLOR->neutral->g = 255;
                $COLOR->neutral->b = 255;
            }

        return $COLOR;
        
    }