<?php

    namespace Wonder\Concerns;

    trait HasSchema {

        public array $schema = [];

        public function schema($key, $value): static 
        {

            $this->schema[$key] = $value;

            return $this;

        }

        public function schemaPush($key, $value): static 
        {

            if (!isset($this->schema[$key])) {
                $this->schema[$key] = [];
            }

            array_push($this->schema[$key], $value);

            return $this;

        }

        public function getSchema($key = null)
        {

            return $key == null ? $this->schema : $this->schema[$key] ?? null;

        }

    }