<?php 

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    header("Content-type: text/css");

    $CSS_MODAL = info('css_modal', 'id', '1');
    $CSS_DROPDOWN = info('css_dropdown', 'id', '1');
    $CSS_ALERT = info('css_alert', 'id', '1');
    
?>
:root {

    --modal-tx: <?=$CSS_MODAL->tx?>;
    --modal-bg: <?=$CSS_MODAL->bg?>;
    --modal-border-color: <?=$CSS_MODAL->border_color?>;
    --modal-border-width: <?=$CSS_MODAL->border_width?>px;
    --modal-border-radius: <?=$CSS_MODAL->border_radius?>px;

    --dropdown-tx: <?=$CSS_DROPDOWN->tx?>;
    --dropdown-bg: <?=$CSS_DROPDOWN->bg?>;
    --dropdown-bg-hover: <?=$CSS_DROPDOWN->bg_hover?>;
    --dropdown-border-color: <?=$CSS_DROPDOWN->border_color?>;
    --dropdown-border-width: <?=$CSS_DROPDOWN->border_width?>px;
    --dropdown-border-radius: <?=$CSS_DROPDOWN->border_radius?>px;

    --alert-tx: <?=$CSS_ALERT->tx?>;
    --alert-bg: <?=$CSS_ALERT->bg?>;
    --alert-top: <?=$CSS_ALERT->top?>;
    --alert-right: <?=$CSS_ALERT->right?>;
    --alert-border-color: <?=$CSS_ALERT->border_color?>;
    --alert-border-width: <?=$CSS_ALERT->border_width?>px;
    --alert-border-radius: <?=$CSS_ALERT->border_radius?>px;

}