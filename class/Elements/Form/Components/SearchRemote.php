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

    protected function renderInput(): string
    {
        return '';
    }
}
