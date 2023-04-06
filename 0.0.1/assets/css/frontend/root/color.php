<?php 

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    header("Content-type: text/css");

    echo ":root {";

    foreach (sqlSelect('css_color', ['id' => '1'])->row as $key => $row) {
        
        $var = $row["var"];
        $colorHEX = $row["color"];
        $colorRGB = hexToRgb($colorHEX);

        echo "--$var-color: $colorHEX;";
        echo "--$var-color-rgb: $colorRGB;";

        for ($i=0; $i < 11; $i++) { 

            $opacity = $i * 10;
            $opacityCSS = $i / 10;
            
            echo "--$var-color-$opacity: rgba(var(--$var-color-rgb), $opacityCSS);";

        }
        
    }

    foreach (sqlSelect('css_default', ['id' => '1'])->row as $key => $row) {
        
        $var = 'tx';
        $colorHEX = $row["tx_color"];
        $colorRGB = hexToRgb($colorHEX);


        echo "--$var-color: $colorHEX;";
        echo "--$var-color-rgb: $colorRGB;";

        for ($i=0; $i < 11; $i++) { 

            $opacity = $i * 10;
            $opacityCSS = $i / 10;
            
            echo "--$var-color-$opacity: rgba(var(--$var-color-rgb), $opacityCSS);";

        }

        $var = 'bg';
        $colorHEX = $row["bg_color"];
        $colorRGB = hexToRgb($colorHEX);

        echo "--$var-color: $colorHEX;";
        echo "--$var-color-rgb: $colorRGB;";

        for ($i=0; $i < 11; $i++) { 

            $opacity = $i * 10;
            $opacityCSS = $i / 10;
            
            echo "--$var-color-$opacity: rgba(var(--$var-color-rgb), $opacityCSS);";

        }
        
    }

    echo "}";

?>