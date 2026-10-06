<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\Renderer;

class ChoiceGroup extends Component
{
    use Renderer;

    public function __construct(string $legend = '')
    {
        $this->schema('legend', $legend)->schema('choices', []);
    }

    public static function make(string $legend = ''): self
    {
        return new self($legend);
    }

    public function choices(Choice ...$choices): self
    {
        return $this->schema('choices', $choices);
    }
}
