<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\Renderer;

class ChoiceGroup extends Component
{
    use Renderer;

    /** `segmented`: scelte in riga, unite; `list`: scelte impilate, unite. */
    public const VARIANTS = ['segmented', 'list'];

    public function __construct(string $legend = '')
    {
        $this->schema('legend', $legend)->schema('choices', [])->schema('variant', '');
    }

    public static function make(string $legend = ''): self
    {
        return new self($legend);
    }

    public function choices(Choice ...$choices): self
    {
        return $this->schema('choices', $choices);
    }

    public function variant(string $variant): self
    {
        $variant = strtolower(trim($variant));

        return $this->schema('variant', in_array($variant, self::VARIANTS, true) ? $variant : '');
    }
}
