<?php

    if (!sqlSelect('seo', ['id' => 1], 1)->exists) {
            
        $values = \Wonder\App\SeedDefaults::seoRow();
        $values['id'] = 1;

        sqlInsert('seo', $values);

    }
