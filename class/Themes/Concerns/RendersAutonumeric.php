<?php

namespace Wonder\Themes\Concerns;

/**
 * I campi numerici (`data-wi-number`, `data-wi-price`, `data-wi-percentige`)
 * sono formattati da AutoNumeric. Il backend lo carica sempre; il frontend no,
 * quindi il renderer che stampa il campo si ricorda da solo della libreria,
 * come fanno Swiper e i grafici. Senza APP_URL (CLI, test) non c'è una pagina
 * da servire e il caricamento si salta.
 *
 * Va usata in una classe che estende un renderer con `renderInput()`.
 */
trait RendersAutonumeric
{
    public function renderInput(): string
    {
        if (defined('APP_URL')) {
            \Wonder\App\Dependencies::autonumeric();
        }

        return parent::renderInput();
    }
}
