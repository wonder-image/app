<?php

    namespace Wonder\Elements\Concerns;

    trait CanSpanColumn {

        public ?array $columnSpan = [
            'default' => 1,
            'sm' => null,
            'md' => null,
            'lg' => null,
            'xl' => null,
            '2xl' => null
        ];

        public function columnSpan( array | int $span): static 
        {

            if (!is_array($span)) { 
                $this->columnSpan['default'] = $span;
            } else {
                $this->columnSpan = $span;
            }

            return $this;

        }

    }