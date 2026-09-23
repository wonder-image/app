<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\InputIcon as IconElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Il nome di un'icona Bootstrap (`bi-star`), scelto da una griglia.
 *
 * Nel backend il campo mostra l'anteprima e un bottone che apre la raccolta
 * con la ricerca (anche in italiano: «stella», «goccia»); il valore resta un
 * testo, e chi lo salva lo controlla con `OptionVisual::icon()`.
 */
class InputIcon extends Input
{
    protected string $helper = 'icon';

    protected function element(): ElementField
    {
        return new IconElement($this->name);
    }
}
