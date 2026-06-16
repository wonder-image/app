<?php

namespace Wonder\Elements\Components;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

class Dropdown extends Component
{
    use CanSpanColumn, Renderer;

    private const ALLOWED_SIZES = ['', 'sm', 'lg'];
    private const ALLOWED_DIRECTIONS = ['down', 'up', 'start', 'end'];
    private const ALLOWED_ALIGNMENTS = ['start', 'end'];

    private string $label = '';

    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    public function __construct(string $label = '')
    {
        $this->label = $label;

        $this->variant('secondary');
        $this->align('start');
        $this->direction('down');
    }

    public static function make(string $label = ''): self
    {
        return new self($label);
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

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function items(array $items): self
    {
        $this->items = $items;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function item(string $label, ?string $href = '#', array $options = []): self
    {
        $item = array_merge($options, [
            'kind' => ($options['kind'] ?? (($href === null || $href === '') ? 'button' : 'link')),
            'label' => $label,
            'href' => $href,
        ]);

        $this->items[] = $item;

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function button(string $label, array $options = []): self
    {
        $options['kind'] = 'button';

        return $this->item($label, null, $options);
    }

    public function divider(): self
    {
        $this->items[] = ['kind' => 'divider'];

        return $this;
    }

    public function header(string $label): self
    {
        $this->items[] = [
            'kind' => 'header',
            'label' => $label,
        ];

        return $this;
    }

    public function text(string $text): self
    {
        $this->items[] = [
            'kind' => 'text',
            'label' => $text,
        ];

        return $this;
    }

    public function variant(string $variant): self
    {
        $variant = strtolower(trim($variant));

        return $this->schema('variant', $variant !== '' ? $variant : 'secondary');
    }

    public function outline(bool $outline = true): self
    {
        return $this->schema('outline', $outline);
    }

    public function size(string $size): self
    {
        $normalized = strtolower(trim($size));
        if (!in_array($normalized, self::ALLOWED_SIZES, true)) {
            throw new InvalidArgumentException(
                'Dimensione dropdown non valida. Valori ammessi: '.implode(', ', array_filter(self::ALLOWED_SIZES))
            );
        }

        return $this->schema('size', $normalized);
    }

    public function direction(string $direction): self
    {
        $normalized = strtolower(trim($direction));
        if (!in_array($normalized, self::ALLOWED_DIRECTIONS, true)) {
            throw new InvalidArgumentException(
                'Direzione dropdown non valida. Valori ammessi: '.implode(', ', self::ALLOWED_DIRECTIONS)
            );
        }

        return $this->schema('direction', $normalized);
    }

    public function align(string $align): self
    {
        $normalized = strtolower(trim($align));
        if (!in_array($normalized, self::ALLOWED_ALIGNMENTS, true)) {
            throw new InvalidArgumentException(
                'Allineamento dropdown non valido. Valori ammessi: '.implode(', ', self::ALLOWED_ALIGNMENTS)
            );
        }

        return $this->schema('align', $normalized);
    }

    public function dark(bool $dark = true): self
    {
        return $this->schema('dark', $dark);
    }

    public function disabled(bool $disabled = true): self
    {
        return $this->schema('disabled', $disabled);
    }

    public function grouped(bool $grouped = true): self
    {
        return $this->schema('grouped', $grouped);
    }
}
