<?php

    // Hash nuovi: argon2id se disponibile, altrimenti bcrypt. Mai sanitize().
    function hashPassword($password) {

        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        return password_hash(trim((string) $password), $algorithm);

    }

    // Hash phpass legacy (`$P$`/`$H$`), validi finché l'utente non rientra.
    function isLegacyPasswordHash($hashPassword): bool {

        $hashPassword = (string) $hashPassword;

        return str_starts_with($hashPassword, '$P$') || str_starts_with($hashPassword, '$H$');

    }

    function checkPassword($password, $hashPassword) {

        $password = trim((string) $password);
        $hashPassword = (string) $hashPassword;

        if ($hashPassword === '') {
            return false;
        }

        if (!empty(password_get_info($hashPassword)['algo'])) {
            return password_verify($password, $hashPassword);
        }

        if (isLegacyPasswordHash($hashPassword)) {
            $hasher = new Wonder\Password(8, true);
            return $hasher->CheckPassword($password, $hashPassword);
        }

        return false;

    }

    function passwordNeedsRehash($hashPassword): bool {

        $hashPassword = (string) $hashPassword;

        if (isLegacyPasswordHash($hashPassword)) {
            return true;
        }

        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        return password_needs_rehash($hashPassword, $algorithm);

    }

    /**
     * Verifica la password grezza; solo per hash phpass riprova con
     * `sanitize()` (hash storici da input sanitizzato). Transitorio:
     * rimosso nella beta successiva.
     */
    function checkUserPassword($password, $hashPassword): bool {

        if (checkPassword($password, $hashPassword)) {
            return true;
        }

        return isLegacyPasswordHash($hashPassword)
            && function_exists('sanitize')
            && checkPassword(sanitize((string) $password), $hashPassword);

    }

    /**
     * Hash fittizio per i login senza utente o senza password: verificarlo costa
     * quanto una password errata. Precalcolato con i parametri di default; se
     * i default cambiano si rigenera una volta per processo.
     */
    function dummyPasswordHash(): string {

        static $hash = null;

        if ($hash === null) {
            $hash = defined('PASSWORD_ARGON2ID')
                ? '$argon2id$v=19$m=65536,t=4,p=1$UWtCeTRKbDRRSEFkZWxqNQ$ovEPl5lo1GRSSoSJEQxu5tXri4CfEOx4B00fNHR87Ew'
                : '$2y$12$L2pMEVRt9T1MKnzN9Iw53es9/yvMmb/8lrFGluKlM8YTn7yItzi3e';

            if (passwordNeedsRehash($hash)) {
                $hash = hashPassword(bin2hex(random_bytes(16)));
            }
        }

        return $hash;

    }
