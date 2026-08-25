<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\Elements\Form\Components\InputPercentige as PercentigeElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Variante percentuale di {@see InputNumber}: stessi setters di formatting,
 * ma rende l'Element `InputPercentige`.
 */
class InputPercentige extends InputNumber
{
    protected string $helper = 'percentige';

    protected function element(): ElementField
    {
        return $this->applyNumberConfig(new PercentigeElement($this->name));
    }
}
