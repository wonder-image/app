<?php

    namespace Wonder\Plugin\Custom\String;

    class Rand {

        static $letter = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        static $number = "0123456789";
        static $characters = "";


        public function __construct( $type = 'all' ) { self::init($type); }

        public static function init( $type = 'all' ) {

            if ($type == 'all') {
                self::$characters = self::$letter.self::$number;
            } else if ($type == 'letters' || $type == 'letter') {
                self::$characters = self::$letter;
            } else if ($type == 'numbers' || $type == 'number') {
                self::$characters = self::$number;
            }

        }

        public static function generate( $lenght = 10, $prefix = null ) {

            if (empty(self::$characters)) { self::init(); }

            $code = '';
            for ($i = 0; $i < $lenght; $i++) {
                $code .= self::$characters[rand(0, strlen(self::$characters) - 1)];
            }

            if(!empty($prefix)){ $code = $prefix.$code; }

            return $code;

        }

    }