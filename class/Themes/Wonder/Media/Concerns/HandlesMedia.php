<?php

    namespace Wonder\Themes\Wonder\Media\Concerns;

    use Wonder\Elements\Media\Image;

    trait HandlesMedia
    {
        /**
         * Normalizza l'input immagini nella forma canonica.
         * ['src.jpg' => 'alt', ...]  oppure lista numerica ['a.jpg', ...] (alt = '').
         *
         * @return array<int, array{src:string, alt:string, position:int}>
         */
        protected function normalizeImages(array $images): array
        {
            $items = [];
            $position = 0;

            foreach ($images as $key => $value) {
                if (is_int($key)) {
                    $src = (string) $value;
                    $alt = '';
                } else {
                    $src = (string) $key;
                    $alt = (string) $value;
                }

                if ($src === '') { continue; }

                $items[] = [ 'src' => $src, 'alt' => $alt, 'position' => $position ];
                $position++;
            }

            return $items;
        }

        /**
         * Renderizza un'immagine via il builder Image (webp + srcset + skeleton).
         * $fit: 'cover' | 'contain' | 'natural'.
         */
        protected function renderImage(string $src, string $alt, int $size, string $fit = 'cover', bool $draggable = false): string
        {
            $img = Image::src($src)
                ->sizes(RESPONSIVE_IMAGE_SIZES)
                ->hasWebP()
                ->alt($alt)
                ->size($size)
                ->skeleton()
                ->loading();

            $draggable ? $img->notDraggable(false) : $img->notDraggable();

            if ($fit === 'contain') {
                $img->fitContain();
            } elseif ($fit === 'cover') {
                $img->fitCover();
            } else {
                $img->addClass('w-100');
            }

            return $img->render();
        }

        /** URL della variante alla size richiesta (per il data-src del lightbox). */
        protected function imageUrl(string $src, int $size): string
        {
            return Image::src($src)->size($size)->url();
        }

        /** Id esplicito (->id()) se presente, altrimenti generato. */
        protected function mediaId(mixed $class, string $prefix): string
        {
            $id = $class->getSchema('id');

            return (is_string($id) && $id !== '') ? $id : $prefix . '-' . bin2hex(random_bytes(4));
        }
    }
