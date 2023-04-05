<?php

    if (sqlSelect('css_default', ['id' => 1], 1)->Nrow == 0) {
                    
        $values = [
            "font_id" => 1,
            "font_size" => 16,
            "font_weight" => 400,
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