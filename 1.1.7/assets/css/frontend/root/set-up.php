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

    --spacer: <?=$CSS_DEFAULT->spacer?>px;

    --font-family: <?=info('css_font', 'id', $CSS_DEFAULT->font_id)->font_family?>;
    --font-weight: <?=$CSS_DEFAULT->font_weight?>;
    --font-size: <?=$CSS_DEFAULT->font_size?>px;
    --line-height: <?=$CSS_DEFAULT->line_height?>px;

    --title-big-font-family: <?=info('css_font', 'id', $CSS_DEFAULT->title_big_font_id)->font_family?>;
    --title-big-font-weight: <?=$CSS_DEFAULT->title_big_font_weight?>;
    --title-big-font-size: <?=$CSS_DEFAULT->title_big_font_size?>px;
    --title-big-line-height: <?=$CSS_DEFAULT->title_big_line_height?>px;

    --title-font-family: <?=info('css_font', 'id', $CSS_DEFAULT->title_font_id)->font_family?>;
    --title-font-weight: <?=$CSS_DEFAULT->title_font_weight?>;
    --title-font-size: <?=$CSS_DEFAULT->title_font_size?>px;
    --title-line-height: <?=$CSS_DEFAULT->title_line_height?>px;

    --subtitle-font-family: <?=info('css_font', 'id', $CSS_DEFAULT->subtitle_font_id)->font_family?>;
    --subtitle-font-weight: <?=$CSS_DEFAULT->subtitle_font_weight?>;
    --subtitle-font-size: <?=$CSS_DEFAULT->subtitle_font_size?>px;
    --subtitle-line-height: <?=$CSS_DEFAULT->subtitle_line_height?>px;

    --text-font-family: <?=info('css_font', 'id', $CSS_DEFAULT->text_font_id)->font_family?>;
    --text-font-weight: <?=$CSS_DEFAULT->text_font_weight?>;
    --text-font-size: <?=$CSS_DEFAULT->text_font_size?>px;
    --text-line-height: <?=$CSS_DEFAULT->text_line_height?>px;

    --text-small-font-family: <?=info('css_font', 'id', $CSS_DEFAULT->text_small_font_id)->font_family?>;
    --text-small-font-weight: <?=$CSS_DEFAULT->text_small_font_weight?>;
    --text-small-font-size: <?=$CSS_DEFAULT->text_small_font_size?>px;
    --text-small-line-height: <?=$CSS_DEFAULT->text_small_line_height?>px;

    --button-font-weight: <?=$CSS_DEFAULT->button_font_weight?>;
    --button-font-size: <?=$CSS_DEFAULT->button_font_size?>px;
    --button-line-height: <?=$CSS_DEFAULT->button_line_height?>px;
    --button-border-radius: <?=$CSS_DEFAULT->button_border_radius?>px;
    --button-border-width: <?=$CSS_DEFAULT->button_border_width?>px;

    --badge-font-weight: <?=$CSS_DEFAULT->badge_font_weight?>;
    --badge-font-size: <?=$CSS_DEFAULT->badge_font_size?>px;
    --badge-line-height: <?=$CSS_DEFAULT->badge_line_height?>px;
    --badge-border-radius: <?=$CSS_DEFAULT->badge_border_radius?>px;
    --badge-border-width: <?=$CSS_DEFAULT->badge_border_width?>px;

    --header-height: <?=$CSS_DEFAULT->header_height?>px;

    --default-image: url('<?=$DEFAULT->image?>');

}