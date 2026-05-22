<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Field;

    class File extends Field {

        public string $type = 'file';

        public function file(string $file): self
        {

            return $this->schema('file', trim($file));

        }

        public function uploader(string $uploader): self
        {

            return $this->schema('uploader', trim($uploader));

        }

        public function maxFile(int $maxFile): self
        {

            return $this->schema('max_file', max(1, $maxFile));

        }

        public function maxSize(int $maxSize): self
        {

            return $this->schema('max_size', max(1, $maxSize));

        }

        public function directory(string $directory): self
        {

            return $this->schema('directory', $directory);

        }

        public function fileValue(mixed $value): self
        {

            return $this->schema('file_value', $value);

        }

        public function sizeBefore(bool $sizeBefore): self
        {

            return $this->schema('size_before', $sizeBefore);

        }

        public function minSizeImage(?string $size): self
        {

            return $this->schema('min_size_image', $size);

        }

        protected function renderInput(): string {

            return '';
            
        }

    }
