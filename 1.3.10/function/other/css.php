<?php

    function cssRoot($update = true) {

        global $DEFAULT;
        global $ROOT;

        $PATH = $ROOT.'/assets/'.$_ENV['ASSETS_VERSION'].'/css/set-up/root.css';

        $CSS_DEFAULT = info('css_default', 'id', '1');
        $CSS_INPUT = info('css_input', 'id', '1');

        $CSS_MODAL = info('css_modal', 'id', '1');
        $CSS_DROPDOWN = info('css_dropdown', 'id', '1');
        $CSS_ALERT = info('css_alert', 'id', '1');

        $fontFamilyDefault = html_entity_decode(info('css_font', 'id', $CSS_DEFAULT->font_id)->font_family, ENT_QUOTES | ENT_HTML5);
        $fontFamilyTitleBig = html_entity_decode(info('css_font', 'id', $CSS_DEFAULT->title_big_font_id)->font_family, ENT_QUOTES | ENT_HTML5);
        $fontFamilyTitle = html_entity_decode(info('css_font', 'id', $CSS_DEFAULT->title_font_id)->font_family, ENT_QUOTES | ENT_HTML5);
        $fontFamilySubtitle = html_entity_decode(info('css_font', 'id', $CSS_DEFAULT->subtitle_font_id)->font_family, ENT_QUOTES | ENT_HTML5);
        $fontFamilyText = html_entity_decode(info('css_font', 'id', $CSS_DEFAULT->text_font_id)->font_family, ENT_QUOTES | ENT_HTML5);
        $fontFamilyTextSmall = html_entity_decode(info('css_font', 'id', $CSS_DEFAULT->text_small_font_id)->font_family, ENT_QUOTES | ENT_HTML5);

        $RETURN = ":root {\n";
        $RETURN .= "\n";
        $RETURN .= "--spacer: {$CSS_DEFAULT->spacer}px;\n";
        $RETURN .= "--header-height: {$CSS_DEFAULT->header_height}px;\n";
        $RETURN .= "--default-image: url('$DEFAULT->image');\n";
        $RETURN .= "\n";
        $RETURN .= "/* Default font */\n";
        $RETURN .= "--font-family: $fontFamilyDefault;\n";
        $RETURN .= "--font-weight: {$CSS_DEFAULT->font_weight};\n";
        $RETURN .= "--font-size: {$CSS_DEFAULT->font_size}px;\n";
        $RETURN .= "--line-height: {$CSS_DEFAULT->line_height}px;\n";
        $RETURN .= "\n";
        $RETURN .= "/* Font titolo grande */\n";
        $RETURN .= "--title-big-font-family: $fontFamilyTitleBig;\n";
        $RETURN .= "--title-big-font-weight: {$CSS_DEFAULT->title_big_font_weight};\n";
        $RETURN .= "--title-big-font-size: {$CSS_DEFAULT->title_big_font_size}px;\n";
        $RETURN .= "--title-big-line-height: {$CSS_DEFAULT->title_big_line_height}px;\n";
        $RETURN .= "\n";
        $RETURN .= "/* Font titolo */\n";
        $RETURN .= "--title-font-family: $fontFamilyTitle;\n";
        $RETURN .= "--title-font-weight: {$CSS_DEFAULT->title_font_weight};\n";
        $RETURN .= "--title-font-size: {$CSS_DEFAULT->title_font_size}px;\n";
        $RETURN .= "--title-line-height: {$CSS_DEFAULT->title_line_height}px;\n";
        $RETURN .= "\n";
        $RETURN .= "/* Font sottotitolo */\n";
        $RETURN .= "--subtitle-font-family: $fontFamilySubtitle;\n";
        $RETURN .= "--subtitle-font-weight: {$CSS_DEFAULT->subtitle_font_weight};\n";
        $RETURN .= "--subtitle-font-size: {$CSS_DEFAULT->subtitle_font_size}px;\n";
        $RETURN .= "--subtitle-line-height: {$CSS_DEFAULT->subtitle_line_height}px;\n";
        $RETURN .= "\n";
        $RETURN .= "/* Font testo */\n";
        $RETURN .= "--text-font-family: $fontFamilyText;\n";
        $RETURN .= "--text-font-weight: {$CSS_DEFAULT->text_font_weight};\n";
        $RETURN .= "--text-font-size: {$CSS_DEFAULT->text_font_size}px;\n";
        $RETURN .= "--text-line-height: {$CSS_DEFAULT->text_line_height}px;\n";
        $RETURN .= "\n";
        $RETURN .= "/* Font testo piccolo */\n";
        $RETURN .= "--text-small-font-family: $fontFamilyTextSmall;\n";
        $RETURN .= "--text-small-font-weight: {$CSS_DEFAULT->text_small_font_weight};\n";
        $RETURN .= "--text-small-font-size: {$CSS_DEFAULT->text_small_font_size}px;\n";
        $RETURN .= "--text-small-line-height: {$CSS_DEFAULT->text_small_line_height}px;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up bottoni */\n";
        $RETURN .= "--button-font-weight: $CSS_DEFAULT->button_font_weight;\n";
        $RETURN .= "--button-font-size: {$CSS_DEFAULT->button_font_size}px;\n";
        $RETURN .= "--button-line-height: {$CSS_DEFAULT->button_line_height}px;\n";
        $RETURN .= "--button-border-radius: {$CSS_DEFAULT->button_border_radius}px;\n";
        $RETURN .= "--button-border-width: {$CSS_DEFAULT->button_border_width}px;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up badge */\n";
        $RETURN .= "--badge-font-weight: $CSS_DEFAULT->badge_font_weight;\n";
        $RETURN .= "--badge-font-size: {$CSS_DEFAULT->badge_font_size}px;\n";
        $RETURN .= "--badge-line-height: {$CSS_DEFAULT->badge_line_height}px;\n";
        $RETURN .= "--badge-border-radius: {$CSS_DEFAULT->badge_border_radius}px;\n";
        $RETURN .= "--badge-border-width: {$CSS_DEFAULT->badge_border_width}px;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up input */\n";
        $RETURN .= "--input-tx-color: $CSS_INPUT->tx_color;\n";
        $RETURN .= "--input-tx-family: var(--font-family);\n";
        $RETURN .= "--input-tx-weight: var(--font-weight);\n";
        $RETURN .= "\n";
        $RETURN .= "--input-bg-color: $CSS_INPUT->bg_color;\n";
        $RETURN .= "--input-disabled-bg: $CSS_INPUT->disabled_bg_color;\n";
        $RETURN .= "\n";
        $RETURN .= "--input-select-hover: $CSS_INPUT->select_hover;\n";
        $RETURN .= "\n";
        $RETURN .= "--input-border-color: $CSS_INPUT->border_color;\n";
        $RETURN .= "--input-border-focus: $CSS_INPUT->border_color_focus;\n";
        $RETURN .= "--input-border-radius: {$CSS_INPUT->border_radius}px;\n";
        $RETURN .= "--input-border-top: {$CSS_INPUT->border_top}px;\n";
        $RETURN .= "--input-border-right: {$CSS_INPUT->border_right}px;\n";
        $RETURN .= "--input-border-bottom: {$CSS_INPUT->border_bottom}px;\n";
        $RETURN .= "--input-border-left: {$CSS_INPUT->border_left}px;\n";
        $RETURN .= "\n";
        $RETURN .= "--input-date-default: $CSS_INPUT->date_default;\n";
        $RETURN .= "--input-date-active: $CSS_INPUT->date_active;\n";
        $RETURN .= "--input-date-bg: $CSS_INPUT->date_bg;\n";
        $RETURN .= "--input-date-bg-hover: $CSS_INPUT->date_bg_hover;\n";
        $RETURN .= "--input-date-border-radius: {$CSS_INPUT->date_border_radius}px;\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up input label */\n";
        $RETURN .= "--input-label-color: $CSS_INPUT->label_color;\n";
        $RETURN .= "--input-label-focus-color: $CSS_INPUT->label_color_focus;\n";
        $RETURN .= "--input-label-weight: $CSS_INPUT->label_weight;\n";
        $RETURN .= "--input-label-focus-weight: $CSS_INPUT->label_weight_focus;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up modal */\n";
        $RETURN .= "--modal-tx: $CSS_MODAL->tx;\n";
        $RETURN .= "--modal-bg: $CSS_MODAL->bg;\n";
        $RETURN .= "--modal-border-color: $CSS_MODAL->border_color;\n";
        $RETURN .= "--modal-border-width: {$CSS_MODAL->border_width}px;\n";
        $RETURN .= "--modal-border-radius: {$CSS_MODAL->border_radius}px;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up dropdown */\n";
        $RETURN .= "--dropdown-tx: $CSS_DROPDOWN->tx;\n";
        $RETURN .= "--dropdown-bg: $CSS_DROPDOWN->bg;\n";
        $RETURN .= "--dropdown-bg-hover: $CSS_DROPDOWN->bg_hover;\n";
        $RETURN .= "--dropdown-border-color: $CSS_DROPDOWN->border_color;\n";
        $RETURN .= "--dropdown-border-width: {$CSS_DROPDOWN->border_width}px;\n";
        $RETURN .= "--dropdown-border-radius: {$CSS_DROPDOWN->border_radius}px;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up alert */\n";
        $RETURN .= "--alert-tx: $CSS_ALERT->tx;\n";
        $RETURN .= "--alert-bg: $CSS_ALERT->bg;\n";
        $RETURN .= "--alert-top: $CSS_ALERT->top;\n";
        $RETURN .= "--alert-right: $CSS_ALERT->right;\n";
        $RETURN .= "--alert-border-color: $CSS_ALERT->border_color;\n";
        $RETURN .= "--alert-border-width: {$CSS_ALERT->border_width}px;\n";
        $RETURN .= "--alert-border-radius: {$CSS_ALERT->border_radius}px;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up colori */\n";

        foreach (sqlSelect('css_color')->row as $key => $row) {
            
            $var = $row["var"];
            $colorHEX = $row["color"];
            $colorRGB = hexToRgb($colorHEX);
            $contrastHEX = $row["contrast"];
            $contrastRGB = hexToRgb($contrastHEX);

            $RETURN .= "\n";
            $RETURN .= "/* $var */\n";
            $RETURN .= "--$var-color: $colorHEX;\n";
            $RETURN .= "--$var-o-color: $contrastHEX;\n";
            $RETURN .= "--$var-color-rgb: $colorRGB;\n";
            $RETURN .= "--$var-o-color-rgb: $contrastRGB;\n";

            for ($i=0; $i < 11; $i++) { 

                $opacity = $i * 10;
                $opacityCSS = $i / 10;
                
                $RETURN .= "--$var-color-$opacity: rgba(var(--$var-color-rgb), $opacityCSS);\n";
                $RETURN .= "--$var-o-color-$opacity: rgba(var(--$var-o-color-rgb), $opacityCSS);\n";

            }
            
        }

        $RETURN .= "\n";
        $RETURN .= "/* Set-up colori testo */\n";

        $CSS_DEFAULT = info('css_default', 'id', '1');

        $RETURN .= "--tx-color: ".$CSS_DEFAULT->tx_color.";\n";
        $RETURN .= "--tx-color-rgb: ".hexToRgb($CSS_DEFAULT->tx_color).";\n";

        for ($i=0; $i < 11; $i++) { 

            $opacity = $i * 10;
            $opacityCSS = $i / 10;
            
            $RETURN .= "--tx-color-$opacity: rgba(var(--tx-color-rgb), $opacityCSS);\n";

        }

        $RETURN .= "\n";
        $RETURN .= "/* Set-up colori sfondo */\n";

        $RETURN .= "--bg-color: ".$CSS_DEFAULT->bg_color.";\n";
        $RETURN .= "--bg-color-rgb: ".hexToRgb($CSS_DEFAULT->bg_color).";\n";

        for ($i=0; $i < 11; $i++) { 

            $opacity = $i * 10;
            $opacityCSS = $i / 10;
            
            $RETURN .= "--bg-color-$opacity: rgba(var(--bg-color-rgb), $opacityCSS);\n";

        }

        $RETURN .= "\n";
        $RETURN .= "}";
        
        if ($update) {
            
            $FILE = fopen($PATH, "w");
            fwrite($FILE, $RETURN);
            fclose($FILE);
            
        } else {

            return $RETURN;

        }

    }

    function cssColor($update = true) {

        global $ROOT;

        $PATH = $ROOT.'/assets/'.$_ENV['ASSETS_VERSION'].'/css/set-up/color.css';

        $RETURN = "/* Classi colori */\n";

        foreach (sqlSelect('css_color')->row as $key => $row) {
            
            $var = $row["var"];

            $RETURN .= "\n";
            $RETURN .= "/* $var */\n";
            $RETURN .= ".tx-$var { color: var(--$var-color) !important; }\n";
            $RETURN .= ".tx-$var-o { color: var(--$var-o-color) !important; }\n";
            $RETURN .= ".bg-$var { background: var(--$var-color) !important; }\n";
            $RETURN .= ".bg-$var-o { background: var(--$var-o-color) !important; }\n";

            for ($i=0; $i < 11; $i++) { 

                $opacity = $i * 10;
                
                $RETURN .= ".bg-$var-$opacity { background: var(--$var-color-$opacity) !important; }\n";
                $RETURN .= ".bg-$var-o-$opacity { background: var(--$var-o-color-$opacity) !important; }\n";

            }

            $RETURN .= "\n";
            $RETURN .= ".badge.badge-$var, .btn.btn-$var { border-color: var(--$var-color-100); background: var(--$var-color-100); color: var(--$var-o-color); }\n";
            $RETURN .= ".badge.badge-$var-o, .btn.btn-$var-o { border-color: var(--$var-color); background: var(--$var-color-0); color: var(--$var-color-100); }\n";
            $RETURN .= ".btn.btn-$var:hover { background: var(--$var-color-90); }\n";
            $RETURN .= ".btn.btn-$var-o:hover { background: var(--$var-color-10); }\n";
            
        }
        
        if ($update) {
            
            $FILE = fopen($PATH, "w");
            fwrite($FILE, $RETURN);
            fclose($FILE);
            
        } else {

            return $RETURN;

        }

    }