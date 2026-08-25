<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Base delle ricerche remote: un input che popola la propria dropdown via
 * AJAX dall'endpoint dato.
 *
 * L'URL vive in `schema['url']` (non in `context`, dove lo tiene invece
 * {@see InputDynamicCheck}); il tipo di ricerca lo decide la sottoclasse
 * tramite l'helper — {@see InputSearchText} o {@see InputSearchRadio}.
 */
abstract class InputSearchRemote extends Input
{
    public function url(string $url): static
    {
        $this->schema['url'] = trim($url);

        return $this;
    }
}
