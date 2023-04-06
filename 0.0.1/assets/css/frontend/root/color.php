<?php 

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    header("Content-type: text/css");

    echo ":root {";

    foreach (sqlSelect('css_color')->row as $key => $row) {
        
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

    foreach (sqlSelect('css_color')->row as $key => $row) {
        
        $var = $row["var"];
        $contrast = $row["contrast"];

        echo "
        .badge.badge-$var,
        .btn.btn-$var {
        border-color: var(--$var-color-100);
        background: var(--$var-color-100);
        color: $contrast;
        }
        .badge.badge-$var-o,
        .btn.btn-$var-o {
        border-color: var(--$var-color);
        background: var(--$var-color-0);
        color: var(--$var-color-100);
        }
        .btn.btn-$var:hover { background: var(--$var-color-90); }
        .btn.btn-$var-o:hover { background: var(--$var-color-10); }";
        
    }

?>