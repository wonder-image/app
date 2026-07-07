<?php

    namespace Wonder\Elements\Media;

    use Wonder\Elements\Component;
    use Wonder\Elements\Concerns\{ Renderer };

    class Swiper extends Component {

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
        public function thumbsPerView( int $n ): self          { return $this->schema('thumbs-per-view', $n); }
        public function download( bool $on = true ): self      { return $this->schema('download', $on); }
        public function size( int $px ): self                  { return $this->schema('size', $px); }
        public function thumbsSize( int $px ): self            { return $this->schema('thumbs-size', $px); }
        public function fullSize( int $px ): self              { return $this->schema('full-size', $px); }

        public function fitCover( bool $on = true ): self
        {
            if ($on) { $this->schema('fit-contain', false); }
            return $this->schema('fit-cover', $on);
        }

        public function fitContain( bool $on = true ): self
        {
            if ($on) { $this->schema('fit-cover', false); }
            return $this->schema('fit-contain', $on);
        }

    }
