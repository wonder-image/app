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

        $schema = $class->getSchema();
        $masonry = (int) ($schema['masonry'] ?? 0);

        $html = "<div class=\"{$classSpanColumn}\">";
        $html .= $this->renderInner(
            $class,
            $masonry > 0
                ? $this->renderMasonryComponents($class->components, (string) ($schema['masonry-gap'] ?? '1rem'))
                : $this->renderComponents($class->components)
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
        $masonry = (int) ($schema['masonry'] ?? 0);

        if ($masonry > 0) {
            // Multi-colonna: i figli si impilano dall'alto in basso e passano
            // alla colonna dopo, senza i buchi della griglia a righe.
            $layoutClasses = [];
        } elseif ($noGrid) {
            $layoutClasses = [];
        } elseif ($layoutClasses === null && $masonry === 0) {
            $layoutClasses = [
                $this->getColumns($class->columns),
                $this->getGap($class->gap),
            ];
        }

        $attributes = is_array($schema['attributes'] ?? null)
            ? $schema['attributes']
            : [];

        if ($masonry > 0) {
            $minWidth = (string) ($schema['masonry-min-width'] ?? '22rem');
            $gap = (string) ($schema['masonry-gap'] ?? '1rem');
            $attributes['style'] = trim(
                'columns: '.$this->escape($minWidth).' '.$masonry.';'
                .' column-gap: '.$this->escape($gap).';'
                .' '.(string) ($attributes['style'] ?? '')
            );
        }
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

    /**
     * Disegna i figli del multi-colonna: ognuno in un blocco che non si spezza
     * tra due colonne. Il margine sotto tiene la distanza verticale, che
     * `column-gap` non dà.
     */
    public function renderMasonryComponents(array $components, string $gap = '1rem'): string
    {
        $html = '';

        foreach ($components as $component) {
            if (!is_object($component) || !method_exists($component, 'render')) {
                continue;
            }

            $html .= '<div style="break-inside: avoid; margin-bottom: '.$this->escape($gap).';">'
                .$component->render()
                .'</div>';
        }

        return $html;
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
