<?php

    namespace Wonder\Elements\Media;

    use Wonder\Elements\Component;

    use Wonder\Elements\Concerns\{ Renderer };

    class Image extends Component {

        use Renderer;

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

        public function fitCover( bool $cover = true ): self
        {

            $this->fitContain(false);
            return $this->schema('fit-cover', $cover);

        }

        public function fitContain( bool $contain = true ): self
        {

            $this->fitCover(false);
            return $this->schema('fit-contain', $contain);

        }

        public function skeleton( bool $skeleton = true ): self
        {

            $this->fitCover(false);
            return $this->schema('skeleton', $skeleton);

        }


        public function notDraggable( bool $draggable = true ): self
        {

            return $this->schema('draggable', $draggable ? false : true);

        }

        public function loading( string $loading = 'lazy' )
        {

            return $this->attr('loading', $loading );

        }

    }
