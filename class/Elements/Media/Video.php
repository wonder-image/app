<?php

namespace Wonder\Elements\Media;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\HasMediaFit;
use Wonder\Elements\Concerns\Renderer;

class Video extends Component
{
    use HasMediaFit;
    use Renderer;

    public function __construct(string $src)
    {
        $src = trim($src);

        if ($src === '') {
            throw new InvalidArgumentException('La source video non puo essere vuota.');
        }

        $this->schema('src', $src)
            ->schema('poster', $this->replaceExtension($src, 'jpg'))
            ->playsInline()
            ->loop()
            ->muted();
    }

    public static function src(string $src): self
    {
        return new self($src);
    }

    public function poster(string $poster): self
    {
        $poster = trim($poster);

        if ($poster === '') {
            throw new InvalidArgumentException('Il poster video non puo essere vuoto.');
        }

        return $this->schema('poster', $poster);
    }

    /**
     * Abilita una source WebM derivata oppure usa l'URL esplicito ricevuto.
     */
    public function webm(bool|string $webm = true): self
    {
        if (is_string($webm)) {
            $url = trim($webm);

            return $this->schema('webm', $url !== '' ? $url : null);
        }

        return $this->schema(
            'webm',
            $webm ? $this->replaceExtension($this->url(), 'webm') : null
        );
    }

    /**
     * Usa la variante WebP del poster; non abilita la source video WebM.
     */
    public function webp(bool $webp = true): self
    {
        return $this->schema('poster-webp', $webp);
    }

    public function hasWebP(bool $webp = true): self
    {
        return $this->webp($webp);
    }

    public function autoplay(bool $autoplay = true): self
    {
        if ($autoplay) {
            $this->hover(false);
        }

        return $this->attr('autoplay', $autoplay);
    }

    public function hover(bool $hover = true): self
    {
        if (!$hover) {
            return $this
                ->removeAttr('data-start-hover')
                ->removeAttr('onmouseenter')
                ->removeAttr('onmouseleave');
        }

        $this->autoplay(false);

        return $this
            ->attr('data-start-hover', 'true')
            ->attr('onmouseenter', 'this.play()')
            ->attr('onmouseleave', 'this.pause()');
    }

    public function start(string $mode): self
    {
        return match (strtolower(trim($mode))) {
            'autoplay' => $this->autoplay(),
            'hover' => $this->hover(),
            'manual', 'none' => $this->autoplay(false)->hover(false),
            default => throw new InvalidArgumentException(
                "Avvio video {$mode} non valido. Valori ammessi: autoplay, hover, manual."
            ),
        };
    }

    public function controls(bool $controls = true): self
    {
        return $this->attr('controls', $controls);
    }

    public function loop(bool $loop = true): self
    {
        return $this->attr('loop', $loop);
    }

    public function muted(bool $muted = true): self
    {
        return $this->attr('muted', $muted);
    }

    public function playsInline(bool $playsInline = true): self
    {
        return $this->attr('playsinline', $playsInline);
    }

    public function fixed(bool $fixed = true): self
    {
        $this->schema('fixed', $fixed);

        if ($fixed && $this->getStyle('z-index') === null) {
            $this->schema('fixed-default-z-index', true)
                ->style('z-index', '-1');
        } elseif (!$fixed && $this->getSchema('fixed-default-z-index') === true && $this->getStyle('z-index') === '-1') {
            $this->removeStyle('z-index');
        }

        if (!$fixed) {
            $this->schema('fixed-default-z-index', false);
        }

        return $this;
    }

    public function filter(bool $filter = true): self
    {
        return $this->schema('filter', $filter);
    }

    public function url(): string
    {
        return (string) $this->getSchema('src');
    }

    public function posterUrl(): string
    {
        $poster = (string) $this->getSchema('poster');

        if ($this->getSchema('poster-webp') === true) {
            return $this->replaceExtension($poster, 'webp');
        }

        return $poster;
    }

    public function webmUrl(): ?string
    {
        $webm = $this->getSchema('webm');

        return is_string($webm) && $webm !== '' ? $webm : null;
    }

    private function replaceExtension(string $url, string $extension): string
    {
        $suffixPosition = strcspn($url, '?#');
        $base = substr($url, 0, $suffixPosition);
        $suffix = substr($url, $suffixPosition);
        $path = parse_url($base, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return $base . '.' . ltrim($extension, '.') . $suffix;
        }

        $prefix = substr($base, 0, strlen($base) - strlen($path));
        $dotPosition = strrpos($path, '.');
        $slashPosition = strrpos($path, '/');

        if ($dotPosition !== false && ($slashPosition === false || $dotPosition > $slashPosition)) {
            $path = substr($path, 0, $dotPosition) . '.' . ltrim($extension, '.');
        } else {
            $path .= '.' . ltrim($extension, '.');
        }

        return $prefix . $path . $suffix;
    }
}
