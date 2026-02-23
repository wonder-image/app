<?php

    namespace Wonder\Data\Fields;

    class File extends Field {

        public string $type = 'file';

        public function __construct($key) 
        {

            parent::__construct($key);

            $this->maxFile(1);
            $this->maxSize(1);
            
        }

        public function mimeType( $mimeType ): self
        {
            
            return $this->schema('mime-type', $mimeType);

        }

        /**
         * Massimo peso file
         *
         * @param integer $maxSize in mb
         * @return self
         */
        public function maxSize( int $maxSize ): self
        {

            return $this->schema('max-size', $maxSize * 1048576);

        }

        public function maxFile( int $maxFile ): self
        {

            return $this->schema('max-file', $maxFile);

        }

        public function minFile( int $minFile ): self
        {

            return $this->schema('min-file', $minFile);

        }

        public function path( string $path ): self
        {

            return $this->schema('path', $path);

        }

        public function prepare( $file ): ValidationResult 
        {

            $result = parent::prepare($file);

            if ($result->isValid()) {

                return ValidationResult::success($file);

            } else {

                return $result;

            }

        }

    }