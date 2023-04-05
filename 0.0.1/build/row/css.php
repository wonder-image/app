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
            "button_border_radius" => 5,
            "button_border_width" => 2,
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
        
        if (sqlSelect('css_color', ['var' => $var], 1)->Nrow == 0) {
            
            $values = [
                "var" => $var,
                "name" => $name,
                "color" => $color
            ];
    
            sqlInsert('css_color', $values);
    
        }

    }

?>