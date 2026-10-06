<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\Renderer;

/**
 * Un radio o un checkbox dentro un riquadro cliccabile: titolo, testo
 * sotto e una colonna a destra per il prezzo.
 */
class Choice extends Component
{
    use Renderer;

    public function __construct(string $name, string|int $value)
    {
        $this->schema('name', $name)
            ->schema('value', (string) $value)
            ->schema('type', 'radio')
            ->schema('title', '')
            ->schema('text', '')
            ->schema('aside', '')
            ->schema('checked', false)
            ->schema('disabled', false);
    }

    public static function make(string $name, string|int $value): self
    {
        return new self($name, $value);
    }

    public function type(string $type): self
    {
        return $this->schema('type', $type === 'checkbox' ? 'checkbox' : 'radio');
    }

    public function title(string $title): self
    {
        return $this->schema('title', $title);
    }

    public function text(string $text): self
    {
        return $this->schema('text', $text);
    }

    public function aside(string $aside): self
    {
        return $this->schema('aside', $aside);
    }

    public function checked(bool $checked = true): self
    {
        return $this->schema('checked', $checked);
    }

    public function disabled(bool $disabled = true): self
    {
        return $this->schema('disabled', $disabled);
    }
}
