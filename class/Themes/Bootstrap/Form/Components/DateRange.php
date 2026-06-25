<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class DateRange extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    public function renderInput(): string
    {
        $name = (string) ($this->schema['name'] ?? '');
        $label = trim($this->resolvedLabel());
        $value = is_array($this->schema['value'] ?? null) ? $this->schema['value'] : ['', ''];
        $attributes = (array) ($this->schema['attributes'] ?? []);
        $fromId = $this->escape((string) ($this->schema['id'] ?? 'date-range').'-from');
        $toId = $this->escape((string) ($this->schema['id'] ?? 'date-range').'-to');
        $fromName = $this->escape($name.'_from');
        $toName = $this->escape($name.'_to');
        $fromValue = $this->escape((string) ($value[0] ?? ''));
        $toValue = $this->escape((string) ($value[1] ?? ''));
        $class = $this->inputClass('form-control');
        $min = $this->renderSingleAttribute('data-wi-min-date', $attributes['data-wi-min-date'] ?? null);
        $max = $this->renderSingleAttribute('data-wi-max-date', $attributes['data-wi-max-date'] ?? null);
        unset($attributes['data-wi-min-date'], $attributes['data-wi-max-date']);
        $fieldAttributes = $this->renderAttributes($attributes);
        $labelHtml = $label !== '' ? '<label class="h6 form-label">'.$this->escape($label).'</label>' : '';

        return <<<HTML
<div>
    {$labelHtml}
    <div class="input-group input-daterange mt-1" data-wi-date-range="true" {$min} {$max}>
        <span class="input-group-text">Dal</span>
        <input id="{$fromId}" type="text" class="{$class}" name="{$fromName}" value="{$fromValue}" data-wi-check="true" readonly {$fieldAttributes}>
        <span class="input-group-text">Al</span>
        <input id="{$toId}" type="text" class="{$class}" name="{$toName}" value="{$toValue}" data-wi-check="true" readonly {$fieldAttributes}>
    </div>
    {$this->renderError()}
</div>
HTML;
    }

    private function renderSingleAttribute(string $key, mixed $value): string
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return '';
        }

        return $key.'="'.$this->escape((string) $value).'"';
    }
}
