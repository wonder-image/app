<?php

namespace Wonder\Elements\Media;

use InvalidArgumentException;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Concerns\HasRatio;

/** Defers a renderable element or trusted server-generated HTML. */
class Deferred extends Media
{
    use HasRatio;

    public function __construct(string|object $content)
    {
        if (is_object($content) && !method_exists($content, 'render')) {
            throw new InvalidArgumentException('Deferred content must be renderable.');
        }
        $this->schema('content', $content)->mode('interaction')->ratio('16:9');
    }

    public static function make(string|object $content): self
    {
        return new self($content);
    }

    public function mode(string $mode): self
    {
        if (!in_array($mode, ['interaction', 'visible'], true)) {
            throw new InvalidArgumentException('Deferred mode must be interaction or visible.');
        }
        return $this->schema('deferred-mode', $mode);
    }

    public function button(Button $button): self
    {
        return $this->schema('deferred-button', $button);
    }

    public function fallbackUrl(string $url): self
    {
        return $this->schema('fallback-url', Iframe::url($url)->srcUrl());
    }

    /** Fill an already sized parent without duplicating its dimensions. */
    public function fill(): self
    {
        return $this->removeStyle('aspect-ratio')->style('position', 'absolute')
            ->style('inset', '0')->style('height', '100%');
    }
}
