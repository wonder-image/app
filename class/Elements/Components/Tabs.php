<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\HasColumns;
use Wonder\Elements\Concerns\HasGap;
use Wonder\Elements\Concerns\Renderer;

/**
 * Schede con un pannello ciascuna. Con una sola scheda resta il pannello,
 * senza barra. `columns()` e `gap()` valgono per i campi di ogni pannello.
 */
class Tabs extends Component
{
    use CanSpanColumn, HasColumns, HasGap, Renderer;

    public function __construct(string $id)
    {
        $this->schema('id', self::clean($id) ?: 'tabs')->schema('tabs', [])->schema('active', null);
        $this->columnSpan(12);
    }

    public static function make(string $id): self
    {
        return new self($id);
    }

    /** @param array<int, mixed> $components */
    public function tab(string $key, string $label, array $components): self
    {
        return $this->schemaPush('tabs', [
            'key' => $key,
            'label' => $label,
            'components' => array_values($components),
        ]);
    }

    public function active(string $key): self
    {
        return $this->schema('active', $key);
    }

    /** @return array<int, array{key: string, label: string, components: array}> */
    public function tabs(): array
    {
        return (array) $this->getSchema('tabs');
    }

    /** Solo `[a-z0-9-]`: finisce negli id e negli `aria-controls`. */
    public static function clean(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9-]+/', '-', strtolower($value)), '-');
    }
}
