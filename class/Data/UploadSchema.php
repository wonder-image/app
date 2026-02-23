<?php

    namespace Wonder\Data;

    class UploadSchema {

        private const FIELDS = [
            'text' => \Wonder\Data\Fields\Text::class,
            'number' => \Wonder\Data\Fields\Number::class,
        ];

        public string $key;
        
        public function __construct($key) 
        {

            $this->key = $key;

        }

        public static function key( string $key ): static
        {

            return new self($key);

        }

        public function __call( string $method, array $args )
        {

            $method = strtolower($method);
            $class = self::FIELDS[$method] ?? null;

            if ($class !== null) {
                return new $class($this->key);
            }

            throw new \Exception("Campo {$method} non supportato. Usa text() o number().");

        }

    }
