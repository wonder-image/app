<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasOptions;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasVersion;

/**
 * Combobox "text + list": campo di testo con dropdown filtrabile su una lista
 * *statica* di opzioni.
 *
 * Sul tema Wonder la selezione popola l'input con la label e un radio nascosto
 * trasporta il value effettivo; sul tema Bootstrap degrada a un select
 * ricercabile. Da preferire a {@see InputSelect} quando le opzioni sono molte
 * e serve la ricerca lato client senza chiamate remote — per la ricerca via
 * AJAX vedi {@see InputSearchText}.
 */
class InputTextList extends Input
{
    use HasOptions;
    use HasVersion;

    protected string $helper = 'textList';
}
