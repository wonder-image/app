<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Wonder\Component;

/**
 * La creazione rapida vive solo nel backend, che è Bootstrap: il modal, lo
 * script e l'endpoint (`backend.resource.quick-create`) sono suoi.
 *
 * Sul tema Wonder il bottone non si disegna, invece di far cadere la pagina
 * con «nessun renderer»: una scheda condivisa fra i due temi resta in piedi.
 */
class QuickCreateButton extends Component
{
    public function render($class): string
    {
        return '';
    }
}
