<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\Elements\Form\Components\InputPrice as PriceElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Variante prezzo di {@see InputNumber}: stessi setters di formatting, ma
 * rende l'Element `InputPrice` (simbolo di valuta e decimali già impostati
 * lato lib).
 */
class InputPrice extends InputNumber
{
    protected string $helper = 'price';

    protected function element(): ElementField
    {
        return $this->applyNumberConfig(new PriceElement($this->name));
    }
}
