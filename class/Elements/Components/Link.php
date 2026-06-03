<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
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
    use CanSpanColumn, Renderer;

    private string $href = '';
    private string $label = '';

    public function __construct(string $href = '', string $label = '')
    {
        $this->href = trim($href);
        $this->label = $label;
    }

    public static function to(string $href, string $label): self
    {
        return new self($href, $label);
    }

    public function href(string $href): self
    {
        $this->href = trim($href);

        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function target(string $target): self
    {
        return $this->schema('target', trim($target));
    }

    /**
     * Shortcut per link che aprono in una nuova tab in modo sicuro:
     * `target="_blank"` + `rel="noopener noreferrer"`. Usalo invece di
     * scrivere a mano `<a ... target="_blank" rel="noopener noreferrer">`.
     */
    public function blank(bool $blank = true): self
    {
        if (!$blank) {
            return $this->schema('target', '')->schema('rel', '');
        }

        $rel = trim((string) ($this->schema['rel'] ?? ''));
        $rel = trim($rel.' noopener noreferrer');

        return $this->schema('target', '_blank')->schema('rel', $rel);
    }

    public function external(bool $external = true): self
    {
        return $this->blank($external);
    }

    public function rel(string $rel): self
    {
        return $this->schema('rel', trim($rel));
    }

    public function title(string $title): self
    {
        return $this->schema('title', $title);
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
