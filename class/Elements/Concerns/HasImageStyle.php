<?php

    namespace Wonder\Elements\Concerns;

    use Wonder\Concerns\HasSchema;

    /**
     * Classi e stile inline applicati alla <img> renderizzata da un media
     * (es. le anteprime di una Gallery), tenuti separati dagli attributi del
     * componente contenitore. I renderer leggono `image-class` / `image-style`
     * dallo schema e li inoltrano al builder Image.
     */
    trait HasImageStyle
    {
        use HasSchema;

        public function imageClass(string $class): static
        {
            return $this->schema('image-class', [$class]);
        }

        public function addImageClass(string $class): static
        {
            return $this->schemaPush('image-class', $class);
        }

        public function imageStyle(string $property, string|int|float $value): static
        {
            $styles = $this->getSchema('image-style');
            $styles = is_array($styles) ? $styles : [];
            $styles[$property] = (string) $value;

            return $this->schema('image-style', $styles);
        }

        public function imageStyles(array $styles): static
        {
            foreach ($styles as $property => $value) {
                if (!is_string($property) || $property === '' || !is_scalar($value)) {
                    continue;
                }

                $this->imageStyle($property, (string) $value);
            }

            return $this;
        }
    }
