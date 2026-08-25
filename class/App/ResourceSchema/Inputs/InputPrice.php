<?php

namespace Wonder\App\ResourceSchema\Inputs;

/**
 * Variante prezzo di {@see InputNumber}: stessi setters di formatting, ma
 * rende l'Element `InputPrice` (simbolo di valuta e decimali già impostati
 * lato lib).
 */
class InputPrice extends InputNumber
{
    protected string $helper = 'price';
}
