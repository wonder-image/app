<?php

    namespace Wonder\Data\Fields;

    class Text extends Field {

        public string $type = 'text';

        public function __construct($key)
        {

            parent::__construct($key);

            $this->validators([
                new \Wonder\Data\Validators\StringValidator()
            ]);

            $this->formatters([
                new \Wonder\Data\Formatters\String\TrimFormatter()
            ]);

        }
        
        public function lower(): self
        {
            
            return $this->addFormatter(
                new \Wonder\Data\Formatters\String\LowercaseFormatter()
            );

        }

        public function upper(): self
        {

            return $this->addFormatter(
                new \Wonder\Data\Formatters\String\UppercaseFormatter()
            );

        }

        public function ucwords(): self
        {
            
            return $this->addFormatter(
                new \Wonder\Data\Formatters\String\TitleCaseFormatter()
            );

        }

        public function slug(): self
        {
            
            return $this->addFormatter(
                new \Wonder\Data\Formatters\String\SlugFormatter()
            );

        }

        public function unique(): self
        {
            
            return $this->schema('unique', true);

        }

        public function sanitize( bool $sanitize = true ): self 
        {

            return $this->schema('sanitize', $sanitize);

        } 

        public function sanitizeFirst(): self 
        {

            return $this->lower()
                        ->ucwords()
                        ->sanitize();

        } 

    }