<?php

    namespace Wonder\Plugin\Custom\Image;

    use Intervention\Image\ImageManager;
    use Intervention\Image\Drivers\Gd\Driver;
    use Wonder\App\Path;
    use ErrorException;

    class ResponsiveImage
    {

        public string $imagePath, $imageName, $extension, $directory, $directoryUrl;
        public array $sizes = RESPONSIVE_IMAGE_SIZES;
        public bool $webp = RESPONSIVE_IMAGE_WEBP;

        public function __construct($imagePath)
        {

            if (!file_exists($imagePath)) {
                throw new ErrorException("L'immagine $imagePath non esiste!", 751);
            }
            
            $this->imagePath = $imagePath;
            $this->extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            $this->imageName = pathinfo($imagePath, PATHINFO_FILENAME);
            $this->directory = rtrim(dirname($imagePath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $this->directoryUrl = (new Path)->site.str_replace(__DIR__, '', rtrim(dirname($imagePath), DIRECTORY_SEPARATOR)) . DIRECTORY_SEPARATOR;
            
            sort($this->sizes);

            if (!in_array($this->extension, ['jpg','jpeg','png','webp'])) {
                throw new ErrorException("Formato {$this->extension} non è supportato!", 752);
            }

        }

        public static function path($imagePath): self
        {

            return new self($imagePath);

        }

        public function generate()
        {

            $manager = new ImageManager( new Driver() );

            if ($this->webp && $this->extension !== 'webp') {
                $webpPath = sprintf('%s%s.webp', $this->directory, $this->imageName);
                $webpImage = $manager->read($this->imagePath);
                $webpImage->toWebp(80)->save($webpPath);
            }
            
            foreach ($this->sizes as $size) {

                $resizedImage = $manager->read($this->imagePath);
                $resizedImage->scaleDown($size);

                $targetPath = sprintf('%s%s-%d.%s', $this->directory, $this->imageName, $size, $this->extension);
                $resizedImage->save($targetPath);

                if ($this->webp && $this->extension !== 'webp') {
                    $webpPath = sprintf('%s%s-%d.webp', $this->directory, $this->imageName, $size);
                    $resizedImage->toWebp(80)->save($webpPath);
                }

            }

        }

        private function getSrcSet(?string $extension = null): string
        {

            $srcset = [];
            $extension ??= $this->extension;

            foreach ($this->sizes as $size) {
                $src = sprintf('%s%s-%d.%s', $this->directoryUrl, $this->imageName, $size, $extension);
                $srcset[] = "{$src} {$size}w";
            }

            return implode(', ', $srcset);

        }

        private function getMimeType(): string
        {

            switch ($this->extension) {
                case 'jpg':
                case 'jpeg':
                    return 'image/jpeg';
                case 'png':
                    return 'image/png';
                case 'webp':
                    return 'image/webp';
                default:
                    return 'image/'.$this->extension;
            }

        }

        public function getSizes($base): string
        {

            $mobile = 768;
            $tablet = 992;

            $max = max($this->sizes);
            $ratio = $base / $max;
            $vw = round($ratio * 100);

            // Se il file base è molto piccolo (< max/3), trattalo da thumbnail
            if ($ratio <= 0.33) {
                return "(max-width: {$mobile}px) 50vw, (max-width: {$tablet}px) 33vw, 25vw";
            }

            // Se è medio (≈ metà del massimo)
            if ($ratio <= 0.66) {
                return "(max-width: {$mobile}px) 100vw, (max-width: {$tablet}px) 75vw, {$vw}vw";
            }

            // Se è grande ma non full
            if ($ratio < 1) {
                return "(max-width: {$mobile}px) 100vw, (max-width: {$tablet}px) 90vw, {$vw}vw";
            }

            // Full width (base == max)
            return "100vw";

        }

        public function img($alt, $defaultSize = 960, $class = 'bg bg-cover'): string
        {

            $image = sprintf('%s%s-%d.%s', $this->directoryUrl, $this->imageName, $defaultSize, $this->extension);
            $srcSet = $this->getSrcSet();
            $sizes = $this->getSizes($defaultSize);
            $alt = htmlspecialchars($alt);

            return "<img src=\"$image\" srcset=\"$srcSet\" sizes=\"$sizes\" class=\"{$class} no-interaction unselectable\" alt=\"{$alt}\" loading=\"lazy\"/>";

        }

        public function picture($alt, $defaultSize = 960, $class = 'bg bg-cover'): string
        {

            $image = sprintf('%s%s-%d.%s', $this->directoryUrl, $this->imageName, $defaultSize, $this->webp && $this->extension != 'webp' ? 'webp' : $this->extension);
            $srcSet = $this->getSrcSet();
            $sizes = $this->getSizes($defaultSize);
            $alt = htmlspecialchars($alt);

            $sources = [];

            if ($this->webp && $this->extension !== 'webp') {
                $webpSrcSet = $this->getSrcSet('webp');
                $sources[] = "<source type=\"image/webp\" srcset=\"{$webpSrcSet}\" sizes=\"{$sizes}\">";
            }

            $mimeType = $this->getMimeType();
            $sources[] = "<source type=\"{$mimeType}\" srcset=\"{$srcSet}\" sizes=\"{$sizes}\">";

            $sourcesHtml = implode("\n", $sources);

            return "<picture>
                        {$sourcesHtml}
                        <img src=\"{$image}\" class=\"$class no-interaction unselectable\" alt=\"{$alt}\" loading=\"lazy\" />
                    </picture>";

        }

    }


    class Image {

        public $url, $alt;

        public function __construct($url) {
            
        }

        public function url($url): self
        {   

            return new self($url);

        }

        public function alt($alt): self
        {

            return $this;
            
        }

        public function class(): static
        {
            return $this;
        }

    }