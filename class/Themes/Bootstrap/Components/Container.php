<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Elements\Components\Container as ContainerElement;
use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Bootstrap\Concerns\HasColumns;
use Wonder\Themes\Bootstrap\Concerns\HasGap;
use Wonder\Themes\Concerns\HasAttributes;

class Container extends Component
{
    use HasColumns, CanSpanColumn, HasGap, HasAttributes;

    public function render($class): string
    {
        $classSpanColumn = $this->getColumnSpan($class->columnSpan);

        $html = "<div class=\"{$classSpanColumn}\">";
        $html .= $this->renderInner(
            $class,
            $this->renderComponents($class->components)
        );
        $html .= '</div>';

        return $html;
    }

    /**
     * Renderizza il nodo interno del Container. Il layout Resource riusa
     * questo metodo passando le proprie classi Bootstrap `row`/`g-*`.
     */
    public function renderInner(ContainerElement $class, string $content, ?array $layoutClasses = null): string
    {
        $schema = $class->getSchema();
        $noGrid = ($schema['no-grid'] ?? false) === true;

        if ($noGrid) {
            $layoutClasses = [];
        } elseif ($layoutClasses === null) {
            $layoutClasses = [
                $this->getColumns($class->columns),
                $this->getGap($class->gap),
            ];
        }

        $attributes = is_array($schema['attributes'] ?? null)
            ? $schema['attributes']
            : [];
        $classes = $this->mergeClasses(
            $layoutClasses,
            $attributes['class'] ?? null
        );

        $id = $schema['id'] ?? null;
        if (is_string($id) && $id !== '') {
            $attributes['id'] = $id;
        }

        if ($classes === []) {
            unset($attributes['class']);
        } else {
            $attributes['class'] = implode(' ', $classes);
        }

        $attributeString = $this->renderAttributes($attributes);

        return '<div'.($attributeString !== '' ? ' '.$attributeString : '').'>'
            .$content
            .'</div>';
    }

    /** @return string[] */
    private function mergeClasses(array $layoutClasses, mixed $customClasses): array
    {
        $classes = $layoutClasses;

        if (is_array($customClasses)) {
            $classes = array_merge($classes, $customClasses);
        } elseif (is_scalar($customClasses)) {
            $classes[] = (string) $customClasses;
        }

        $tokens = [];

        foreach ($classes as $class) {
            if (!is_scalar($class)) {
                continue;
            }

            foreach (preg_split('/\s+/', trim((string) $class)) ?: [] as $token) {
                if ($token !== '') {
                    $tokens[] = $token;
                }
            }
        }

        return array_values(array_unique($tokens));
    }
}
