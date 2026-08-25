<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

/**
 * Tema Bootstrap (backend): la ricerca remota (`data-wi-search-*`) è gestita
 * solo dal JS del tema Wonder (frontend). In backend rendiamo un input
 * testuale `form-control` che conserva gli attributi del contratto
 * (`data-wi-search-url`, `data-wi-search-{text|radio}`), così il campo resta
 * coerente e pronto qualora il comportamento remoto venga abilitato anche qui.
 */
class SearchRemote extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $url = $this->escape((string) ($this->schema['url'] ?? ''));
        $searchType = (string) ($this->schema['search_type'] ?? 'text');
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $searchAttr = $searchType === 'radio' ? 'data-wi-search-radio="true"' : 'data-wi-search-text="true"';
        $class = $this->inputClass('form-control');

        return "<input type=\"text\" class=\"{$class}\" id=\"{$id}\" name=\"{$name}\" placeholder=\" \" "
            ."data-wi-search-url=\"{$url}\" {$searchAttr} {$attributes}>";
    }
}
