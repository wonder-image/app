<?php

    function hashPassword($password) {

        $password = sanitize($password);
        $hasher = new Wonder\PasswordHash(8, true);
        $hashPassword = $hasher->HashPassword(trim($password));

        return $hashPassword;

    }

    function checkPassword($password, $hashPassword) {

        $hasher = new Wonder\PasswordHash(8, true);
        return $hasher->CheckPassword(trim($password), $hashPassword);

    }

?>