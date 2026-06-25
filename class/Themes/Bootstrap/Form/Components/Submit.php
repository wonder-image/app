<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class Submit extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $label = $this->escape((string) ($this->schema['label'] ?? 'Salva'));
        $buttonClass = $this->escape(trim((string) ($this->schema['button_class'] ?? 'float-end btn btn-dark').' wi-submit'));
        $onclick = trim((string) ($this->schema['onclick'] ?? ''));

        $action = $onclick === ''
            ? 'type="submit"'
            : 'type="button" onclick="'.$this->escape($onclick).'"';

        return "<button {$action} id=\"{$id}\" name=\"{$name}\" class=\"{$buttonClass}\" disabled>{$label}</button>";
    }
}
