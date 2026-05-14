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
            $sourceImage = $manager->read($this->imagePath);
            $sourceWidth = (int) $sourceImage->width();
            $sizes = array_values(array_unique(array_filter(array_map(
                static fn ($size): int => (int) $size,
                $this->sizes
            ), static fn (int $size): bool => $size > 0)));

            sort($sizes);
            $baseWebpPath = null;

            if ($this->webp && $this->extension !== 'webp') {
                $webpPath = sprintf('%s%s.webp', $this->directory, $this->imageName);
                $webpImage = clone $sourceImage;
                $webpImage->toWebp(80)->save($webpPath);
                $baseWebpPath = $webpPath;
            }
            
            foreach ($sizes as $size) {
                $targetPath = sprintf('%s%s-%d.%s', $this->directory, $this->imageName, $size, $this->extension);

                if ($size >= $sourceWidth) {
                    @copy($this->imagePath, $targetPath);

                    if ($baseWebpPath !== null) {
                        @copy($baseWebpPath, sprintf('%s%s-%d.webp', $this->directory, $this->imageName, $size));
                    }

                    continue;
                }

                $resizedImage = clone $sourceImage;
                $resizedImage->scaleDown($size);
                $resizedImage->save($targetPath);

                if ($baseWebpPath !== null) {
                    $webpPath = sprintf('%s%s-%d.webp', $this->directory, $this->imageName, $size);
                    $webpImage = clone $resizedImage;
                    $webpImage->toWebp(80)->save($webpPath);
                }

            }

        }

    }
