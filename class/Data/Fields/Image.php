<?php

    namespace Wonder\Data\Fields;

    class Image extends File {

        public string $type = 'image';

        public function resizeToWidth( int $width ): self
        {

            return $this->schemaPush('resize', $width);

        }

        public function resizeToWidths( array $widths ): self
        {

            return $this->schema('resize', $widths);

        }

        public function convertToWebp(): self
        {

            return $this->schema('webp', true);

        }

        public function quality( int $quality ): self 
        {

            return $this->schema('quality', $quality);

        }

        public function responsive( ?array $widths = null, int $quality = 80 ): self
        {
            
            $widths ??= [120, 480, 620, 960, 1080, 1440, 1920, 2560];


            return $this->resizeToWidths( $widths )
                        ->quality($quality)
                        ->convertToWebp();

        }

        public function prepare( $image ): ValidationResult 
        {

            $result = parent::prepare($image);

            if ($result->isValid()) {

                return ValidationResult::success($image);

            } else {

                return $result;

            }

        }

    }