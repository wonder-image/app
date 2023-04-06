<?php

    if (sqlSelect('css_default', ['id' => 1], 1)->Nrow == 0) {
                    
        $values = [
            "font_id" => 1,
            "font_weight" => 400,
            "font_size" => 16,
            "line_height" => 16,
            "title_big_font_id" => 1,
            "title_big_font_weight" => 500,
            "title_big_font_size" => 40,
            "title_big_line_height" => 40,
            "title_font_id" => 1,
            "title_font_weight" => 500,
            "title_font_size" => 32,
            "title_line_height" => 32,
            "subtitle_font_id" => 1,
            "subtitle_font_weight" => 400,
            "subtitle_font_size" => 24,
            "subtitle_line_height" => 24,
            "text_font_id" => 1,
            "text_font_weight" => 300,
            "text_font_size" => 16,
            "text_line_height" => 20,
            "text_small_font_id" => 1,
            "text_small_font_weight" => 300,
            "text_small_font_size" => 12,
            "text_small_line_height" => 12,
            "button_font_size" => 14,
            "button_line_height" => 16,
            "button_font_weight" => 400,
            "button_border_radius" => 5,
            "button_border_width" => 2,
            "badge_font_size" => 12,
            "badge_line_height" => 12,
            "badge_font_weight" => 400,
            "badge_border_radius" => 5,
            "badge_border_width" => 1,
            "tx_color" => '#000000',
            "bg_color" => '#ffffff',
            "spacer" => 4
        ];

        sqlInsert('css_default', $values);

    }

    foreach ($DEFAULT->font as $key => $value) {

        $name = sanitize($value['name']);
        $link = sanitize($value['link']);
        $fontFamily = sanitize($value['font-family']);
        
        if (sqlSelect('css_font', ['name' => $name], 1)->Nrow == 0) {
            
            $values = [
                "name" => $name,
                "link" => $link,
                "font_family" => $fontFamily,
                "visible" => "true"
            ];
    
            sqlInsert('css_font', $values);
    
        }

    }

    foreach ($DEFAULT->color as $key => $value) {

        $var = sanitize($value['var']);
        $name = sanitize($value['name']);
        $color = sanitize($value['color']);
        $contrast = sanitize($value['contrast']);
        
        if (sqlSelect('css_color', ['var' => $var], 1)->Nrow == 0) {
            
            $values = [
                "var" => $var,
                "name" => $name,
                "color" => $color,
                "contrast" => $contrast
            ];
    
            sqlInsert('css_color', $values);
    
        }

    }

    if (sqlSelect('css_input', ['id' => 1], 1)->Nrow == 0) {
                    
        $values = [
            "tx_color" => "#000000",
            "bg_color" => "#ffffff",
            "disabled_bg_color" => "#eeeeee",
            "label_color" => "#626262",
            "label_color_focus" => "#626262",
            "label_weight" => 300,
            "label_weight_focus" => 400,
            "select_hover" => "#f6f6f6",
            "border_color" => "#DEDEDE",
            "border_color_focus" => "#626262",
            "border_radius" => 5,
            "border_top" => 1,
            "border_right" => 1,
            "border_bottom" => 1,
            "border_left" => 1,
            "date_default" => "#DEDEDE",
            "date_active" => "#626262",
            "date_bg" => "#f6f6f6",
            "date_bg_hover" => "#eeeeee",
            "date_border_radius" => 5
        ];

        sqlInsert('css_input', $values);

    }

    if (sqlSelect('css_modal', ['id' => 1], 1)->Nrow == 0) {
                    
        $values = [
            "tx" => "#000000",
            "bg" => "#ffffff",
            "border_color" => "#DEDEDE",
            "border_width" => 1,
            "border_radius" => 5
        ];

        sqlInsert('css_modal', $values);

    }

    if (sqlSelect('css_dropdown', ['id' => 1], 1)->Nrow == 0) {
                    
        $values = [
            "tx" => "#000000",
            "bg" => "#ffffff",
            "bg_hover" => "rgba(0,0,0,.02)",
            "border_color" => "#DEDEDE",
            "border_width" => 1,
            "border_radius" => 5
        ];

        sqlInsert('css_dropdown', $values);

    }

    if (sqlSelect('css_alert', ['id' => 1], 1)->Nrow == 0) {
                    
        $values = [
            "tx" => "var(--dark-color)",
            "bg" => "var(--light-color)",
            "top" => "calc(var(--spacer) * 5)",
            "right" => "calc(var(--spacer) * 5)",
            "border_color" => "#DEDEDE",
            "border_width" => 1,
            "border_radius" => 5
        ];

        sqlInsert('css_alert', $values);

    }

?>