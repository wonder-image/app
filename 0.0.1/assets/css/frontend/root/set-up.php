<?php 

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    header("Content-type: text/css");

    echo ":root {";
        
    echo "}";

?>
:root {
    --title-family: <?=$FONT->title_family?>;
    --subtitle-family: <?=$FONT->subtitle_family?>;
    --text-family: <?=$FONT->text_family?>;
    --title-weight: <?=$FONT->title_weight?>;
    --subtitle-weight: <?=$FONT->subtitle_weight?>;
    --text-weight: <?=$FONT->text_weight?>;
    --default-image: url('<?=$DEFAULT->image?>');
}