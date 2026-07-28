<?php

    namespace Wonder\Themes\Wonder\Media;

    use Wonder\Themes\Concerns\HandlesMedia;

    class Swiper extends Media {

        use HandlesMedia;

        protected function renderMedia($class): string
        {
            $id       = $this->mediaId($class, 'swiper');
            $contentMode = ($class->getSchema('mode') ?? 'images') === 'slides';
            $items    = $contentMode
                ? ($class->getSchema('slides') ?? [])
                : $this->normalizeImages($class->getSchema('images') ?? []);

            $thumbs   = !$contentMode && (bool) ($class->getSchema('thumbnails') ?? false);
            $zoom     = !$contentMode && (bool) ($class->getSchema('zoom') ?? false);
            $lightbox = !$contentMode && (bool) ($class->getSchema('lightbox') ?? false);
            $group    = $class->getSchema('lightbox-group') ?? ($id . '-lightbox');

            $size      = (int) ($class->getSchema('size') ?? 1440);
            $thumbSize = (int) ($class->getSchema('thumbs-size') ?? 240);
            $fullSize  = (int) ($class->getSchema('full-size') ?? max(RESPONSIVE_IMAGE_SIZES));
            $fit       = ($class->getSchema('fit-contain') ?? false) ? 'contain' : 'cover';
            $imageRatio = !$contentMode && is_array($class->getSchema('image-ratio'))
                ? $class->getSchema('image-ratio')
                : null;
            $thumbsRatio = !$contentMode && is_array($class->getSchema('thumbs-ratio'))
                ? $class->getSchema('thumbs-ratio')
                : null;
            $slideClasses = $class->getSchema('slide-classes') ?? [];
            $thumbSlideClasses = $class->getSchema('thumb-slide-classes') ?? [];
            $imageRatioAttributes = $this->mediaRatioAttributes($imageRatio);
            $thumbsRatioAttributes = $this->mediaRatioAttributes($thumbsRatio);
            $imageRatioSuffix = $imageRatioAttributes !== '' ? " $imageRatioAttributes" : '';
            $thumbsRatioSuffix = $thumbsRatioAttributes !== '' ? " $thumbsRatioAttributes" : '';

            $slides = "";
            $thumbSlides = "";
            $hidden = "";
            $i = 0;

            foreach ($items as $item) {

                if ($contentMode) {
                    $mainClasses = $this->escape($this->mediaClasses(['swiper-slide'], $slideClasses));
                    $slides .= "<div class='$mainClasses'>".$this->renderSlideContent($item, 'wonder')."</div>";
                    continue;
                }

                if ($zoom) {
                    $img = $this->renderImage($item['src'], $item['alt'], $size, $fit, true);
                    $mainClasses = $this->escape($this->mediaClasses(['swiper-slide', 'w-100'], $slideClasses));
                    $viewportDefaults = $imageRatio === null
                        ? ['f-1-1', 'f-panzoom__viewport', 'w-100', 'o-hidden']
                        : ['f-panzoom__viewport', 'w-100', 'o-hidden'];
                    $viewportClasses = $this->escape($this->mediaClasses($viewportDefaults, []));
                    $slides .= "<div class='$mainClasses'><div class='f-panzoom w-100'><div class='$viewportClasses'$imageRatioSuffix><div class='f-panzoom__content p-a top start w-100 h-100'>$img</div></div></div></div>";
                } elseif ($lightbox) {
                    $img = $this->renderImage($item['src'], $item['alt'], $size, $fit);
                    $mainClasses = $this->escape($this->mediaClasses(['swiper-slide', 'o-hidden'], $slideClasses));
                    $slides .= "<a class='$mainClasses' data-fancybox-trigger='$group' data-fancybox-index='$i'$imageRatioSuffix>$img</a>";
                    $full    = $this->imageUrl($item['src'], $fullSize);
                    $caption = $item['alt'] !== '' ? " data-caption=\"" . $this->escape($item['alt']) . "\"" : '';
                    $hidden .= "<a data-fancybox=\"$group\" data-src=\"$full\"$caption></a>";
                } else {
                    $img = $this->renderImage($item['src'], $item['alt'], $size, $fit);
                    $mainClasses = $this->escape($this->mediaClasses(['swiper-slide', 'o-hidden'], $slideClasses));
                    $slides .= "<div class='$mainClasses'$imageRatioSuffix>$img</div>";
                }

                if ($thumbs) {
                    $thumbImg = $this->renderImage($item['src'], $item['alt'], $thumbSize, 'cover');
                    $thumbClasses = $this->escape($this->mediaClasses(['swiper-slide', 'o-hidden'], $thumbSlideClasses));
                    $thumbSlides .= "<div class='$thumbClasses'$thumbsRatioSuffix>$thumbImg</div>";
                }

                $i++;
            }

            $html = "";

            if ($lightbox && $hidden !== '') { $html .= "<div class='d-none'>$hidden</div>"; }

            $rootClasses = $this->escape($this->mediaRootClasses($class, ['swiper', 'w-100']));
            $rootAttributes = $this->mediaRootAttributes($class);
            $html .= "<div id='$id' class='$rootClasses'"
                .($rootAttributes !== '' ? " $rootAttributes" : '')
                ."><div class='swiper-wrapper'>$slides</div>";
            if ($class->getSchema('pagination')) { $html .= "<div class='swiper-pagination'></div>"; }
            if ($class->getSchema('navigation')) { $html .= "<div class='swiper-button-next'></div><div class='swiper-button-prev'></div>"; }
            $html .= "</div>";

            if ($thumbs) { $html .= "<div id='$id-thumbs' class='swiper w-100 o-hidden mt-2'><div class='swiper-wrapper'>$thumbSlides</div></div>"; }

            $html .= $this->script($class, $id, $group, $thumbs, $zoom, $lightbox);

            return $html;
        }

        private function script($class, string $id, string $group, bool $thumbs, bool $zoom, bool $lightbox): string
        {
            $lines = [ "window.addEventListener('loaded', function () {" ];

            if ($thumbs) {
                $tpv = (int) ($class->getSchema('thumbs-per-view') ?? 4);
                $lines[] = "  var thumbs = new Swiper('#$id-thumbs', { spaceBetween: 8, slidesPerView: $tpv, freeMode: true, watchSlidesProgress: true });";
            }

            $opts = [ 'grabCursor: true', 'watchSlidesProgress: true' ];
            $opts[] = 'slidesPerView: ' . ($class->getSchema('slides-per-view') ?? 1);
            $opts[] = 'spaceBetween: ' . (int) ($class->getSchema('space-between') ?? 0);
            if ($class->getSchema('auto-height')) { $opts[] = 'autoHeight: true'; }
            if ($class->getSchema('keyboard'))    { $opts[] = 'keyboard: { enabled: true }'; }
            if ($class->getSchema('watch-overflow') !== null) {
                $opts[] = 'watchOverflow: '.($class->getSchema('watch-overflow') ? 'true' : 'false');
            }
            if ($class->getSchema('breakpoints')) {
                $opts[] = 'breakpoints: ' . $this->encodeSwiperBreakpoints($class->getSchema('breakpoints'));
            }
            if ($class->getSchema('loop'))       { $opts[] = 'loop: true'; }
            if ($class->getSchema('autoplay'))   { $opts[] = 'autoplay: { delay: ' . (int) $class->getSchema('autoplay') . ' }'; }
            if ($class->getSchema('pagination')) { $opts[] = "pagination: { el: '#$id .swiper-pagination', clickable: true }"; }
            if ($class->getSchema('navigation')) { $opts[] = "navigation: { nextEl: '#$id .swiper-button-next', prevEl: '#$id .swiper-button-prev' }"; }
            if ($thumbs)                         { $opts[] = 'thumbs: { swiper: thumbs }'; }

            $lines[] = "  var main = new Swiper('#$id', { " . implode(', ', $opts) . " });";

            if ($zoom) {
                $lines[] = "  document.querySelectorAll('#$id .f-panzoom').forEach(function (el) { new Panzoom(el, { click: 'toggleZoom', dblClick: 'toggleMax', panMode: 'mousemove' }); });";
            }

            if ($lightbox) {
                $options = '{';
                if ($class->getSchema('download')) { $options .= "buttons: ['download', 'thumbs', 'close']"; }
                $options .= '}';
                $lines[] = "  Fancybox.bind('[data-fancybox=\"$group\"]', $options);";
            }

            $lines[] = "});";

            return "<script>\n" . implode("\n", $lines) . "\n</script>";
        }
    }
