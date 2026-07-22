<?php

    namespace Wonder\Elements\Concerns;

    trait CanSpanColumn {

        protected bool $columnSpanDeclared = false;

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

            $this->columnSpanDeclared = true;

            if (!is_array($span)) { 
                $this->columnSpan['default'] = $span;
            } else {
                $this->columnSpan = $span;
            }

            return $this;

        }

        public function hasExplicitColumnSpan(): bool
        {

            return $this->columnSpanDeclared;

        }

    }
