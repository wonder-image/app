<?php

namespace Wonder\Themes\Wonder\Form;

use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Concerns\HasAttributes;
use Wonder\Themes\Wonder\Component;

abstract class Field extends Component
{
    use CanSpanColumn, HasAttributes;

    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    abstract public function renderInput(): string;

    protected function inputClass(string $base = 'wi-input'): string
    {
        $class = [$base];

        if ($this->hasValue()) {
            $class[] = 'compiled';
        }

        if ($this->hasError()) {
            $class[] = 'input-error';
        }

        return implode(' ', $class);
    }

    protected function containerClass(string $type): string
    {
        $class = ['wi-input-container', $type];

        if ($this->hasValue()) {
            $class[] = 'compiled';
        }

        if ($this->hasError()) {
            $class[] = 'input-error';
        }

        return implode(' ', $class);
    }

    protected function renderLabel(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $label = $this->escape($this->resolvedLabel());

        return '<label for="'.$id.'" class="wi-label">'.$label.'</label>';
    }

    protected function renderError(): string
    {
        $error = $this->errorMessage();

        if ($error === '') {
            return "<span class='alert-error'></span>";
        }

        return "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> ".$this->escape($error).'</span>';
    }

    protected function hasError(): bool
    {
        return $this->errorMessage() !== '';
    }

    protected function errorMessage(): string
    {
        return trim((string) ($this->schema['error'] ?? ''));
    }

    protected function hasValue(): bool
    {
        $value = $this->schema['value'] ?? null;

        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && $value !== '';
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
