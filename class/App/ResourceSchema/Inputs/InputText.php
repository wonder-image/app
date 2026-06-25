<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Campo testo semplice. Mirror DSL del legacy `FormField::key('x')->text()`,
 * ma usabile direttamente come prima classe (`InputText::key('x')`) senza
 * passare dalla facade `FormField`.
 *
 * Estende `Input` per ereditare label/attribute/required/value/render/etc.
 * L'unica responsabilità qui è marcare l'helper come `'text'` così il
 * `FormFieldElementFactory` istanzi un `Wonder\Elements\Form\Components\InputText`.
 */
class InputText extends Input
{
    protected string $helper = 'text';
}
