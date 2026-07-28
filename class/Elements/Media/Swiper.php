<?php

    namespace Wonder\Elements\Media;

    use InvalidArgumentException;
    use Wonder\Elements\Concerns\HasMediaFit;
    use Wonder\Elements\Concerns\ParsesRatio;

    class Swiper extends Media {

        use HasMediaFit, ParsesRatio;

        public function __construct( array $images = [] )
        {
            $this->schema('mode', 'images');
            $this->schema('images', $images);
        }

        public static function make( array $images = [] ): self
        {
            return new self($images);
        }

        public function images( array $images ): self
        {
            $this->schema('mode', 'images');
            $this->schema('slides', []);

            return $this->schema('images', $images);
        }

        /**
         * Usa contenuti HTML trusted o oggetti renderizzabili al posto delle immagini.
         * Il renderer aggiunge automaticamente il wrapper .swiper-slide.
         */
        public function slides( array $slides ): self
        {
            $this->schema('mode', 'slides');
            $this->schema('images', []);
            $this->schema('thumbnails', false);
            $this->schema('zoom', false);
            $this->schema('lightbox', false);
            $this->schema('download', false);

            return $this->schema('slides', $slides);
        }

        public function thumbnails( bool $on = true ): self
        {
            return $this->schema('thumbnails', $on);
        }

        public function zoom( bool $on = true ): self
        {
            if ($on) { $this->schema('lightbox', false); }
            return $this->schema('zoom', $on);
        }

        public function lightbox( ?string $group = null ): self
        {
            $this->schema('zoom', false);
            if ($group !== null) { $this->schema('lightbox-group', $group); }
            return $this->schema('lightbox', true);
        }

        public function loop( bool $on = true ): self          { return $this->schema('loop', $on); }
        public function autoplay( int $delayMs ): self         { return $this->schema('autoplay', $delayMs); }
        public function navigation( bool $on = true ): self    { return $this->schema('navigation', $on); }
        public function pagination( bool $on = true ): self    { return $this->schema('pagination', $on); }
        public function slidesPerView( int|float $n ): self    { return $this->schema('slides-per-view', $n); }
        public function spaceBetween( int $px ): self          { return $this->schema('space-between', $px); }
        public function autoHeight( bool $on = true ): self    { return $this->schema('auto-height', $on); }
        public function keyboard( bool $on = true ): self      { return $this->schema('keyboard', $on); }
        public function watchOverflow( bool $on = true ): self { return $this->schema('watch-overflow', $on); }
        public function thumbsPerView( int $n ): self          { return $this->schema('thumbs-per-view', $n); }
        public function download( bool $on = true ): self      { return $this->schema('download', $on); }
        public function size( int $px ): self                  { return $this->schema('size', $px); }
        public function thumbsSize( int $px ): self            { return $this->schema('thumbs-size', $px); }
        public function fullSize( int $px ): self              { return $this->schema('full-size', $px); }

        public function ratio( string $ratio ): self
        {
            return $this->schema('image-ratio', $this->normalizeRatio($ratio));
        }

        public function thumbsRatio( string $ratio ): self
        {
            return $this->schema('thumbs-ratio', $this->normalizeRatio($ratio));
        }

        public function slideClass( string|array $classes ): self
        {
            return $this->schema('slide-classes', $this->normalizeClasses($classes));
        }

        public function thumbSlideClass( string|array $classes ): self
        {
            return $this->schema('thumb-slide-classes', $this->normalizeClasses($classes));
        }

        /**
         * Configurazione responsive mobile-first di Swiper.js.
         *
         * @param array<int|string, array<string, mixed>> $breakpoints
         */
        public function breakpoints( array $breakpoints ): self
        {
            $normalized = [];

            foreach ($breakpoints as $minimumWidth => $options) {
                $isNumericKey = is_int($minimumWidth)
                    || (is_string($minimumWidth) && ctype_digit($minimumWidth));

                if (!$isNumericKey || (int) $minimumWidth <= 0) {
                    throw new InvalidArgumentException('Ogni breakpoint Swiper deve essere un intero positivo.');
                }

                if (!is_array($options)) {
                    throw new InvalidArgumentException('Ogni configurazione breakpoint Swiper deve essere un array.');
                }

                if (
                    isset($options['slidesPerView'])
                    && (!is_numeric($options['slidesPerView']) || (float) $options['slidesPerView'] <= 0)
                ) {
                    throw new InvalidArgumentException('slidesPerView deve essere un numero maggiore di zero.');
                }

                if (
                    isset($options['spaceBetween'])
                    && (!is_numeric($options['spaceBetween']) || (float) $options['spaceBetween'] < 0)
                ) {
                    throw new InvalidArgumentException('spaceBetween non puo essere negativo.');
                }

                $normalized[(int) $minimumWidth] = $options;
            }

            ksort($normalized, SORT_NUMERIC);

            return $this->schema('breakpoints', $normalized);
        }

        /**
         * @return array{0:int,1:int}
         */
        private function normalizeRatio(string $ratio): array
        {
            return self::parseRatio($ratio)
                ?? throw new InvalidArgumentException("Rapporto d'aspetto non valido: {$ratio}");
        }

        /**
         * @return string[]
         */
        private function normalizeClasses(string|array $classes): array
        {
            $values = is_array($classes) ? $classes : [$classes];
            $tokens = [];

            foreach ($values as $value) {
                if (!is_scalar($value)) {
                    continue;
                }

                foreach (preg_split('/\s+/', trim((string) $value)) ?: [] as $token) {
                    if ($token !== '') {
                        $tokens[] = $token;
                    }
                }
            }

            return array_values(array_unique($tokens));
        }

    }
