<?php

    function hashPassword($password) {

        $hasher = new Wonder\Password(8, true);
        $hashPassword = $hasher->HashPassword(trim($password));

        return $hashPassword;

    }

    function checkPassword($password, $hashPassword) {

        $hasher = new Wonder\Password(8, true);
        return $hasher->CheckPassword(trim($password), $hashPassword);

    }

?>