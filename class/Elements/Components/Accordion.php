<?php

namespace Wonder\Elements\Components;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\HasText;
use Wonder\Elements\Concerns\Renderer;

class Accordion extends Component
{
    use CanSpanColumn, HasText, Renderer;

    private const ALLOWED_TEXT_SIZES = [
        '',
        'title-big',
        'title',
        'subtitle',
        'text',
        'text-small',
    ];

    private const ICON_ALIASES = [
        'plus' => 'plus',
        'bi-plus' => 'plus',
        'bi bi-plus' => 'plus',
        'dash' => 'plus',
        'bi-dash' => 'plus',
        'bi bi-dash' => 'plus',
        'chevron' => 'chevron',
        'chevron-down' => 'chevron',
        'bi-chevron-down' => 'chevron',
        'bi bi-chevron-down' => 'chevron',
        'chevron-up' => 'chevron',
        'bi-chevron-up' => 'chevron',
        'bi bi-chevron-up' => 'chevron',
        'plus-lg' => 'plus-lg',
        'bi-plus-lg' => 'plus-lg',
        'bi bi-plus-lg' => 'plus-lg',
        'dash-lg' => 'plus-lg',
        'bi-dash-lg' => 'plus-lg',
        'bi bi-dash-lg' => 'plus-lg',
    ];

    public array $components = [];

    public function __construct(string $text = '')
    {
        $this->text = $text;
        $this->columnSpan(12);
    }

    public static function make(string $text): self
    {
        return new self($text);
    }

    public function description(string $description): self
    {
        return $this->schema('description', $description);
    }

    public function getDescription(): string
    {
        return (string) ($this->getSchema('description') ?? '');
    }

    public function components(array $components): self
    {
        $this->components = $components;

        return $this;
    }

    public function expanded(bool $expanded = true): self
    {
        return $this->schema('expanded', $expanded);
    }

    public function icon(string $icon = 'plus'): self
    {
        $normalized = strtolower(trim($icon));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        if (!isset(self::ICON_ALIASES[$normalized])) {
            throw new InvalidArgumentException(
                'Icona accordion non valida. Valori ammessi: plus, chevron, plus-lg'
            );
        }

        return $this->schema('icon', self::ICON_ALIASES[$normalized]);
    }

    public function titleSize(string $size): self
    {
        return $this->schema('title_size', $this->normalizeTextSize($size));
    }

    public function descriptionSize(string $size): self
    {
        return $this->schema('description_size', $this->normalizeTextSize($size));
    }

    public function flush(bool $flush = true): self
    {
        return $this->schema('flush', $flush);
    }

    private function normalizeTextSize(string $size): string
    {
        $normalized = strtolower(trim($size));

        if (!in_array($normalized, self::ALLOWED_TEXT_SIZES, true)) {
            throw new InvalidArgumentException(
                'Dimensione testo accordion non valida. Valori ammessi: '
                .implode(', ', array_filter(self::ALLOWED_TEXT_SIZES))
            );
        }

        return $normalized;
    }
}
