<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

/**
 * Input "text + list": un campo di testo combobox-like che mostra una
 * dropdown filtrabile di opzioni. La selezione popola il campo con
 * la label dell'opzione e il radio nascosto trasporta il `value`
 * effettivo nel form. Usato in frontend per country/state/phone-prefix.
 */
class TextList extends Field
{
    public string $type = 'text';

    public function options(array $options): self
    {
        return $this->schema('options', $options);
    }

    protected function renderInput(): string
    {
        return '';
    }
}
