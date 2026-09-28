<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Wonder\Component;

/**
 * La finestra vive solo nel backend, che è Bootstrap: il markup `.modal`,
 * lo script che la stacca dal form e l'apertura con `data-bs-toggle` sono
 * suoi.
 *
 * Sul tema Wonder non si disegna, invece di far cadere la pagina con
 * «nessun renderer»: una scheda condivisa fra i due temi resta in piedi.
 */
class Modal extends Component
{
    public function render($class): string
    {
        return '';
    }
}
