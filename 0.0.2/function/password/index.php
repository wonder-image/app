<?php

    function hashPassword($password) {

        $password = sanitize($password);
        $hasher = new PasswordHash(8, TRUE);
        $hashPassword = $hasher->HashPassword(trim($password));

        return $hashPassword;

    }

    function checkPassword($password, $passwordHash) {

        return wp_check_password($password, $passwordHash);

    }

?>