<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Elements\Components\AbstractValueCard as ValueCardElement;

class MetricCard extends AbstractValueCard
{
    protected function renderBody(ValueCardElement $class, array $schema): string
    {
        $comparison = $this->comparison($schema);
        $tag = $this->valueTag($schema);
        $valueColumn = $comparison === null ? 'col-12' : 'col-8';
        $unit = $this->escape((string) ($schema['unit'] ?? ''));

        $html = '<div class="card-body row g-3 align-items-end">';
        $html .= '<div class="col-12"><h6 class="text-body-secondary mb-0">'
            .$this->title($schema)
            .'</h6></div>';
        $html .= "<div class=\"{$valueColumn}\"><{$tag} class=\"w-auto mb-0\">"
            .$this->displayValue($schema)
            .$unit
            ."</{$tag}></div>";

        if ($comparison !== null) {
            $attributes = $this->renderAttributes([
                'class' => 'mb-0 text-end text-'.$comparison['color'],
                'data-bs-toggle' => 'tooltip',
                'data-bs-title' => $comparison['previous'].$this->scalarToString($schema['unit'] ?? ''),
                'tabindex' => '0',
            ]);
            $html .= '<div class="col-4">';
            $html .= '<h6 '.$attributes.'>';
            $html .= '<i class="bi '.$comparison['icon'].'" aria-hidden="true"></i> ';
            $html .= $this->escape($comparison['label']);
            $html .= '</h6>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @return array{color: string, icon: string, label: string, previous: string}|null
     */
    private function comparison(array $schema): ?array
    {
        if (!array_key_exists('previous_value', $schema)) {
            return null;
        }

        $current = $this->numericValue($schema['value'] ?? null);
        $previous = $this->numericValue($schema['previous_value']);

        if ($current === null || $previous === null) {
            return null;
        }

        $previousLabel = $this->scalarToString($schema['previous_value']);

        if ($previous == 0.0 && $current != 0.0) {
            return [
                'color' => 'body-secondary',
                'icon' => 'bi-dash',
                'label' => '--',
                'previous' => $previousLabel,
            ];
        }

        $percentage = $previous == 0.0
            ? 0.0
            : (($current - $previous) / abs($previous)) * 100;
        $precision = array_key_exists('delta_precision', $schema)
            && $schema['delta_precision'] !== null
                ? (int) $schema['delta_precision']
                : (($percentage != 0.0 && abs($percentage) < 10) ? 2 : 0);
        $rounded = round($percentage, $precision);

        if ($rounded == 0.0) {
            $rounded = 0.0;
        }

        $direction = $rounded <=> 0.0;
        $higherIsBetter = ($schema['higher_is_better'] ?? true) === true;
        $improved = $higherIsBetter ? $direction > 0 : $direction < 0;

        $color = match ($direction) {
            1, -1 => $improved ? 'success' : 'danger',
            default => 'body-secondary',
        };
        $icon = match ($direction) {
            1 => 'bi-arrow-up',
            -1 => 'bi-arrow-down',
            default => 'bi-dash',
        };
        $sign = match ($direction) {
            1 => '+',
            -1 => '-',
            default => '',
        };
        $label = $sign.number_format(abs($rounded), $precision, '.', '').'%';

        return [
            'color' => $color,
            'icon' => $icon,
            'label' => $label,
            'previous' => $previousLabel,
        ];
    }

    private function numericValue(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }
}
