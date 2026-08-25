<?php

namespace Wonder\App\ResourceSchema\Inputs;

/**
 * Ricerca remota a testo libero (`data-wi-search-text`): la selezione di una
 * voce invia il value scelto. Sul tema Bootstrap degrada a un input testuale,
 * perché la ricerca remota è una feature del frontend.
 */
class InputSearchText extends InputSearchRemote
{
    protected string $helper = 'searchText';
}
