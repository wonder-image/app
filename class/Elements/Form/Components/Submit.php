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
     * renderer (un eventuale `wi-submit` passato dal caller viene rimosso
     * per evitare duplicati).
     */
    public function buttonClass(string $class): self
    {
        return $this->schema('button_class', self::stripWiSubmit($class));
    }

    /**
     * Aggiunge classi mantenendo quelle già impostate con `buttonClass()`
     * / `class()`. Se nessuna classe è stata impostata, il default
     * theme-specifico viene sostituito (vive nel renderer).
     */
    public function addButtonClass(string $class): self
    {
        $current = trim((string) ($this->getSchema('button_class') ?? ''));
        $merged = trim($current.' '.self::stripWiSubmit($class));

        return $this->schema('button_class', $merged);
    }

    /**
     * Sul bottone la classe va in `button_class` (il generico
     * `attributes.class` di Component non è letto dai renderer Submit).
     * `wi-submit` resta sempre presente: è l'hook del sistema di check
     * JS frontend.
     */
    public function class(string $class): self
    {
        return $this->buttonClass($class);
    }

    public function addClass(string $class): self
    {
        return $this->addButtonClass($class);
    }

    /**
     * Quando impostato, il bottone diventa `type="button"` con
     * `onclick="$callback"` invece di un submit nativo.
     */
    public function onclick(string $callback): self
    {
        return $this->schema('onclick', $callback);
    }

    private static function stripWiSubmit(string $class): string
    {
        $classes = array_filter(
            preg_split('/\s+/', trim($class)) ?: [],
            static fn (string $item): bool => $item !== 'wi-submit'
        );

        return implode(' ', $classes);
    }
}
