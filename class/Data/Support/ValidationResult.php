<?php

    namespace Wonder\Data\Support;

    class ValidationResult
    {

        public function __construct(
            public bool $valid,
            public mixed $value = null,
            public ?string $message = null
        ) {}

        public static function success(mixed $value): self
        {
            
            return new self(true, $value);
        
        }

        public static function error(string $message, mixed $value = null): self
        {

            return new self(false, $value, $message);
        
        }

        public function isValid(): bool
        {

            return $this->valid;

        }

    }
