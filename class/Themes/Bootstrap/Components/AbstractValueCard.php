<?php

namespace Wonder\Themes\Bootstrap\Components;

use InvalidArgumentException;
use Wonder\Elements\Components\AbstractValueCard as ValueCardElement;
use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Concerns\HasAttributes;

abstract class AbstractValueCard extends Component
{
    use CanSpanColumn, HasAttributes;

    final public function render($class): string
    {
        if (!$class instanceof ValueCardElement) {
            throw new InvalidArgumentException('Il renderer richiede un AbstractValueCard.');
        }

        return $this->renderCard($class, true);
    }

    final public function renderInner(ValueCardElement $class): string
    {
        return $this->renderCard($class, false);
    }

    abstract protected function renderBody(ValueCardElement $class, array $schema): string;

    protected function title(array $schema): string
    {
        return $this->escape((string) ($schema['title'] ?? ''));
    }

    protected function displayValue(array $schema): string
    {
        $value = array_key_exists('display_value', $schema)
            ? $schema['display_value']
            : ($schema['value'] ?? null);

        if ($value === null || (is_string($value) && trim($value) === '')) {
            $value = (string) ($schema['placeholder'] ?? '--');
        }

        return $this->escape($this->scalarToString($value));
    }

    protected function valueTag(array $schema): string
    {
        $level = max(1, min(6, (int) ($schema['value_level'] ?? 5)));

        return 'h'.$level;
    }

    protected function scalarToString(mixed $value): string
    {
        if ($value === true) {
            return '1';
        }

        if ($value === false) {
            return '0';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function renderCard(ValueCardElement $class, bool $includeColumnSpan): string
    {
        $schema = $class->getSchema();
        $attributes = is_array($schema['attributes'] ?? null)
            ? $schema['attributes']
            : [];
        $classes = ['card', 'border', 'h-100'];

        if ($includeColumnSpan) {
            $classes[] = $this->getColumnSpan($class->columnSpan);
        }

        $classes = array_merge($classes, $this->classTokens($attributes['class'] ?? null));
        $attributes['class'] = implode(' ', array_values(array_unique(array_filter($classes))));

        $id = $schema['id'] ?? null;
        if (is_string($id) && $id !== '') {
            $attributes['id'] = $id;
        }

        $attributeString = $this->renderAttributes($attributes);

        return '<div'.($attributeString !== '' ? ' '.$attributeString : '').'>'
            .$this->renderBody($class, $schema)
            .'</div>';
    }

    /** @return string[] */
    private function classTokens(mixed $classes): array
    {
        if (is_array($classes)) {
            $classes = implode(' ', array_map(
                static fn (mixed $class): string => is_scalar($class) ? (string) $class : '',
                $classes
            ));
        }

        if (!is_scalar($classes)) {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\s+/', trim((string) $classes)) ?: []
        ));
    }
}
