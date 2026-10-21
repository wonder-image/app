<?php

    /**
     * Algoritmo e opzioni degli hash nuovi: argon2id se disponibile, altrimenti
     * bcrypt con il costo di default della versione PHP (10 prima della 8.4, poi 12).
     *
     * @return array{0:string|int,1:array<string,int>}
     */
    function passwordHashSettings(?bool $argon2id = null, ?int $phpVersionId = null): array {

        if ($argon2id ?? defined('PASSWORD_ARGON2ID')) {
            return [PASSWORD_ARGON2ID, []];
        }

        return [PASSWORD_BCRYPT, ['cost' => ($phpVersionId ?? PHP_VERSION_ID) >= 80400 ? 12 : 10]];

    }

    // Hash nuovi. Mai sanitize().
    function hashPassword($password) {

        [$algorithm, $options] = passwordHashSettings();

        return password_hash(trim((string) $password), $algorithm, $options);

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

        [$algorithm, $options] = passwordHashSettings();

        return password_needs_rehash($hashPassword, $algorithm, $options);

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
     * Hash fittizio precalcolato con le stesse impostazioni di passwordHashSettings().
     * Senza argomenti usa il runtime corrente.
     */
    function dummyPasswordHashFor(?bool $argon2id = null, ?int $phpVersionId = null): string {

        [$algorithm, $options] = passwordHashSettings($argon2id, $phpVersionId);

        if ($algorithm === PASSWORD_ARGON2ID) {
            return '$argon2id$v=19$m=65536,t=4,p=1$UWtCeTRKbDRRSEFkZWxqNQ$ovEPl5lo1GRSSoSJEQxu5tXri4CfEOx4B00fNHR87Ew';
        }

        return $options['cost'] === 12
            ? '$2y$12$L2pMEVRt9T1MKnzN9Iw53es9/yvMmb/8lrFGluKlM8YTn7yItzi3e'
            : '$2y$10$aIhNFUbDxb26B34HnaCifugtW8EOKlUb7kvwwAEwRHTl0iluclUtq';

    }

    /**
     * Hash fittizio per i login senza utente o senza password: verificarlo costa
     * quanto una password errata. Se i default di argon2id cambiano si rigenera
     * una volta per processo.
     */
    function dummyPasswordHash(): string {

        static $hash = null;

        if ($hash === null) {
            $hash = dummyPasswordHashFor();

            if (passwordNeedsRehash($hash)) {
                $hash = hashPassword(bin2hex(random_bytes(16)));
            }
        }

        return $hash;

    }
