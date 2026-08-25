<?php

namespace Wonder\App\ResourceSchema\Inputs;

/**
 * Variante percentuale di {@see InputNumber}: stessi setters di formatting,
 * ma rende l'Element `InputPercentige`.
 */
class InputPercentige extends InputNumber
{
    protected string $helper = 'percentige';
}
