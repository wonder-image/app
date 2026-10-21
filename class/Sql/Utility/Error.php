<?php

    namespace Wonder\Sql\Utility;

    /**
     * Creazione degli errori Sql
     * 
     * @author andreamarinoni <marinoni@wonderimage.it>
     * @copyright 2024 andreamarinoni
     * @license MIT
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