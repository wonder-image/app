<?php

namespace Wonder\Themes\Bootstrap\Form;

use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\HasAttributes;

abstract class Field extends Component
{
    use CanSpanColumn, HasAttributes;

    protected array $schema = [];

    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderField($this->renderInput());
    }

    abstract public function renderInput(): string;

    protected function renderField(string $input, bool $floating = true): string
    {
        if ($floating) {
            $html = '<div><div class="form-floating">';
            $html .= $input;
            $html .= $this->renderLabel();
            $html .= '</div>';
            $html .= $this->renderError();
            $html .= '</div>';

            return $html;
        }

        return '<div>'.$input.$this->renderError().'</div>';
    }

    protected function renderLabel(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $label = $this->resolvedLabel();

        if ($label === '') {
            return '';
        }

        return '<label for="'.$id.'">'.$this->escape($label).'</label>';
    }

    protected function renderError(): string
    {
        $error = $this->errorMessage();
        $class = $error !== '' ? 'invalid-feedback d-block' : 'invalid-feedback';

        return '<div class="'.$class.'">'.$this->escape($error).'</div>';
    }

    protected function inputClass(string $base): string
    {
        return trim($base.($this->hasError() ? ' is-invalid' : ''));
    }

    protected function hasError(): bool
    {
        return $this->errorMessage() !== '';
    }

    protected function errorMessage(): string
    {
        return trim((string) ($this->schema['error'] ?? ''));
    }

    protected function resolvedLabel(): string
    {
        $label = trim((string) ($this->schema['label'] ?? ''));

        if (!empty($this->schema['attributes']['required'])) {
            $label .= '*';
        }

        return $label;
    }
}
