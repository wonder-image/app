<?php

    namespace Wonder\Elements\Media;

    use Wonder\Elements\Concerns\HasMediaFit;
    use Wonder\App\Path;
    use Wonder\Http\UrlParser;

    class Image extends Media {

        use HasMediaFit;

        public array $sizes;
        public int $defaultSize;
        public bool $webp;

        public function __construct( string $src ) {

            $this->schema('src', $src);

        }

        public static function src( string $src ): self 
        {

            return new self($src);

        }

        public function alt( string $alt ): self 
        {

            return $this->attr('alt', htmlspecialchars($alt));

        }

        public function hasWebP( bool $webp = true ): self
        {

            return $this->schema('webp', $webp);

        }

        public function size( int $size)
        {

            return $this->schema('default-size', $size);

        }

        public function sizes( array $sizes ): self
        {

            return $this->schema('sizes', $sizes);

        }

        public function skeleton( bool $skeleton = true ): self
        {

            return $this->schema('skeleton', $skeleton);

        }


        public function notDraggable( bool $draggable = true ): self
        {

            return $this->schema('draggable', $draggable ? false : true);

        }

        public function loading( string $loading = 'lazy' ): self
        {

            return $this->attr('loading', $loading );

        }

        /**
         * Segnala l'immagine come candidata LCP (hero above-the-fold): alza la priorità
         * di fetch (fetchpriority=high) e disabilita il lazy-load (loading=eager), così il
         * browser la scarica tra le prime risorse. Da usare su UNA sola immagine per pagina
         * (quella che domina il primo viewport), mai a tappeto.
         */
        public function priority( bool $priority = true ): self
        {

            if (!$priority) {
                return $this;
            }

            $this->attr('fetchpriority', 'high');

            return $this->attr('loading', 'eager');

        }

        public function url() : string 
        {

            $image = $this->getSchema('src');
            $size = $this->getSchema('default-size');

            if (empty($image)) {
                
                return '';

            } else if (empty($size) || !self::supportsResponsiveVariants($image)) {

                return $image;

            } else {

                $pathInfo = pathinfo($image);

                $name = $pathInfo['filename'] ?? '';
                $extension = $pathInfo['extension'] ?? '';

                $directory = str_replace((new Path())->site, '', $pathInfo['dirname']);
                $directoryUrl = (new Path())->site.$directory.DIRECTORY_SEPARATOR;

                return sprintf('%s%s-%d.%s', $directoryUrl, $name, $size, $extension);

            }

        }

        /** URL relativi o assoluti del sito possono usare le varianti generate localmente. */
        public static function supportsResponsiveVariants(string $src): bool
        {
            $source = new UrlParser($src);

            if (!$source->isAbsolute()) {
                return true;
            }

            $sourceBase = $source->getBaseUrl();
            $siteBase = (new UrlParser(APP_URL))->getBaseUrl();

            return $sourceBase !== null
                && $siteBase !== null
                && strcasecmp(rtrim($sourceBase, '/'), rtrim($siteBase, '/')) === 0;
        }

    }
