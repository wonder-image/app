<?php

    if (!sqlSelect('logos', ['id' => 1], 1)->exists) {

        sqlInsert('logos', ['id' => 1]);

    }
