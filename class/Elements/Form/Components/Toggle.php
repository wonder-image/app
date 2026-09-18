<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

/**
 * Interruttore acceso/spento con etichetta e descrizione.
 *
 * Nasce per le pagine di configurazione dove ogni riga è una scelta con un
 * nome e una spiegazione breve (es. il pannello "Funzionalità" del
 * gestionale). I due valori postati si scelgono con `values()`; un campo
 * nascosto manda sempre il valore "spento", così il form trasmette qualcosa
 * anche quando l'interruttore è staccato.
 */
class Toggle extends Field
{
    public string $type = 'checkbox';

    /** Testo sotto l'etichetta: a cosa serve l'interruttore. */
    public function description(string $description): self
    {
        return $this->schema('description', $description);
    }

    /** Valori postati da acceso e da spento (default `true` e `false`). */
    public function values(string $on, string $off = 'false'): self
    {
        return $this->schema('toggle_values', [$on, $off]);
    }

    protected function renderInput(): string
    {
        return '';
    }
}
