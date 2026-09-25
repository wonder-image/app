<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Concerns\ComposesFieldButton;
use Wonder\Themes\Wonder\Form\Field;

/**
 * Renderer Wonder del bottone fra i campi: il `Components\Button` del tema
 * Wonder con accanto la didascalia. Niente label e niente
 * `wi-input-container`: il testo del bottone è già l'etichetta.
 */
class Button extends Field
{
    use ComposesFieldButton;

    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    public function renderInput(): string
    {
        $button = $this->fieldButton($this->schema)->render('wonder');
        $caption = $this->escape($this->fieldButtonCaption($this->schema));

        return '<div class="d-flex gap-2">'
            .$button
            .'<span class="text-small" data-wi-button-caption>'.$caption.'</span>'
            .'</div>'
            .($this->hasError() ? $this->renderError() : '');
    }
}
