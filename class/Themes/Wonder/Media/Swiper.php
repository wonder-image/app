<?php

    namespace Wonder\Themes\Wonder\Media;

    use Wonder\Themes\Wonder\Component;
    use Wonder\Themes\Wonder\Media\Concerns\HandlesMedia;

    class Swiper extends Component {

        use HandlesMedia;

        public function render($class): string
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

            $slides = "";
            $thumbSlides = "";
            $hidden = "";
            $i = 0;

            foreach ($items as $item) {

                if ($zoom) {
                    $img = $this->renderImage($item['src'], $item['alt'], $size, $fit, true);
                    $slides .= "<div class='swiper-slide w-100'><div class='f-panzoom w-100'><div class='f-1-1 f-panzoom__viewport w-100 o-hidden'><div class='f-panzoom__content p-a top start w-100 h-100'>$img</div></div></div></div>";
                } elseif ($lightbox) {
                    $img = $this->renderImage($item['src'], $item['alt'], $size, $fit);
                    $slides .= "<a class='swiper-slide o-hidden' data-fancybox-trigger='$group' data-fancybox-index='$i'>$img</a>";
                    $full    = $this->imageUrl($item['src'], $fullSize);
                    $caption = $item['alt'] !== '' ? " data-caption=\"" . $this->escape($item['alt']) . "\"" : '';
                    $hidden .= "<a data-fancybox=\"$group\" data-src=\"$full\"$caption></a>";
                } else {
                    $img = $this->renderImage($item['src'], $item['alt'], $size, $fit);
                    $slides .= "<div class='swiper-slide o-hidden'>$img</div>";
                }

                if ($thumbs) {
                    $thumbImg = $this->renderImage($item['src'], $item['alt'], $thumbSize, 'cover');
                    $thumbSlides .= "<div class='swiper-slide o-hidden'>$thumbImg</div>";
                }

                $i++;
            }

            $html = "";

            if ($lightbox && $hidden !== '') { $html .= "<div class='d-none'>$hidden</div>"; }

            $html .= "<div id='$id' class='swiper w-100'><div class='swiper-wrapper'>$slides</div>";
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
