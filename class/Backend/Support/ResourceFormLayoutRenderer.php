<?php

namespace Wonder\Backend\Support;

use Wonder\Elements\Components\Card;
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

        $html = '<form';
        $html .= ' id="'.$id.'"';
        $html .= ' method="'.$method.'"';
        $html .= ' enctype="'.$enctype.'"';
        $html .= ' action="'.$action.'"';
        $html .= ' onsubmit="'.$onsubmit.'"';
        $html .= ' class="'.self::columnsClass($form).' '.self::gapClass($form).'"';
        $html .= '>';
        $html .= self::renderComponents((array) ($form->components ?? []));
        $html .= '</form>';

        return $html;
    }

    private static function renderComponents(array $components): string
    {
        $html = '';

        foreach ($components as $component) {
            if ($component instanceof Card) {
                $html .= self::renderCard($component);
                continue;
            }

            if (is_object($component) && method_exists($component, 'render')) {
                $fieldHtml = $component->render();
                $html .= self::wrapField($component, $fieldHtml);
                continue;
            }

            if (is_string($component)) {
                $html .= $component;
            }
        }

        return $html;
    }

    private static function renderCard(Card $card): string
    {
        $html = '<div class="'.self::columnSpanClass($card).'">';
        $html .= '<div class="card border">';
        $html .= '<div class="card-body '.self::columnsClass($card).' '.self::gapClass($card).'">';
        $html .= self::renderComponents((array) ($card->components ?? []));
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private static function wrapField(object $component, string $html): string
    {
        $spanClass = self::columnSpanClass($component, false);

        if ($spanClass === '') {
            return $html;
        }

        return '<div class="'.$spanClass.'">'.$html.'</div>';
    }

    private static function columnSpanClass(object $component, bool $fallback = true): string
    {
        $span = property_exists($component, 'columnSpan') ? $component->columnSpan : null;

        if (!is_array($span)) {
            return $fallback ? 'col-12' : '';
        }

        $class = [];

        foreach ($span as $breakpoint => $value) {
            if ($value === null) {
                continue;
            }

            $class[] = self::breakpointPrefix((string) $breakpoint).$value;
        }

        if ($class === [] && $fallback) {
            $class[] = 'col-12';
        }

        return implode(' ', $class);
    }

    private static function columnsClass(object $component): string
    {
        $columns = property_exists($component, 'columns') ? $component->columns : null;

        if (!is_array($columns)) {
            return 'row row-cols-1';
        }

        $class = ['row'];

        foreach ($columns as $breakpoint => $value) {
            if ($value === null) {
                continue;
            }

            $class[] = 'row-cols-'.self::breakpointSuffix((string) $breakpoint).$value;
        }

        return implode(' ', $class);
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

    private static function breakpointSuffix(string $breakpoint): string
    {
        return match ($breakpoint) {
            'default' => '',
            'sm', 'md', 'lg', 'xl', '2xl' => $breakpoint.'-',
            default => '',
        };
    }

    private static function breakpointPrefix(string $breakpoint): string
    {
        return match ($breakpoint) {
            'default' => 'col-',
            'sm', 'md', 'lg', 'xl', '2xl' => 'col-'.$breakpoint.'-',
            default => 'col-',
        };
    }
}
