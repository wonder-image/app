<?php

    namespace Wonder\Themes\Bootstrap\Concerns;

    use Wonder\Themes\Bootstrap\Concerns\Breakpoint;

    trait HasColumns {

        use Breakpoint;

        public function getColumns( array $span): string 
        {

            $class = [ 'row', 'd-grid' ];

            foreach ($span as $key => $value) {
                if ($value != null) {
                    array_push($class, 'row-col-'.$this->translateBreakpoint($key).$value);
                }
            }

            return implode(' ', $class);

        }

    }