<?php

namespace Wonder\Localization\Contracts;

/**
 * Un Model con campi traducibili: le traduzioni stanno nella tabella
 * `translations`, la colonna resta il testo nella lingua base.
 */
interface Translatable
{
    /** @return array<string, 'text'|'html'> campo => formato */
    public static function translatableFields(): array;
}
