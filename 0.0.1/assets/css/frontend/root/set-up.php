<?php 

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    header("Content-type: text/css");

    $CSS_DEFAULT = info('css_default', 'id', '1');
    
?>
:root {

    --spacer: <?=$CSS_DEFAULT->spacer?>;

    --title-big-font-family: <?=info('css_font', 'id', $CSS_DEFAULT->title_big_font_id)->font_family?>;
    --title-big-font-weight: <?=$CSS_DEFAULT->title_big_font_weight?>;
    --title-big-font-size: <?=$CSS_DEFAULT->title_big_font_size?>;
    --title-big-line-height: <?=$CSS_DEFAULT->title_big_line_height?>;

    --title-font-family: <?=info('css_font', 'id', $CSS_DEFAULT->title_font_id)->font_family?>;
    --title-font-weight: <?=$CSS_DEFAULT->title_font_weight?>;
    --title-font-size: <?=$CSS_DEFAULT->title_font_size?>;
    --title-line-height: <?=$CSS_DEFAULT->title_line_height?>;

    --subtitle-font-family: <?=info('css_font', 'id', $CSS_DEFAULT->subtitle_font_id)->font_family?>;
    --subtitle-font-weight: <?=$CSS_DEFAULT->subtitle_font_weight?>;
    --subtitle-font-size: <?=$CSS_DEFAULT->subtitle_font_size?>;
    --subtitle-line-height: <?=$CSS_DEFAULT->subtitle_line_height?>;

    --text-font-family: <?=info('css_font', 'id', $CSS_DEFAULT->text_font_id)->font_family?>;
    --text-font-weight: <?=$CSS_DEFAULT->text_font_weight?>;
    --text-font-size: <?=$CSS_DEFAULT->text_font_size?>;
    --text-line-height: <?=$CSS_DEFAULT->text_line_height?>;

    --text-small-font-family: <?=info('css_font', 'id', $CSS_DEFAULT->text_small_font_id)->font_family?>;
    --text-small-font-weight: <?=$CSS_DEFAULT->text_small_font_weight?>;
    --text-small-font-size: <?=$CSS_DEFAULT->text_small_font_size?>;
    --text-small-line-height: <?=$CSS_DEFAULT->text_small_line_height?>;

    --button-font-weight: <?=$CSS_DEFAULT->button_font_weight?>;
    --button-font-size: <?=$CSS_DEFAULT->button_font_size?>;
    --button-line-height: <?=$CSS_DEFAULT->button_line_height?>;
    --button-border-radius: <?=$CSS_DEFAULT->button_border_radius?>;
    --button-border-width: <?=$CSS_DEFAULT->button_border_width?>;

    --badge-font-weight: <?=$CSS_DEFAULT->badge_font_weight?>;
    --badge-font-size: <?=$CSS_DEFAULT->badge_font_size?>;
    --badge-line-height: <?=$CSS_DEFAULT->badge_line_height?>;
    --badge-border-radius: <?=$CSS_DEFAULT->badge_border_radius?>;
    --badge-border-width: <?=$CSS_DEFAULT->badge_border_width?>;

    --default-image: url('<?=$DEFAULT->image?>');

}