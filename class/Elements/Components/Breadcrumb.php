<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\Renderer;

class Breadcrumb extends Component
{
    use Renderer;

    private array $items;
    private string $label = 'breadcrumb';

    /** Accepts URL => name or a list of ['url' => ..., 'name' => ...]. */
    public function __construct(array $list)
    {
        if (!array_is_list($list)) {
            $list = array_map(static fn ($url, $name): array => ['url' => $url, 'name' => $name], array_keys($list), array_values($list));
        }
        $this->items = array_values(array_filter($list, 'is_array'));
    }

    public static function make(array $list): self
    {
        return new self($list);
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

    public function getItems(): array
    {
        return $this->items;
    }
}
