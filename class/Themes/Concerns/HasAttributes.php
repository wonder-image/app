<?php

    namespace Wonder\Themes\Concerns;

    trait HasAttributes {

        protected function renderAttributes( ?array $attributes ): string
        {
            
            $html = [];

            if ($attributes != null) {
                foreach ($attributes ?? [] as $key => $value) {
                    if (is_bool($value)) {

                        if ($value) $html[] = $key;

                    } else if (is_array($value)) {

                        $attr = $key . '="';
                        foreach ($value as $k => $c) { $attr .= htmlspecialchars((string) $c).' '; }
                        $attr .= '"';
                        $html[] = $attr;

                    } else {

                        $html[] = $key . '="' . htmlspecialchars((string) $value) . '"';

                    }
                }
            }

            return implode(' ', $html);
        
        }

    }