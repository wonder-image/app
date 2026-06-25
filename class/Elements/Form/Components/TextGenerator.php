<?php

namespace Wonder\Elements\Form\Components;

/**
 * Text input con bottone "GENERA" che invoca `generateCode('#input-id')`
 * lato JS per pre-popolare il campo (usato in admin per slug/codici
 * casuali). Estende `InputText` perché markup base e attributi sono
 * gli stessi; il renderer del tema aggiunge il bottone dentro il
 * wrap form-floating.
 */
class TextGenerator extends InputText
{
    /**
     * Label del bottone (default "GENERA").
     */
    public function buttonLabel(string $label): self
    {
        return $this->schema('button_label', trim($label));
    }

    /**
     * Callback JS invocato al click. Riceve il selector dell'input come
     * stringa. Default: `generateCode`.
     */
    public function callback(string $callback): self
    {
        return $this->schema('callback', trim($callback));
    }
}
