<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\App\Support\OptionVisual;
use Wonder\Themes\Bootstrap\Form\Field;

/**
 * Anteprima, nome dell'icona e bottone della raccolta. La griglia la apre la
 * lib (`setIconPicker`), che aggiorna anche l'anteprima mentre si scrive.
 */
class InputIcon extends Field
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
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $preview = OptionVisual::icon((string) ($this->schema['value'] ?? ''));
        $preview = $preview !== '' ? $preview : 'bi-question-square';
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $class = $this->inputClass('form-control');
        $label = $this->escape($this->resolvedLabel());

        return <<<HTML
<label class="h6 form-label" for="{$id}">{$label}</label>
<div class="input-group mt-1 wi-icon-picker">
    <span class="input-group-text"><i class="bi {$preview} wi-show-icon" aria-hidden="true"></i></span>
    <input type="text" class="{$class}" id="{$id}" name="{$name}" value="{$value}" placeholder="bi-star" {$attributes}>
    <button type="button" class="btn btn-outline-secondary" data-wi-icon-picker-open="{$id}" title="Scegli un'icona" aria-label="Scegli un'icona"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i></button>
</div>
HTML;
    }
}
