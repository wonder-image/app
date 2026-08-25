<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Tipo di controllo usato dalle liste di scelta rese come gruppo
 * (`checkTree`, `dynamicCheck`): `checkbox` (default) o `radio`.
 * Qualsiasi altro valore degrada a `checkbox`.
 */
trait HasInputType
{
    public function inputType(string $inputType): static
    {
        return $this->context('input_type', $inputType === 'radio' ? 'radio' : 'checkbox');
    }
}
