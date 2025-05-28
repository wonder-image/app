<?php

    namespace Wonder\Themes\Bootstrap\Concerns;

    trait Breakpoint {

        public function translateBreakpoint($key) {

            return match ($key) {
                'default' => '',
                'sm' => 'sm-',
                'md' => 'md-',
                'lg' => 'lg-',
                'xl' => 'xl-',
                '2xl' => 'xxl-'
            };

        }

    }