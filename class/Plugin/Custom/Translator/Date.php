<?php

    namespace Wonder\Plugin\Custom\Translator;

    class Date {

        public function Day( $date ) {

            $newDate = date("D", strtotime($date));

            if ($newDate == "Mon") {
                $newDate = "Lunedì";
            } elseif ($newDate == "Tue") {
                $newDate = "Martedì";
            } elseif ($newDate == "Wed") {
                $newDate = "Mercoledì";
            } elseif ($newDate == "Thu") {
                $newDate = "Giovedì";
            } elseif ($newDate == "Fri") {
                $newDate = "Venerdì";
            } elseif ($newDate == "Sat") {
                $newDate = "Sabato";
            } elseif ($newDate == "Sun") {
                $newDate = "Domenica";
            }

            return $newDate;
    
        }

        public function Month( $date ) {

            $newDate = date("F", strtotime($date));
    
            if ($newDate == "January") {
                $newDate = "Gennaio";
            } elseif ($newDate == "February") {
                $newDate = "Febbraio";
            } elseif ($newDate == "March") {
                $newDate = "Marzo";
            } elseif ($newDate == "April") {
                $newDate = "Aprile";
            } elseif ($newDate == "May") {
                $newDate = "Maggio";
            } elseif ($newDate == "June") {
                $newDate = "Giugno";
            } elseif ($newDate == "July") {
                $newDate = "Luglio";
            } elseif ($newDate == "August") {
                $newDate = "Agosto";
            } elseif ($newDate == "September") {
                $newDate = "Settembre";
            } elseif ($newDate == "October") {
                $newDate = "Ottobre";
            } elseif ($newDate == "November") {
                $newDate = "Novembre";
            } elseif ($newDate == "December") {
                $newDate = "Dicembre";
            }

            return $newDate;
            
        }


    }