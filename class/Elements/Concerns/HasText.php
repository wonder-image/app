<?php

namespace Wonder\Elements\Concerns;

trait HasText
{
    protected string $text = '';

    public function text(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function muted(bool $muted = true): static
    {
        return $this->schema('muted', $muted);
    }

    public function small(bool $small = true): static
    {
        return $this->schema('small', $small);
    }

    public function bold(bool $bold = true): static
    {
        return $this->schema('bold', $bold);
    }

    public function italic(bool $italic = true): static
    {
        return $this->schema('italic', $italic);
    }

    public function color(string $color): static
    {
        return $this->schema('color', trim($color));
    }

    public function align(string $align): static
    {
        return $this->schema('align', trim($align));
    }
}
