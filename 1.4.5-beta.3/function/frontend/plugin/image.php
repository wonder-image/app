<?php

    function srcSet(string $folder, string $filenameBase, string $extension = 'jpg', array $widths = [480, 960, 1440, 1920]): string
    {

        global $PATH;

        $srcset = [];

        foreach ($widths as $width) {
            $src = $PATH->upload."/$folder/{$filenameBase}-{$width}.{$extension}";
            $srcset[] = "{$src} {$width}w";
        }

        return implode(', ', $srcset);

    }

    function imgWebP(string $folder, string $filenameBase, int $defaultWidth, string $alt = '', string $class = 'bg bg-cover', array $widths = [480, 960, 1440, 1920]): string
    {

        global $PATH;

        $srcSet = srcSet($folder, $filenameBase, 'webp', $widths);

        return "<img 
                    src=\"{$PATH->upload}/$folder/{$filenameBase}-{$defaultWidth}.webp\"
                    srcset=\"$srcSet\"
                    sizes=\"100vw\"
                    class=\"{$class} no-interaction unselectable\"
                    alt=\"{$alt}\"
                    loading=\"lazy\"
                />";

    }