<?php

namespace Wonder\Backend\Support;

use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Form\Form;

final class ResourceFormLayoutRenderer
{
    public static function render(Form $form, array $options = []): string
    {
        $id = htmlspecialchars((string) ($options['id'] ?? 'resource-layout-form'), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars((string) ($options['action'] ?? ''), ENT_QUOTES, 'UTF-8');
        $method = htmlspecialchars((string) ($options['method'] ?? 'POST'), ENT_QUOTES, 'UTF-8');
        $enctype = htmlspecialchars((string) ($options['enctype'] ?? 'multipart/form-data'), ENT_QUOTES, 'UTF-8');
        $onsubmit = htmlspecialchars((string) ($options['onsubmit'] ?? 'loadingSpinner()'), ENT_QUOTES, 'UTF-8');
        $footer = (string) ($options['footer'] ?? '');

        $html = '<form';
        $html .= ' id="'.$id.'"';
        $html .= ' method="'.$method.'"';
        $html .= ' enctype="'.$enctype.'"';
        $html .= ' action="'.$action.'"';
        $html .= ' onsubmit="'.$onsubmit.'"';
        $html .= ' class="'.self::rowClass($form).'"';
        $html .= '>';
        $html .= self::renderComponents(
            (array) ($form->components ?? []),
            self::columnsMap($form)
        );
        if ($footer !== '') {
            $html .= $footer;
        }
        $html .= '</form>';

        return $html;
    }

    private static function renderComponents(array $components, array $parentColumns): string
    {
        $html = '';

        foreach ($components as $component) {
            if ($component instanceof Card) {
                $html .= self::renderCard($component, $parentColumns);
                continue;
            }

            if ($component instanceof Container) {
                $html .= self::renderContainer($component, $parentColumns);
                continue;
            }

            if (is_object($component) && method_exists($component, 'render')) {
                $fieldHtml = $component->render();
                $html .= self::wrapField($component, $fieldHtml, $parentColumns);
                continue;
            }

            if (is_string($component)) {
                $html .= $component;
            }
        }

        return $html;
    }

    private static function renderCard(Card $card, array $parentColumns): string
    {
        $cardColumns = self::columnsMap($card);

        $html = '<div class="'.self::columnSpanClass($card, $parentColumns).'">';
        $html .= '<div class="card border">';
        $html .= '<div class="card-body '.self::rowClass($card).'">';
        $html .= self::renderComponents((array) ($card->components ?? []), $cardColumns);
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private static function renderContainer(Container $container, array $parentColumns): string
    {
        $containerColumns = self::columnsMap($container);

        $html = '<div class="'.self::columnSpanClass($container, $parentColumns).'">';
        $html .= '<div class="'.self::rowClass($container).'">';
        $html .= self::renderComponents((array) ($container->components ?? []), $containerColumns);
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private static function wrapField(object $component, string $html, array $parentColumns): string
    {
        $spanClass = self::columnSpanClass($component, $parentColumns, false);

        if ($spanClass === '') {
            return $html;
        }

        return '<div class="'.$spanClass.'">'.$html.'</div>';
    }

    private static function columnSpanClass(object $component, array $parentColumns = [], bool $fallback = true): string
    {
        $span = property_exists($component, 'columnSpan') ? $component->columnSpan : null;

        if (!is_array($span) || $span === []) {
            return $fallback ? 'col-12' : '';
        }

        $class = [];

        foreach (self::breakpoints() as $breakpoint) {
            $spanValue = $span[$breakpoint] ?? ($breakpoint === 'default' ? 1 : null);
            $columnsValue = $parentColumns[$breakpoint] ?? $parentColumns['default'] ?? 1;

            if ($spanValue === null || $columnsValue === null) {
                continue;
            }

            $width = self::columnWidth((int) $spanValue, (int) $columnsValue);
            $class[] = self::breakpointPrefix((string) $breakpoint).$width;
        }

        if ($class === [] && $fallback) {
            $class[] = 'col-12';
        }

        return implode(' ', $class);
    }

    private static function rowClass(object $component): string
    {
        return 'row '.self::gapClass($component);
    }

    private static function gapClass(object $component): string
    {
        $gap = property_exists($component, 'gap') ? $component->gap : null;

        if (!is_array($gap)) {
            return 'g-3';
        }

        $class = [];

        foreach ($gap as $breakpoint => $value) {
            if ($value === null) {
                continue;
            }

            $class[] = 'g-'.self::breakpointSuffix((string) $breakpoint).$value;
        }

        return implode(' ', $class);
    }

    private static function columnsMap(object $component): array
    {
        $columns = property_exists($component, 'columns') ? $component->columns : null;

        if (!is_array($columns) || $columns === []) {
            return ['default' => 1];
        }

        $map = [];

        foreach (self::breakpoints() as $breakpoint) {
            $value = $columns[$breakpoint] ?? null;

            if ($breakpoint === 'default') {
                $map[$breakpoint] = is_numeric($value) && (int) $value > 0 ? (int) $value : 1;
                continue;
            }

            $map[$breakpoint] = is_numeric($value) && (int) $value > 0
                ? (int) $value
                : null;
        }

        return $map;
    }

    private static function columnWidth(int $span, int $columns): int
    {
        $span = max(1, $span);
        $columns = max(1, $columns);

        return max(1, min(12, (int) round((12 / $columns) * $span)));
    }

    private static function breakpoints(): array
    {
        return ['default', 'sm', 'md', 'lg', 'xl', '2xl'];
    }

    private static function breakpointSuffix(string $breakpoint): string
    {
        return match ($breakpoint) {
            'default' => '',
            'sm', 'md', 'lg', 'xl' => $breakpoint.'-',
            '2xl' => 'xxl-',
            default => '',
        };
    }

    private static function breakpointPrefix(string $breakpoint): string
    {
        return match ($breakpoint) {
            'default' => 'col-',
            'sm', 'md', 'lg', 'xl' => 'col-'.$breakpoint.'-',
            '2xl' => 'col-xxl-',
            default => 'col-',
        };
    }
}
