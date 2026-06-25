<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

/**
 * Bottone submit del form. Per default `type="submit"`; impostando un
 * `onclick()` diventa `type="button"` con handler JS personalizzato
 * (utile per sottomettere via fetch o multi-step).
 *
 * Inizia disabilitato (`disabled`): è il sistema di check JS lato
 * frontend (`data-wi-check`) che lo abilita quando tutti i campi
 * required sono validi.
 */
class Submit extends Field
{
    public string $type = 'submit';

    public function __construct(string $name = 'upload')
    {
        parent::__construct($name);

        $this->schema('label', 'Salva');
        # Nessun `button_class` di default: è il renderer del tema attivo
        # a scegliere la classe iniziale (Bootstrap → `float-end btn btn-dark`,
        # Wonder → `f-end btn btn-success`). Inserire qui un default
        # tema-specifico (es. `float-end`) verrebbe propagato anche al
        # tema Wonder, che usa la utility class `f-end` di wonder-image/lib.
    }

    /**
     * Sostituisce o aggiunge classi CSS al bottone. Default theme-specifico
     * (vedi `__construct`). La classe `wi-submit` è sempre aggiunta dal
     * renderer.
     */
    public function buttonClass(string $class): self
    {
        return $this->schema('button_class', trim($class));
    }

    /**
     * Aggiunge classi mantenendo quelle di default.
     */
    public function addButtonClass(string $class): self
    {
        $current = trim((string) ($this->getSchema('button_class') ?? ''));
        $merged = trim($current.' '.$class);

        return $this->schema('button_class', $merged);
    }

    /**
     * Quando impostato, il bottone diventa `type="button"` con
     * `onclick="$callback"` invece di un submit nativo.
     */
    public function onclick(string $callback): self
    {
        return $this->schema('onclick', $callback);
    }
}
