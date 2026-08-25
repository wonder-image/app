<?php

namespace Wonder\App\Support;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Field as ElementField;

/**
 * @deprecated Il mapping helper -> Element non esiste più: ogni tipo sotto
 *             `Wonder\App\ResourceSchema\Inputs\` costruisce da sé il proprio
 *             `Wonder\Elements\Form\Components\*` in `element()`. Usa
 *             `$field->render($theme)` o `$field->compile()`.
 *
 * Resta come sottile passacarte per i call site esterni (moduli
 * `wonder-image/<slug>`, siti) che chiamavano la factory direttamente.
 */
final class FormFieldElementFactory
{
    /** @deprecated Usa `$field->render($theme)`. */
    public static function render(Input $field, ?string $theme = null): ?string
    {
        return $field->render($theme);
    }

    /** @deprecated Usa `$field->compile()`. */
    public static function make(Input $field): ?ElementField
    {
        return $field->compile();
    }
}
