<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\Toggle as ToggleElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Interruttore acceso/spento con etichetta e descrizione, per le pagine di
 * configurazione: ogni riga è una scelta con il suo nome e una spiegazione
 * breve sotto.
 *
 * ```php
 * FormField::key('orders')->toggle()
 *     ->label('Ordini')
 *     ->description('Gestione degli ordini con stati e scarico del magazzino.');
 * ```
 */
class InputToggle extends Input
{
    protected string $helper = 'toggle';

    /** Testo sotto l'etichetta: a cosa serve l'interruttore. */
    public function description(string $description): static
    {
        $description = trim($description);

        return $description !== '' ? $this->context('description', $description) : $this;
    }

    /** Valori postati da acceso e da spento (default `true` e `false`). */
    public function values(string $on, string $off = 'false'): static
    {
        return $this->context('toggle_values', [$on, $off]);
    }

    protected function element(): ElementField
    {
        $context = (array) ($this->schema['context'] ?? []);
        $values = is_array($context['toggle_values'] ?? null) ? $context['toggle_values'] : ['true', 'false'];
        $values = array_pad($values, 2, '');

        $element = (new ToggleElement($this->name))
            ->values((string) $values[0], (string) $values[1])
            ->value($this->elementValue());

        if (isset($context['description']) && is_string($context['description'])) {
            $element->description($context['description']);
        }

        return $element;
    }
}
