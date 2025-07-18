<?php

    function getAddress($id, $table = 'addresses') {


        $RETURN = new Wonder\Plugin\Custom\Address\Address($table);

        return $RETURN->getById($id);

    }