<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\GoogleAddress as GoogleAddressElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Indirizzo con autocomplete Google Places più i sei hidden field del
 * breakdown (via, civico, città, provincia, CAP, paese).
 */
class InputGoogleAddress extends Input
{
    protected string $helper = 'googleAddress';

    /**
     * Restrizioni Google Places, es. `['country' => 'it']`.
     * Un array vuoto lascia la ricerca senza vincoli.
     */
    public function restriction(array $restriction): static
    {
        return $restriction !== [] ? $this->context('restriction', $restriction) : $this;
    }

    /**
     * Prefisso dei sei hidden field. Senza alias esplicito si usa il `name`
     * del campo: passa un alias quando servono più indirizzi nello stesso form.
     */
    public function alias(string $alias): static
    {
        $alias = trim($alias);

        return $alias !== '' ? $this->context('alias', $alias) : $this;
    }

    protected function element(): ElementField
    {
        $context = (array) ($this->schema['context'] ?? []);
        $value = $this->schema['value'] ?? null;

        $element = (new GoogleAddressElement($this->name))
            ->alias((string) ($context['alias'] ?? $this->name))
            ->restriction(is_array($context['restriction'] ?? null) ? $context['restriction'] : []);

        # breakdown: priorità a context['breakdown'], fallback al value se è array
        if (is_array($context['breakdown'] ?? null)) {
            $element->breakdown($context['breakdown']);
        } elseif (is_array($value)) {
            $element->breakdown($value);
        }

        return $element;
    }
}
