<?php

namespace Wonder\Data\Fields;

class Image extends File
{
    public string $type = 'image';

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->mimeType('image/*');
    }

    public function resizeToWidth(int $width): self
    {
        return $this->resize([['width' => $width]]);
    }

    public function resizeToWidths(array $widths): self
    {
        $resize = array_map(
            static fn (int $width) => ['width' => $width],
            $widths
        );

        return $this->resize($resize);
    }

    public function convertToWebp(bool $webp = true): self
    {
        return $this->webp($webp);
    }

    public function quality(int $quality): self
    {
        return $this->schema('quality', $quality);
    }

    public function responsive(?array $widths = null, int $quality = 80): self
    {
        $widths ??= defined('RESPONSIVE_IMAGE_SIZES')
            ? RESPONSIVE_IMAGE_SIZES
            : [120, 480, 620, 960, 1080, 1440, 1920, 2560];

        return $this->resizeToWidths($widths)
            ->quality($quality)
            ->convertToWebp(defined('RESPONSIVE_IMAGE_WEBP') ? (bool) RESPONSIVE_IMAGE_WEBP : true);
    }
}
