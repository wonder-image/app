<?php

    namespace Wonder\App;

    class Theme {

        protected static string $theme = 'css';

        public static function set(string $theme): void
        {

            if (!in_array($theme, [ 'css', 'bootstrap' ])) {
                throw new \Exception("Tema {$theme} non trovato.");
            } else {
                self::$theme = $theme;
            }

        }

        public static function get(): string
        {

            return self::$theme;

        }

    }