<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class CheckBoolean extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderField($this->renderInput(), false);
    }

    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $rawName = (string) ($this->schema['name'] ?? '');
        $value = $this->schema['value'] ?? null;
        $values = is_array($this->schema['boolean_values'] ?? null)
            ? $this->schema['boolean_values']
            : ['', 'true', 'false'];

        [$valueNull, $valueTrue, $valueFalse] = array_pad($values, 3, '');

        $attributes = (array) ($this->schema['attributes'] ?? []);
        $isRequired = !empty($attributes['required']);
        unset($attributes['required']);
        $attributeString = $this->renderAttributes($attributes);

        $required = $isRequired ? ' wi-checkbox-required' : '';
        $idTrue = $this->escape($rawName.'-'.$valueTrue);
        $idFalse = $this->escape($rawName.'-'.$valueFalse);
        $label = $this->escape($this->resolvedLabel());
        $trueLabel = $this->escape((string) ($this->schema['true_label'] ?? 'Si'));
        $falseLabel = $this->escape((string) ($this->schema['false_label'] ?? 'No'));

        $checkedTrue = '';
        $checkedFalse = '';
        $classTrue = '';
        $classFalse = '';

        if ($valueTrue === $value) {
            $checkedTrue = ' checked';
            $classTrue = ' btn-primary';
        } elseif ($valueFalse === $value) {
            $checkedFalse = ' checked';
            $classFalse = ' btn-primary';
        }

        $valueNullEscaped = $this->escape((string) $valueNull);
        $valueTrueEscaped = $this->escape((string) $valueTrue);
        $valueFalseEscaped = $this->escape((string) $valueFalse);

        return <<<HTML
<div id="container-{$id}" class="w-100 wi-container-checkbox{$required}" data-wi-check-boolean="true">
    <input type="hidden" class="wi-none" name="{$name}" value="{$valueNullEscaped}">
    <input type="checkbox" class="btn-check wi-true" name="{$name}" value="{$valueTrueEscaped}" id="{$idTrue}" data-wi-check="true"{$checkedTrue} {$attributeString}>
    <input type="checkbox" class="btn-check wi-false" name="{$name}" value="{$valueFalseEscaped}" id="{$idFalse}" data-wi-check="true"{$checkedFalse} {$attributeString}>
    <div class="input-group">
        <span class="form-control">{$label}</span>
        <label class="btn border{$classTrue}" for="{$idTrue}">{$trueLabel}</label>
        <label class="btn border{$classFalse}" for="{$idFalse}">{$falseLabel}</label>
    </div>
</div>
HTML;
    }
}
