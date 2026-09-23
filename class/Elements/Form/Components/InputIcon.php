<?php

namespace Wonder\Elements\Form\Components;

class InputIcon extends InputText
{
    public string $type = 'text';

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->attr('data-wi-icon-picker', 'true');
        $this->attr('autocomplete', 'off');
    }
}
