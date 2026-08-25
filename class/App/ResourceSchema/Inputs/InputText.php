<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\InputText as TextElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Campo testo semplice. Mirror DSL del legacy `FormField::key('x')->text()`,
 * ma usabile direttamente come prima classe (`InputText::key('x')`) senza
 * passare dalla facade `FormField`.
 *
 * Estende `Input` per ereditare label/attribute/required/value/render/etc. e
 * costruisce direttamente il proprio `Elements\Form\Components\InputText`.
 * L'helper `'text'` resta soltanto come identità introspezionabile.
 */
class InputText extends Input
{
    protected string $helper = 'text';

    protected function element(): ElementField
    {
        return new TextElement($this->name);
    }
}
