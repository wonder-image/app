<?php

    if (!sqlSelect('society', ['id' => 1], 1)->exists) {
        
        sqlInsert('society', ['id' => 1]);
        sqlInsert('society_address', ['id' => 1]);
        sqlInsert('society_legal_address', ['id' => 1]);
        sqlInsert('society_social', ['id' => 1]);
        sqlInsert('logos', ['id' => 1]);

    }