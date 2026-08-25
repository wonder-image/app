<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

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
}
