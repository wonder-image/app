<?php

    namespace Wonder\Themes\Concerns;

    use ReflectionMethod;
    use Stringable;
    use Wonder\Elements\Media\Image;

    trait HandlesMedia
    {
        use HasAttributes;

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

        /**
         * Renderizza il contenuto interno di una slide generica.
         *
         * Le stringhe sono HTML trusted e non vengono escapate. Gli oggetti che
         * espongono render() ricevono il tema esplicito del renderer, se previsto
         * dalla loro firma.
         */
        protected function renderSlideContent(mixed $slide, string $theme): string
        {
            if (is_object($slide) && method_exists($slide, 'render')) {
                $method = new ReflectionMethod($slide, 'render');

                if (!$method->isPublic()) {
                    return '';
                }

                $rendered = $method->getNumberOfParameters() > 0
                    ? $slide->render($theme)
                    : $slide->render();

                return is_string($rendered) || $rendered instanceof Stringable
                    ? (string) $rendered
                    : '';
            }

            if (is_string($slide) || is_int($slide) || is_float($slide) || $slide instanceof Stringable) {
                return (string) $slide;
            }

            return '';
        }

        /**
         * Combina le classi strutturali del renderer con quelle fluenti del componente.
         *
         * @param string[] $defaults
         */
        protected function mediaRootClasses(mixed $class, array $defaults): string
        {
            $raw = $class->getSchema('attributes')['class'] ?? [];

            return $this->mediaClasses($defaults, $raw);
        }

        /**
         * Unisce classi strutturali e classi configurabili, normalizzandole.
         *
         * @param string[] $defaults
         */
        protected function mediaClasses(array $defaults, mixed $extra): string
        {
            $values = array_merge($defaults, is_array($extra) ? $extra : [$extra]);
            $classes = [];

            foreach ($values as $value) {
                if (!is_scalar($value)) {
                    continue;
                }

                foreach (preg_split('/\s+/', trim((string) $value)) ?: [] as $name) {
                    if ($name !== '') {
                        $classes[] = $name;
                    }
                }
            }

            return implode(' ', array_values(array_unique($classes)));
        }

        /**
         * @param array{0:int,1:int}|null $ratio
         */
        protected function mediaRatioAttributes(?array $ratio): string
        {
            if ($ratio === null) {
                return '';
            }

            return $this->renderAttributes([
                'style' => [
                    'aspect-ratio' => (int) $ratio[0].' / '.(int) $ratio[1],
                ],
            ]);
        }

        /**
         * Renderizza gli attributi fluenti del componente tranne id e class,
         * che vengono gestiti dal markup strutturale del media renderer.
         */
        protected function mediaRootAttributes(mixed $class): string
        {
            $attributes = $class->getSchema('attributes');

            if (!is_array($attributes)) {
                return '';
            }

            unset($attributes['id'], $attributes['class']);

            return $this->renderAttributes($attributes);
        }

        /**
         * Serializza i breakpoint come oggetto JavaScript senza consentire
         * la chiusura del tag script da valori configurabili.
         *
         * @param array<int, array<string, mixed>> $breakpoints
         */
        protected function encodeSwiperBreakpoints(array $breakpoints): string
        {
            return json_encode(
                (object) $breakpoints,
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_HEX_TAG
                    | JSON_HEX_AMP
                    | JSON_HEX_APOS
                    | JSON_HEX_QUOT
            );
        }
    }
