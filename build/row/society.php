<?php

    if (sqlSelect('society', ['id' => 1], 1)->Nrow == 0) {
        
        sqlInsert('society', []);
        sqlInsert('society_address', []);
        sqlInsert('society_legal_address', []);
        sqlInsert('society_social', []);

    }

?>