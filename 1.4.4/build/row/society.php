<?php

    if (!sqlSelect('society', ['id' => 1], 1)->exists) {
        
        sqlInsert('society', []);
        sqlInsert('society_address', []);
        sqlInsert('society_legal_address', []);
        sqlInsert('society_social', []);
        sqlInsert('logos', []);

    }