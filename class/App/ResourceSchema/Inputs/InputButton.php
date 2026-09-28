<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\Button;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Un bottone fra i campi, anche come colonna di un repeater
 * (`Wonder\Elements\Form\Components\Button`).
 *
 * Non posta niente: il nome serve solo a trovarlo nello schema e a leggerne
 * il value, che diventa la didascalia accanto al bottone («Filati Nord ·
 * 12,00 €»). Di default è `outline-secondary`, come i bottoni secondari del
 * backend.
 */
class InputButton extends Input
{
    protected string $helper = 'button';

    /** Il testo del bottone: è la sua etichetta. */
    public function text(string $text): static
    {
        return $this->label($text);
    }

    /** Icona prima del testo (classe Bootstrap Icons, es. `bi bi-truck`). */
    public function icon(string $icon): static
    {
        return $this->context('icon', trim($icon));
    }

    /** Variante di colore del tema (`secondary` di default). */
    public function variant(string $variant): static
    {
        return $this->context('variant', trim($variant));
    }

    public function outline(bool $outline = true): static
    {
        return $this->context('outline', $outline);
    }

    /** `sm`, `lg` o stringa vuota per la misura normale. */
    public function size(string $size): static
    {
        return $this->context('size', trim($size));
    }

    /** La didascalia accanto al bottone quando il value è vuoto. */
    public function emptyCaption(string $caption): static
    {
        return $this->context('empty_caption', $caption);
    }

    /**
     * Al clic apre la finestra con quell'id.
     *
     * Sono gli attributi di Bootstrap, delegati al documento: funzionano
     * anche sulle righe che il repeater clona dopo. Chi deve sapere quale
     * riga ha aperto la finestra la trova in `event.relatedTarget` di
     * `show.bs.modal`.
     */
    public function opensModal(string $modalId): static
    {
        $modalId = trim(str_replace(['"', "'", '#'], '', $modalId));

        if ($modalId === '') {
            return $this;
        }

        return $this->attribute('data-bs-toggle="modal" data-bs-target="#'.$modalId.'"');
    }

    protected function element(): ElementField
    {
        $context = (array) ($this->schema['context'] ?? []);
        $element = new Button($this->name);

        if (isset($context['icon']) && is_string($context['icon']) && $context['icon'] !== '') {
            $element->icon($context['icon']);
        }

        if (isset($context['variant']) && is_string($context['variant'])) {
            $element->variant($context['variant']);
        }

        if (isset($context['outline'])) {
            $element->outline((bool) $context['outline']);
        }

        if (isset($context['size']) && is_string($context['size'])) {
            $element->size($context['size']);
        }

        if (isset($context['empty_caption']) && is_string($context['empty_caption'])) {
            $element->emptyCaption($context['empty_caption']);
        }

        return $element;
    }
}
