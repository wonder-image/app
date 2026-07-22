<?php

    namespace Wonder\Themes\Bootstrap\Media;

    use Wonder\Themes\Concerns\HandlesMedia;
    use Wonder\App\Dependencies;

    class Gallery extends Media {

        use HandlesMedia;

        /** Mappa i formati "N-M" sugli aspect-ratio Bootstrap. */
        private const RATIOS = [
            '1-1'  => 'ratio-1x1',
            '4-3'  => 'ratio-4x3',
            '16-9' => 'ratio-16x9',
            '21-9' => 'ratio-21x9',
        ];

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

            $rowCols = "row-cols-$colMobile row-cols-md-$colTablet row-cols-xl-$colDesktop";
            $gutter  = $this->gutter($gap);

            // Lightbox + download: Fancybox caricato solo quando la gallery è in pagina.
            Dependencies::fancyapps();

            $cells = "";

            foreach ($items as $item) {
                $full    = $this->imageUrl($item['src'], $fullSize);
                $caption = $item['alt'] !== '' ? ' data-caption="' . $this->escape($item['alt']) . '"' : '';
                $thumb   = $this->thumb($item, $format, $previewSize);

                $cells .= "<div class='col'>"
                    . "<a href='" . $this->escape($full) . "' data-fancybox=\"$id\"$caption class='d-block rounded overflow-hidden'>$thumb</a>"
                    . "</div>";
            }

            $html  = "<div class='row $rowCols $gutter'>$cells</div>";

            $options = '{';
            if ($download) { $options .= "buttons: ['download', 'thumbs', 'close']"; }
            $options .= '}';

            $html .= "<script>window.addEventListener('load', function () { Fancybox.bind('[data-fancybox=\"$id\"]', $options); });</script>";

            return $html;
        }

        /** Anteprima della cella: altezza naturale (h-fit) o box con aspect-ratio + cover. */
        private function thumb(array $item, string $format, int $previewSize): string
        {
            // ratio nativo Bootstrap (ratio-1x1/4x3/16x9/21x9)
            if (isset(self::RATIOS[$format])) {
                $img = $this->renderImage($item['src'], $item['alt'], $previewSize, 'cover');
                return "<div class='ratio " . self::RATIOS[$format] . "'>$img</div>";
            }

            // ratio arbitrario "W-H" via custom property (.ratio usa --bs-aspect-ratio)
            if (preg_match('/^(\d+)-(\d+)$/', $format, $m) && (int) $m[1] > 0) {
                $pct = round((int) $m[2] / (int) $m[1] * 100, 4);
                $img = $this->renderImage($item['src'], $item['alt'], $previewSize, 'cover');
                return "<div class='ratio' style='--bs-aspect-ratio: $pct%'>$img</div>";
            }

            // altezza naturale (h-fit o formato sconosciuto)
            return $this->renderImage($item['src'], $item['alt'], $previewSize, 'natural');
        }

        /** Gutter Bootstrap (g-0..g-5) da uno spacing scalare o responsive [desktop,tablet,mobile]. */
        private function gutter(int|array $gap): string
        {
            $clamp = static fn ($v): int => max(0, min(5, (int) $v));

            if (is_array($gap)) {
                $d = $clamp($gap['desktop'] ?? 6);
                $t = $clamp($gap['tablet'] ?? $gap['desktop'] ?? 6);
                $m = $clamp($gap['mobile'] ?? $gap['tablet'] ?? $gap['desktop'] ?? 6);

                return "g-$m g-md-$t g-xl-$d";
            }

            return 'g-' . $clamp($gap);
        }

    }
