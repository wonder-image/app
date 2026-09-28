<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;
use Wonder\Themes\Concerns\ComposesFieldButton;

/**
 * Renderer Bootstrap del bottone fra i campi: il `Components\Button` del
 * tema, in fila con la sua didascalia.
 *
 * Salta il wrap del campo come `Submit`: niente `form-floating` e niente
 * `<label>`, il testo del bottone è già l'etichetta.
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
        $button = $this->fieldButton($this->schema)->render('bootstrap');
        $caption = $this->escape($this->fieldButtonCaption($this->schema));

        // `h-100` tiene bottone e didascalia a metà altezza della riga: accanto
        // a un campo con l'etichetta flottante restano allineati al centro.
        return '<div class="d-flex align-items-center gap-2 h-100">'
            .$button
            .'<span class="small text-body-secondary" data-wi-button-caption>'.$caption.'</span>'
            .'</div>'
            .($this->hasError() ? $this->renderError() : '');
    }
}
