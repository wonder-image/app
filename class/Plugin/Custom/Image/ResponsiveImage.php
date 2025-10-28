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

    }