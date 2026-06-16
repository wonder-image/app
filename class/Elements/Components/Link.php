<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\HasLinkAttributes;
use Wonder\Elements\Concerns\Renderer;

/**
 * Link inline, usabile sia standalone (`echo Link::to('/x', 'Vai')`) sia
 * come frammento dentro `Text` (`Text::make('Vedi ')->link('/x', 'qui')`).
 *
 * Mette a disposizione gli helper più frequenti (`blank()`, `external()`,
 * `icon()`) per evitare la concatenazione manuale di `target="_blank"
 * rel="noopener noreferrer"` ogni volta — che era il vero punto di
 * dolore con `Text + HTML grezzo`.
 */
class Link extends Component
{
    use HasLinkAttributes, Renderer;

    private string $label = '';

    public function __construct(string $href = '', string $label = '')
    {
        $this->href($href);
        $this->label = $label;
    }

    public static function to(string $href, string $label): self
    {
        return new self($href, $label);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function icon(string $icon, string $position = 'start'): self
    {
        return $this->schema('icon', trim($icon))
            ->schema('icon_position', $position === 'end' ? 'end' : 'start');
    }

    public function muted(bool $muted = true): self
    {
        return $this->schema('muted', $muted);
    }
}
