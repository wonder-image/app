<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

/**
 * Campo di ricerca remoto: text input + dropdown popolata via AJAX
 * dall'URL configurato. Usato in frontend per ricerche libere
 * (`searchText`) o a selezione singola (`searchRadio`). Il `searchType`
 * cambia l'attributo `data-wi-search-{type}` letto dallo script
 * client per scegliere il comportamento di selezione.
 */
class SearchRemote extends Field
{
    public string $type = 'text';

    public function url(string $url): self
    {
        return $this->schema('url', $url);
    }

    /**
     * Tipo di ricerca: 'text' (free-form search) o 'radio' (selezione
     * singola con submit del value scelto).
     */
    public function searchType(string $type): self
    {
        return $this->schema('search_type', $type === 'radio' ? 'radio' : 'text');
    }

    /**
     * Il testo mostrato nella casella per il value già scelto (il value è
     * un id, il testo la sua etichetta): senza, la casella parte vuota.
     */
    public function valueLabel(string $label): self
    {
        return $this->schema('value_label', $label);
    }

    /** La frase sotto la casella quando la ricerca non trova nulla. */
    public function emptyText(string $text): self
    {
        return $this->schema('empty_text', $text);
    }

    protected function renderInput(): string
    {
        return '';
    }
}
