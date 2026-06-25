<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

/**
 * Renderer Bootstrap di `TextGenerator`: input text standard nel wrap
 * `form-floating` con un bottone "GENERA" posizionato assoluto sulla
 * destra dell'input. Click → `generateCode('#input-id')` (o callback
 * custom via `TextGenerator::callback()`).
 */
class TextGenerator extends InputText
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $type = $this->escape((string) ($this->schema['type'] ?? 'text'));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $class = $this->inputClass('form-control');
        $buttonLabel = $this->escape((string) ($this->schema['button_label'] ?? 'GENERA'));
        $callback = $this->escape((string) ($this->schema['callback'] ?? 'generateCode'));

        $input = "<input class=\"{$class}\" type=\"{$type}\" name=\"{$name}\" id=\"{$id}\" value=\"{$value}\" {$attributes} />";
        $button = "<div class=\"btn btn-sm btn-dark text-light position-absolute top-50 end-0 me-2 translate-middle-y\" onclick=\"{$callback}('#{$id}')\">{$buttonLabel}</div>";

        return $input.$button;
    }
}
