<?php

    namespace Wonder\Themes\Bootstrap\Concerns;

    use Wonder\Themes\Bootstrap\Concerns\Breakpoint;

    trait CanSpanColumn {

        use Breakpoint;

        public function getColumnSpan( array $span): string 
        {

            $class = [];

            foreach ($span as $key => $value) {
                if ($value != null) {
                    array_push($class, 'col-span-'.$this->translateBreakpoint($key) .$value);
                }
            }

            return implode(' ', $class);

        }

    }