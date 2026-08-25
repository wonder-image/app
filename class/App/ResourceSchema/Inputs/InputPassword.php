<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasPasswordPolicy;

/**
 * Campo password con policy fluent.
 *
 * Le regole vivono in `prepare['password_rules']` (un singolo array assoc).
 * `Resource::prepareFormatFromInput()` le copia in `format['password_rules']`,
 * da dove le legge sia il render — `FormFieldElementFactory::passwordElement()`
 * le propaga al `Wonder\Elements\Form\Components\InputPassword`, che a sua
 * volta lascia che il theme `Wonder` emetta la lista hint sotto l'input — sia
 * `formToArray()`, che le valida server-side via `PasswordPolicyValidator`.
 *
 * Mirror simmetrico di `Wonder\Data\Fields\Password`: se preferisci dichiarare
 * la policy a livello di Model invece che di Resource, la stessa API è
 * disponibile lì.
 */
class InputPassword extends Input
{
    use HasPasswordPolicy;

    protected string $helper = 'password';
}
