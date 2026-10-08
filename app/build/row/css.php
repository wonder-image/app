<?php

    # Font: solo in una tabella vuota. Sui siti che li hanno già, quelli
    # nuovi del pacchetto li aggiunge AppDefaults in locale, per slug.
    if (!sqlSelect('css_font', null, 1)->exists) {

        foreach (\Wonder\App\AppDefaults::fontRows() as $values) {
            sqlInsert('css_font', $values);
        }

    }

    if (!sqlSelect('css_default', ['id' => 1], 1)->exists) {
                    
        $values = \Wonder\App\SeedDefaults::cssDefaultRow();
        $values['id'] = 1;

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
                    
        $values = \Wonder\App\SeedDefaults::cssInputRow();
        $values['id'] = 1;

        sqlInsert('css_input', $values);

    }

    if (!sqlSelect('css_auth', ['id' => 1], 1)->exists) {

        $values = \Wonder\App\SeedDefaults::cssAuthRow();
        $values['id'] = 1;

        sqlInsert('css_auth', $values);

    }

    if (!sqlSelect('css_modal', ['id' => 1], 1)->exists) {
                    
        $values = \Wonder\App\SeedDefaults::cssModalRow();
        $values['id'] = 1;

        sqlInsert('css_modal', $values);

    }

    if (!sqlSelect('css_dropdown', ['id' => 1], 1)->exists) {
                    
        $values = \Wonder\App\SeedDefaults::cssDropdownRow();
        $values['id'] = 1;

        sqlInsert('css_dropdown', $values);

    }

    if (!sqlSelect('css_alert', ['id' => 1], 1)->exists) {
                    
        $values = \Wonder\App\SeedDefaults::cssAlertRow();
        $values['id'] = 1;

        sqlInsert('css_alert', $values);

    }
