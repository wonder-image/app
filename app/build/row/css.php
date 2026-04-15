<?php

    foreach ($DEFAULT->font as $key => $value) {

        $name = sanitize($value['name']);
        $link = sanitize($value['link']);
        $fontFamily = sanitize($value['font-family']);

        if (!sqlSelect('css_font', ['name' => $name], 1)->exists) {
            
            $values = [
                "name" => $name,
                "link" => $link,
                "font_family" => $fontFamily,
                "visible" => "true"
            ];

            sqlInsert('css_font', $values);

        }

    }

    if (!sqlSelect('css_default', ['id' => 1], 1)->exists) {
                    
        $values = \Wonder\App\RuntimeDefaults::cssDefaultRow();

        sqlInsert('css_default', $values);

    }

    foreach ($DEFAULT->color as $key => $value) {

        $var = sanitize($value['var']);
        $name = sanitize($value['name']);
        $color = sanitize($value['color']);
        $contrast = sanitize($value['contrast']);
        
        if (!sqlSelect('css_color', ['var' => $var], 1)->exists) {
            
            $values = [
                "var" => $var,
                "name" => $name,
                "color" => $color,
                "contrast" => $contrast
            ];
    
            sqlInsert('css_color', $values);
    
        }

    }

    if (!sqlSelect('css_input', ['id' => 1], 1)->exists) {
                    
        $values = \Wonder\App\RuntimeDefaults::cssInputRow();

        sqlInsert('css_input', $values);

    }

    if (!sqlSelect('css_modal', ['id' => 1], 1)->exists) {
                    
        $values = \Wonder\App\RuntimeDefaults::cssModalRow();

        sqlInsert('css_modal', $values);

    }

    if (!sqlSelect('css_dropdown', ['id' => 1], 1)->exists) {
                    
        $values = \Wonder\App\RuntimeDefaults::cssDropdownRow();

        sqlInsert('css_dropdown', $values);

    }

    if (!sqlSelect('css_alert', ['id' => 1], 1)->exists) {
                    
        $values = \Wonder\App\RuntimeDefaults::cssAlertRow();

        sqlInsert('css_alert', $values);

    }
