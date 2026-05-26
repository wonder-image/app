<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

/**
 * Check (checkbox/radio) "dinamico": invece di una lista statica di
 * opzioni mostra un campo di ricerca che query-a un URL e popola i
 * risultati lato client. Usato per associazioni many-to-many con
 * grosse cardinalità (utenti, categorie, prodotti).
 */
class DynamicCheck extends Field
{
    public string $type = 'checkbox';

    public function url(string $url): self
    {
        return $this->schema('url', $url);
    }

    public function inputType(string $type): self
    {
        return $this->schema('type', $type);
    }

    protected function renderInput(): string
    {
        return '';
    }
}
