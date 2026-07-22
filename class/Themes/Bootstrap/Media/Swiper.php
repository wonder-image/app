<?php

    namespace Wonder\Themes\Bootstrap\Media;

    use Wonder\Themes\Concerns\HandlesMedia;
    use Wonder\App\Dependencies;

    class Swiper extends Media {

        use HandlesMedia;

        protected function renderMedia($class): string
        {
            $id       = $this->mediaId($class, 'swiper');
            $items    = $this->normalizeImages($class->getSchema('images') ?? []);

            $thumbs   = (bool) ($class->getSchema('thumbnails') ?? false);
            $zoom     = (bool) ($class->getSchema('zoom') ?? false);
            $lightbox = (bool) ($class->getSchema('lightbox') ?? false);
            $group    = $class->getSchema('lightbox-group') ?? ($id . '-lightbox');

            $size      = (int) ($class->getSchema('size') ?? 1440);
            $thumbSize = (int) ($class->getSchema('thumbs-size') ?? 240);
            $fullSize  = (int) ($class->getSchema('full-size') ?? max(RESPONSIVE_IMAGE_SIZES));
            $fit       = ($class->getSchema('fit-contain') ?? false) ? 'contain' : 'cover';

            // Swiper.js sempre; Fancybox/Panzoom solo se zoom o lightbox: caricati on-demand.
            Dependencies::swiper();
            if ($zoom || $lightbox) { Dependencies::fancyapps(); }

            $slides = "";
            $thumbSlides = "";

            foreach ($items as $item) {

                if ($zoom) {
                    $img = $this->renderImage($item['src'], $item['alt'], $size, $fit, true);
                    $slides .= "<div class='swiper-slide w-100'><div class='f-panzoom w-100'><div class='f-panzoom__viewport ratio ratio-1x1 w-100 overflow-hidden'><div class='f-panzoom__content position-absolute top-0 start-0 w-100 h-100'>$img</div></div></div></div>";
                } elseif ($lightbox) {
                    $img     = $this->renderImage($item['src'], $item['alt'], $size, $fit);
                    $full    = $this->imageUrl($item['src'], $fullSize);
                    $caption = $item['alt'] !== '' ? ' data-caption="' . $this->escape($item['alt']) . '"' : '';
                    $slides .= "<a class='swiper-slide overflow-hidden' data-fancybox=\"$group\" href='" . $this->escape($full) . "'$caption>$img</a>";
                } else {
                    $img = $this->renderImage($item['src'], $item['alt'], $size, $fit);
                    $slides .= "<div class='swiper-slide overflow-hidden'>$img</div>";
                }

                if ($thumbs) {
                    $thumbImg = $this->renderImage($item['src'], $item['alt'], $thumbSize, 'cover');
                    $thumbSlides .= "<div class='swiper-slide overflow-hidden'>$thumbImg</div>";
                }

            }

            $html  = "<div id='$id' class='swiper w-100 ratio ratio-16x9 img-thumbnail rounded'><div class='position-absolute top-0 swiper-wrapper w-100 h-100 swiper-wrapper'>$slides</div>";
            if ($class->getSchema('pagination')) { $html .= "<div class='swiper-pagination' style='--swiper-theme-color: var(--bs-dark);'></div>"; }
            if ($class->getSchema('navigation')) { $html .= "<div class='swiper-button-next' style='--swiper-navigation-size: 25px;--swiper-theme-color: var(--bs-dark);'></div><div class='swiper-button-prev' style='--swiper-navigation-size: 25px;--swiper-theme-color: var(--bs-dark);'></div>"; }
            $html .= "</div>";

            if ($thumbs) { $html .= "<div id='$id-thumbs' class='swiper w-100 overflow-hidden mt-2'><div class='swiper-wrapper'>$thumbSlides</div></div>"; }

            $html .= $this->script($class, $id, $group, $thumbs, $zoom, $lightbox);

            return $html;
        }

        private function script($class, string $id, string $group, bool $thumbs, bool $zoom, bool $lightbox): string
        {
            $lines = [ "window.addEventListener('load', function () {" ];

            if ($thumbs) {
                $tpv = (int) ($class->getSchema('thumbs-per-view') ?? 4);
                $lines[] = "  var thumbs = new Swiper('#$id-thumbs', { spaceBetween: 8, slidesPerView: $tpv, freeMode: true, watchSlidesProgress: true });";
            }

            $opts = [ 'grabCursor: true', 'watchSlidesProgress: true' ];
            $opts[] = 'slidesPerView: ' . ($class->getSchema('slides-per-view') ?? 1);
            $opts[] = 'spaceBetween: ' . (int) ($class->getSchema('space-between') ?? 0);
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
