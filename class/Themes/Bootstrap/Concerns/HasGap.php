<?php

    namespace Wonder\Themes\Bootstrap\Concerns;

    use Wonder\Themes\Bootstrap\Concerns\Breakpoint;

    trait HasGap {

        use Breakpoint;

        public function getGap( array $gap): string 
        {

            $class = [];

            foreach ($gap as $key => $value) {
                if ($value != null) {
                    array_push($class, 'g-'.$this->translateBreakpoint($key).$value);
                }
            }

            return implode(' ', $class);

        }

    }