<?php

    namespace Wonder\Elements\Concerns;

    trait HasColumns {

        public ?array $columns = [
            'default' => 1,
            'sm' => null,
            'md' => null,
            'lg' => null,
            'xl' => null,
            '2xl' => null
        ];

        public function columns( array | int $columns = 2): static 
        {

            if (!is_array($columns)) { 
                $this->columns['default'] = $columns;
            } else {
                $this->columns = $columns;
            }

            return $this;

        }

    }