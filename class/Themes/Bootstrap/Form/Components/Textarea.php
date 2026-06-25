<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class Textarea extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $rawValue = (string) ($this->schema['value'] ?? '');
        $value = $this->escape($rawValue);
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $class = $this->inputClass('form-control');
        $placeholder = $this->escape($this->resolvedLabel());
        $maxLength = (int) ($this->schema['max_length'] ?? 0);
        $counter = '';

        if ($maxLength > 0) {
            $current = strlen($rawValue);
            $counter = '<div class="position-absolute bottom-0 end-0 m-2 me-3">'
                .'<span class="wi-counter">'.$current.'</span> / '
                .'<span class="wi-max-lenght">'.$maxLength.'</span>'
                .'</div>';
        }

        return $counter
            ."<textarea class=\"{$class}\" placeholder=\"{$placeholder}\" id=\"{$id}\" style=\"height: 100px\" name=\"{$name}\" {$attributes}>{$value}</textarea>";
    }
}
