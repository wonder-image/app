<?php

    use Wonder\Elements\Form\Components\SortableInput;

    function sortableInput($TITLE, $ID, $INPUT, $INPUT_VALUES = null) {

        return (new SortableInput($ID))
            ->containerId($ID)
            ->title((string) $TITLE)
            ->columns(is_array($INPUT) ? $INPUT : [])
            ->rows(is_array($INPUT_VALUES) ? $INPUT_VALUES : null)
            ->render();

    }
