<?php

    namespace Wonder\Elements\Concerns;

    trait HasGap {

        public ?array $gap = [
            'default' => 3,
            'sm' => null,
            'md' => null,
            'lg' => null,
            'xl' => null,
            '2xl' => null
        ];

        public function gap( array | int $gap = 1): static 
        {

            if (!is_array($gap)) { 
                $this->gap['default'] = $gap;
            } else {
                $this->gap = $gap;
            }

            return $this;

        }

    }