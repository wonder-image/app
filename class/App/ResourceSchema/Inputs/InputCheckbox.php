<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\BuildsCheckGroupElement;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasOptions;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasSearchBar;
use Wonder\Elements\Form\Components\Checkbox;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Checkbox singolo oppure gruppo di checkbox.
 *
 * Senza `options()` rende un singolo checkbox booleano (spuntato quando il
 * value è `true`/`1`/`'true'`/`'on'`); con `options()` rende un gruppo, il
 * cui value può arrivare anche come JSON e viene decodificato al render.
 */
class InputCheckbox extends Input
{
    use BuildsCheckGroupElement;
    use HasOptions;
    use HasSearchBar;

    protected string $helper = 'checkbox';

    /**
     * Senza opzioni un singolo checkbox booleano; con opzioni un gruppo.
     */
    protected function element(): ElementField
    {
        if ((array) ($this->schema['options'] ?? []) === []) {
            return $this->singleCheckbox();
        }

        return $this->checkGroupElement('checkbox');
    }

    private function singleCheckbox(): Checkbox
    {
        $checkbox = new Checkbox($this->name);
        $value = $this->schema['value'] ?? null;

        if (
            $value === true
            || $value === 1
            || $value === '1'
            || $value === 'true'
            || $value === 'on'
        ) {
            $checkbox->checked();
        }

        return $checkbox;
    }
}
