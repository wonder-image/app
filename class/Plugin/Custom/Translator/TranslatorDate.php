<?php

    namespace Wonder\Plugin\Custom\Translator;

    class TranslatorDate {

        public static function Day( $date ) {

            $day = strtolower(date("l", strtotime($date)));

            return __t("date.week.$day");
    
        }

        public static function Month( $date ) {

            $month = strtolower(date("F", strtotime($date)));
    
            return __t("date.month.$month");
            
        }

    }