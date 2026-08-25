<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\BuildsCheckGroupElement;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasOptions;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasSearchBar;
use Wonder\Elements\Form\Field as ElementField;

/** Gruppo di radio button, con barra di ricerca opzionale sulle opzioni. */
class InputRadio extends Input
{
    use BuildsCheckGroupElement;
    use HasOptions;
    use HasSearchBar;

    protected string $helper = 'radio';

    protected function element(): ElementField
    {
        return $this->checkGroupElement('radio');
    }
}
