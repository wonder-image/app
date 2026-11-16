<?php

    namespace Wonder\Sql\Utility;

    /**
     * Creazione degli errori Sql
     * 
     * Copyright (c) 2023-2027 Andrea Marinoni. Tutti i diritti riservati.
     * Software proprietario: uso consentito solo con contratto scritto con il titolare.
     * Vedi il file LICENSE.
     * SPDX-License-Identifier: LicenseRef-Proprietary
     * 
     * @author andreamarinoni <marinoni@wonderimage.it>
     * 
     */
    
    class Error {

        public function __construct ( $action, $table, $query, $mysqli ) {

            // La query va solo nel log (Logger la legge da QueryException::query()).
            throw new QueryException(
                (string) $action,
                (string) $table,
                (string) $query,
                (int) ($mysqli->errno ?? 0),
                (string) ($mysqli->error ?? '')
            );

        }

    }