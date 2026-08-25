<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasPasswordPolicy;
use Wonder\Elements\Form\Components\InputPassword as PasswordElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Campo password con policy fluent.
 *
 * Le regole vivono in `prepare['password_rules']` (un singolo array assoc).
 * `Resource::prepareFormatFromInput()` le copia in `format['password_rules']`.
 * `element()` le propaga al `Wonder\Elements\Form\Components\InputPassword`,
 * che lascia al tema `Wonder` la lista hint sotto l'input; `formToArray()` le
 * valida invece server-side via `PasswordPolicyValidator`.
 *
 * Mirror simmetrico di `Wonder\Data\Fields\Password`: se preferisci dichiarare
 * la policy a livello di Model invece che di Resource, la stessa API è
 * disponibile lì.
 */
class InputPassword extends Input
{
    use HasPasswordPolicy;

    protected string $helper = 'password';

    protected function element(): ElementField
    {
        return $this->applyPasswordPolicy(new PasswordElement($this->name));
    }
}
