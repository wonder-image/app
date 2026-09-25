<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

/**
 * Un bottone fra i campi del form, con accanto una didascalia.
 *
 * Non è un dato: il renderer non stampa né `name` né `id`, così niente
 * parte con il form e le righe clonate di un repeater non si portano dietro
 * lo stesso id. Il value non va nel bottone ma nella didascalia accanto
 * («Filati Nord · 12,00 €»); `empty_caption` è quella da mostrare quando il
 * value è vuoto. Cosa fa al clic lo dicono gli attributi (`data-bs-toggle`,
 * `data-bs-target`, `data-*` per il JS del modulo).
 */
class Button extends Field
{
    public string $type = 'button';

    public function __construct(string $name)
    {
        parent::__construct($name);

        // Il sistema di check JS guarda i campi da compilare: un bottone non
        // ha niente da compilare.
        $this->removeAttr('data-wi-check');
        $this->schema('variant', 'secondary');
        $this->schema('outline', true);
    }

    /** Icona prima del testo (classe Bootstrap Icons, es. `bi bi-truck`). */
    public function icon(string $icon): self
    {
        return $this->schema('icon', trim($icon));
    }

    public function variant(string $variant): self
    {
        $variant = strtolower(trim($variant));

        return $this->schema('variant', $variant !== '' ? $variant : 'secondary');
    }

    public function outline(bool $outline = true): self
    {
        return $this->schema('outline', $outline);
    }

    /** `sm`, `lg` o stringa vuota per la misura normale. */
    public function size(string $size): self
    {
        return $this->schema('size', strtolower(trim($size)));
    }

    /** La didascalia da mostrare quando il value è vuoto. */
    public function emptyCaption(string $caption): self
    {
        return $this->schema('empty_caption', $caption);
    }
}
