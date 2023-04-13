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
        $contrastHEX = $row["contrast"];
        $contrastRGB = hexToRgb($contrastHEX);

        echo "--$var-color: $colorHEX;";
        echo "--$var-o-color: $contrastHEX;";
        echo "--$var-color-rgb: $colorRGB;";
        echo "--$var-o-color-rgb: $contrastRGB;";

        for ($i=0; $i < 11; $i++) { 

            $opacity = $i * 10;
            $opacityCSS = $i / 10;
            
            echo "--$var-color-$opacity: rgba(var(--$var-color-rgb), $opacityCSS);";
            echo "--$var-o-color-$opacity: rgba(var(--$var-o-color-rgb), $opacityCSS);";

        }
        
    }

    $CSS_DEFAULT = info('css_default', 'id', '1');

    echo "--tx-color: ".$CSS_DEFAULT->tx_color.";";
    echo "--tx-color-rgb: ".hexToRgb($CSS_DEFAULT->tx_color).";";

    for ($i=0; $i < 11; $i++) { 

        $opacity = $i * 10;
        $opacityCSS = $i / 10;
        
        echo "--tx-color-$opacity: rgba(var(--tx-color-rgb), $opacityCSS);";

    }

    echo "--bg-color: ".$CSS_DEFAULT->bg_color.";";
    echo "--bg-color-rgb: ".hexToRgb($CSS_DEFAULT->bg_color).";";

    for ($i=0; $i < 11; $i++) { 

        $opacity = $i * 10;
        $opacityCSS = $i / 10;
        
        echo "--bg-color-$opacity: rgba(var(--bg-color-rgb), $opacityCSS);";

    }

    echo "}";
    echo "";
    echo "";
    echo "";

    echo ".tx-color: var(--tx-color) !important;";
    echo ".bg-color: var(--bg-color) !important;";

    for ($i=0; $i < 11; $i++) { 

        $opacity = $i * 10;
        
        echo ".bg-bg-$opacity { background: var(--bg-color-$opacity) !important; }";

    }
    
    echo "";
    echo "";

    foreach (sqlSelect('css_color')->row as $key => $row) {
        
        $var = $row["var"];

        echo "/* $var */
        .tx-$var { color: var(--$var-color) !important; }
        .tx-$var-o { color: var(--$var-o-color) !important; }
        .bg-$var { background: var(--$var-color) !important; }
        .bg-$var-o { background: var(--$var-o-color) !important; }";

        for ($i=0; $i < 11; $i++) { 

            $opacity = $i * 10;
            
            echo ".bg-$var-$opacity { background: var(--$var-color-$opacity) !important; }";
            echo ".bg-$var-o-$opacity { background: var(--$var-o-color-$opacity) !important; }";

        }

        echo "
        .badge.badge-$var,
        .btn.btn-$var {
        border-color: var(--$var-color-100);
        background: var(--$var-color-100);
        color: var(--$var-o-color);
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