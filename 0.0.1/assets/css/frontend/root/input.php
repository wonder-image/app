<?php 

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    header("Content-type: text/css");

    $CSS_INPUT = info('css_input', 'id', '1');
    
?>
:root {

    --input-tx-color: <?=$CSS_INPUT->tx_color?>;
    --input-tx-family: var(--text-family);
    --input-tx-weight: var(--text-weight);

    --input-bg-color: <?=$CSS_INPUT->bg_color?>;
    --input-disabled-bg: <?=$CSS_INPUT->disabled_bg_color?>;

    --input-label-color: <?=$CSS_INPUT->label_color?>;
    --input-label-focus-color: <?=$CSS_INPUT->label_color_focus?>;
    --input-label-weight: <?=$CSS_INPUT->label_weight?>;
    --input-label-focus-weight: <?=$CSS_INPUT->label_weight_focus?>;

    --input-select-hover: <?=$CSS_INPUT->select_hover?>;

    --input-border-color: <?=$CSS_INPUT->border_color?>;
    --input-border-focus: <?=$CSS_INPUT->border_color_focus?>;
    --input-border-radius: <?=$CSS_INPUT->border_radius?>px;
    --input-border-top: <?=$CSS_INPUT->border_top?>px;
    --input-border-right: <?=$CSS_INPUT->border_right?>px;
    --input-border-bottom: <?=$CSS_INPUT->border_bottom?>px;
    --input-border-left: <?=$CSS_INPUT->border_left?>px;

    --input-date-default: <?=$CSS_INPUT->date_default?>;
    --input-date-active: <?=$CSS_INPUT->date_active?>;
    --input-date-bg: <?=$CSS_INPUT->date_bg?>;
    --input-date-bg-hover: <?=$CSS_INPUT->date_bg_hover?>;
    --input-date-border-radius: <?=$CSS_INPUT->date_border_radius?>px;

}