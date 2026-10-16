<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

/** Riquadro-collegamento: icona, titolo e descrizione facoltativa; tutta la card è cliccabile. */
class LinkCard extends Component
{
    use CanSpanColumn, Renderer;

    public function __construct(string $title = '', string $href = '')
    {
        $this->schema('title', $title);
        $this->schema('href', $href);
        $this->variant('primary');
    }

    public static function make(string $title, string $href): static
    {
        return new static($title, $href);
    }

    /** Classe Bootstrap Icons (`bi-envelope`). */
    public function icon(string $icon): static
    {
        return $this->schema('icon', $icon);
    }

    public function variant(string $variant): static
    {
        return $this->schema('variant', $variant);
    }

    public function description(string $description): static
    {
        return $this->schema('description', $description);
    }
}
