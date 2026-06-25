<?php

    /**
     * Helper procedurale `sortableInput()` per pagine admin (Bootstrap).
     * Compatibility shim sopra `Wonder\Elements\Form\Components\SortableInput`.
     * Nuovo codice: usa direttamente la classe Element o, se serve
     * integrazione schema-driven, definisci un helper in
     * `FormField`/`FormFieldElementFactory`.
     */

    use Wonder\Elements\Form\Components\SortableInput;

    function sortableInput($TITLE, $ID, $INPUT, $INPUT_VALUES = null) {

        return (new SortableInput($ID))
            ->containerId($ID)
            ->title((string) $TITLE)
            ->columns(is_array($INPUT) ? $INPUT : [])
            ->rows(is_array($INPUT_VALUES) ? $INPUT_VALUES : null)
            ->render();

    }
