<?php

    namespace Wonder\Themes\Wonder\Media;
    
    use Wonder\Themes\Wonder\Component;

    use Wonder\App\Path;

    use RuntimeException;

    class Image extends Component {

        public string $src, $imageName, $extension, $mimeType, $directory, $directoryUrl;
        public ?int $defaultSize;
        public ?array $sizes;
        public ?string $attributes;
        public bool $webp;

        public function render($class): string
        {

            $this->init($class);

            if ($this->webp && $this->extension != 'webp') {
                return $this->renderPicture();
            } else {
                return $this->renderImg();
            }

        }

        public function renderImg(): string
        {

            $sizes = $this->renderSizes();
            $srcSet = $this->renderSrcSet();

            return "<img src=\"{$this->src}\" srcset=\"$srcSet\" sizes=\"$sizes\" {$this->attributes} />";

        }

        public function renderPicture(): string
        {

            $source1 = $this->renderSource('webp');
            $source2 = $this->renderSource();

            $image = "<img src=\"{$this->src}\" {$this->attributes} />";

            return "<picture>\n$source1\n$source2\n$image\n</picture>";

        }

        public function renderSource($extension = null): string
        {

            $sizes = $this->renderSizes();
            $srcSet = $this->renderSrcSet($extension);
            $mimeType = $this->getMimeType($extension);

            $sources[] = "<source type=\"{$mimeType}\" srcset=\"{$srcSet}\" sizes=\"{$sizes}\">";

            return implode("\n", $sources);

        }

        public function init($class) 
        {

            $this->schema = $class->schema;

            $src = $this->getSchema('src');
            if ($src === null) {
                throw new RuntimeException("Parametro src mancante.");
            }

            $this->src = rtrim($src);
            $this->webp = $this->getSchema('webp') ?? false;

            $pathInfo = pathinfo($this->src);

            $this->extension = strtolower($pathInfo['extension']);
            $this->mimeType = $this->getMimeType();
            $this->imageName = $pathInfo['filename'];
            $this->directory = str_replace((new Path())->site, '', $pathInfo['dirname']);
            $this->directoryUrl = (new Path())->site.$this->directory.DIRECTORY_SEPARATOR;

            $this->defaultSize = $this->getSchema('default-size') ?? null;
            $this->sizes = $this->getSchema('sizes') ?? [];

            if ($this->defaultSize != null) {
                $this->src = sprintf('%s%s-%d.%s', $this->directoryUrl, $this->imageName, $this->defaultSize, $this->extension);
            }

            if ($this->getSchema('fit-cover') == true) { $class->addClass('bg bg-cover'); }
            if ($this->getSchema('fit-contain') == true) { $class->addClass('bg bg-contain'); }
            if ($this->getSchema('skeleton') == true) { $class->addClass('skeleton'); }
            if ($this->getSchema('draggable') == false) { $class->addClass('no-interaction unselectable'); }

            $this->attributes = $this->renderAttributes($class->getSchema('attributes'));

        }

        public function getMimeType($extension = null): string
        {

            $extension ??= $this->extension;

            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    return 'image/jpeg';
                case 'png':
                    return 'image/png';
                case 'webp':
                    return 'image/webp';
                default:
                    return 'image/'.$extension;
            }

        }

        public function renderSrcSet(?string $extension = null): string 
        {
            
            $srcset = [];
            $extension ??= $this->extension;

            foreach ($this->sizes as $size) {
                $src = sprintf('%s%s-%d.%s', $this->directoryUrl, $this->imageName, $size, $extension);
                $srcset[] = "{$src} {$size}w";
            }

            return implode(', ', $srcset);

        }

        public function renderSizes(): string
        {
            $mobile = 768;
            $tablet = 992;

            if (!empty($this->sizes) && !empty($this->defaultSize)) {
                    
                $max = max($this->sizes);
                $ratio = $this->defaultSize / $max;
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
                
            }

            // Full width (base == max)
            return "100vw";

        }


    }