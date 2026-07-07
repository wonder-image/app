<?php

    namespace Wonder\Elements\Media;

    use Wonder\Elements\Component;
    use Wonder\Elements\Concerns\{ Renderer };

    class Gallery extends Component {

        use Renderer;

        public function __construct( array $images = [] )
        {
            $this->schema('images', $images);
        }

        public static function make( array $images = [] ): self
        {
            return new self($images);
        }

        public function images( array $images ): self
        {
            return $this->schema('images', $images);
        }

        public function columns( int $desktop = 4, int $tablet = 3, int $mobile = 2 ): self
        {
            return $this->schema('columns', [ 'desktop' => $desktop, 'tablet' => $tablet, 'mobile' => $mobile ]);
        }

        public function gap( int|array $gap = 6 ): self
        {
            return $this->schema('gap', $gap);
        }

        public function format( string $format = 'h-fit' ): self
        {
            return $this->schema('format', $format);
        }

        public function download( bool $on = true ): self
        {
            return $this->schema('download', $on);
        }

        public function size( int $px ): self
        {
            return $this->schema('preview-size', $px);
        }

        public function fullSize( int $px ): self
        {
            return $this->schema('full-size', $px);
        }

    }
