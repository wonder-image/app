<?php

    $NAME = (object) [];
    $NAME->table = "media";

    $PAGE_TABLE = $TABLE->MEDIA;

    $FILTER_TYPE = 'limit';

    $BUTTON_ADD = true;

    $FILTER_CUSTOM = [];

    $TABLE_ACTION = [
        'modify' => true,
        'delete' => true
    ];

    $FILTER_SEARCH = [ 'name', 'alt' ];
