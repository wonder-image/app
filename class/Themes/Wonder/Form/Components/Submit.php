<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

/**
 * Renderer Wonder del bottone `Submit`. Markup minimale tipico del
 * frontend pubblico: solo un `<button>` con classe `btn {colore}
 * wi-submit`. La classe iniziale "btn …" viene fornita dal caller
 * (es. `submit('Invia', 'send', 'btn-success')`).
 */
class Submit extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $label = $this->escape((string) ($this->schema['label'] ?? 'Invia'));
        # Default Wonder: utility class `f-end` di wonder-image/lib (NON
        # `float-end` di Bootstrap). Le classi `btn`/`btn-success` sono
        # in lib come btn neutro + variante colore.
        $buttonClass = $this->escape(trim((string) ($this->schema['button_class'] ?? 'f-end btn btn-success').' wi-submit'));
        $onclick = trim((string) ($this->schema['onclick'] ?? ''));

        $action = $onclick === ''
            ? 'type="submit"'
            : 'type="button" onclick="'.$this->escape($onclick).'"';

        return "<button {$action} id=\"{$id}\" class=\"{$buttonClass}\" name=\"{$name}\" disabled>{$label}</button>";
    }
}
