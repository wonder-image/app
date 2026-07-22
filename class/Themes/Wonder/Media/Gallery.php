<?php

    namespace Wonder\Themes\Wonder\Media;

    use Wonder\Themes\Concerns\HandlesMedia;

    class Gallery extends Media {

        use HandlesMedia;

        protected function renderMedia($class): string
        {
            $id          = $this->mediaId($class, 'gallery');
            $items       = $this->normalizeImages($class->getSchema('images') ?? []);
            $columns     = $class->getSchema('columns') ?? [ 'desktop' => 4, 'tablet' => 3, 'mobile' => 2 ];
            $gap         = $class->getSchema('gap') ?? 6;
            $format      = $class->getSchema('format') ?? 'h-fit';
            $download    = (bool) ($class->getSchema('download') ?? false);
            $previewSize = (int) ($class->getSchema('preview-size') ?? 480);
            $fullSize    = (int) ($class->getSchema('full-size') ?? max(RESPONSIVE_IMAGE_SIZES));

            $colDesktop = (int) $columns['desktop'];
            $colTablet  = (int) $columns['tablet'];
            $colMobile  = (int) $columns['mobile'];

            if (is_array($gap)) {
                $gapDesktop = $gap['desktop'] ?? 6;
                $gapTablet  = $gap['tablet'] ?? $gapDesktop;
                $gapMobile  = $gap['mobile'] ?? $gapTablet;
            } else {
                $gapDesktop = $gapTablet = $gapMobile = $gap;
            }

            // Bucket round-robin per device
            $buckets = [ 'desktop' => [], 'tablet' => [], 'mobile' => [] ];
            for ($i = 0; $i < $colDesktop; $i++) { $buckets['desktop'][$i] = []; }
            for ($i = 0; $i < $colTablet;  $i++) { $buckets['tablet'][$i]  = []; }
            for ($i = 0; $i < $colMobile;  $i++) { $buckets['mobile'][$i]  = []; }

            $d = $t = $m = 0;
            $hidden = "";

            foreach ($items as $item) {
                $buckets['desktop'][$d][] = $item;
                $buckets['tablet'][$t][]  = $item;
                $buckets['mobile'][$m][]  = $item;

                $d = ($d + 1) % $colDesktop;
                $t = ($t + 1) % $colTablet;
                $m = ($m + 1) % $colMobile;

                $full    = $this->imageUrl($item['src'], $fullSize);
                $caption = $item['alt'] !== '' ? " data-caption=\"" . $this->escape($item['alt']) . "\"" : '';
                $hidden .= "<a data-fancybox=\"$id\" data-src=\"$full\"$caption></a>";
            }

            $html  = "<div class='d-none'>$hidden</div>";
            $html .= "<div class='w-100 d-grid col-$colDesktop col-t-$colTablet col-p-$colMobile gap-$gapDesktop gap-t-$gapTablet gap-p-$gapMobile'>";
            $html .= $this->deviceColumns($id, $buckets['desktop'], $colDesktop, $gapDesktop, $format, $previewSize, 'tablet-none', false);
            $html .= $this->deviceColumns($id, $buckets['tablet'],  $colTablet,  $gapTablet,  $format, $previewSize, 'pc-none phone-none', false);
            $html .= $this->deviceColumns($id, $buckets['mobile'],  $colMobile,  $gapMobile,  $format, $previewSize, 'pc-none tablet-none', true);
            $html .= "</div>";

            $options = '{';
            if ($download) { $options .= "buttons: ['download', 'thumbs', 'close']"; }
            $options .= '}';

            $html .= "<script>Fancybox.bind('[data-fancybox=\"$id\"]', $options);</script>";

            return $html;
        }

        private function deviceColumns(string $id, array $buckets, int $cols, $gap, string $format, int $previewSize, string $visibility, bool $extraHFit): string
        {
            $html  = "";
            $extra = $extraHFit ? ' h-fit' : '';

            for ($i = 0; $i < $cols; $i++) {
                $html .= "<div class='w-100 $visibility$extra'><div class='w-100 d-grid col-1 gap-$gap'>";
                foreach ($buckets[$i] as $item) { $html .= $this->card($id, $item, $format, $previewSize); }
                $html .= "</div></div>";
            }

            return $html;
        }

        private function card(string $id, array $item, string $format, int $previewSize): string
        {
            if ($format === 'h-fit') {
                $img = $this->renderImage($item['src'], $item['alt'], $previewSize, 'natural');
                return "<a href='javascript:;' data-fancybox-trigger='$id' data-fancybox-index='{$item['position']}' class='col-1 h-fit'>$img</a>";
            }

            $img = $this->renderImage($item['src'], $item['alt'], $previewSize, 'cover');
            return "<a href='javascript:;' data-fancybox-trigger='$id' data-fancybox-index='{$item['position']}' class='col-1'><div class='f-$format o-hidden'>$img</div></a>";
        }

    }
