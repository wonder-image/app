<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\TextGenerator;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Campo testo affiancato da un bottone che ne genera il contenuto
 * (`Wonder\Elements\Form\Components\TextGenerator`).
 */
class InputTextGenerator extends Input
{
    protected string $helper = 'textGenerator';

    /** Funzione JS invocata al click sul bottone (default `generateCode`). */
    public function callback(string $callback): static
    {
        $callback = trim($callback);

        return $callback !== '' ? $this->context('callback', $callback) : $this;
    }

    /** Label del bottone (default `GENERA`). */
    public function buttonLabel(string $label): static
    {
        $label = trim($label);

        return $label !== '' ? $this->context('button_label', $label) : $this;
    }

    protected function element(): ElementField
    {
        $context = (array) ($this->schema['context'] ?? []);
        $element = new TextGenerator($this->name);

        if (isset($context['button_label']) && is_string($context['button_label'])) {
            $element->buttonLabel($context['button_label']);
        }

        if (isset($context['callback']) && is_string($context['callback'])) {
            $element->callback($context['callback']);
        }

        return $element;
    }
}
