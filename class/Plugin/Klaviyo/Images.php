<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\ImagesApi
     */
    class Images extends Klaviyo {

        protected const IMAGE_TYPE = 'image';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Images;

        }

        public function all()
        {

            return $this->getImages();

        }

        public function get($imageId)
        {

            return $this->getImage($imageId);

        }

        public function create()
        {

            return $this->uploadImageFromUrl();

        }

        public function update($imageId)
        {

            return $this->updateImage($imageId);

        }

        public function uploadFile(\SplFileObject $file, ?string $name = null, ?bool $hidden = null)
        {

            return $this->uploadImageFromFile(
                file: $file,
                name: $name ?? ($this->params['name'] ?? null),
                hidden: $hidden ?? ($this->params['hidden'] ?? null)
            );

        }

        public function name($value): static
        {

            $this->dataType(self::IMAGE_TYPE);

            return $this->dataAttribute('name', $value);

        }

        public function hidden(bool $value = true): static
        {

            $this->dataType(self::IMAGE_TYPE);

            return $this->dataAttribute('hidden', $value);

        }

        public function importFromUrl($value): static
        {

            $this->dataType(self::IMAGE_TYPE);

            return $this->dataAttribute('import_from_url', $value);

        }

        public function url($value): static
        {

            return $this->importFromUrl($value);

        }

    }
