<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

class Select extends Field
{
    public string $type = 'select';

    public function options(array $options): self
    {
        return $this->schema('options', $options);
    }

    public function placeholder(string $label = 'Seleziona'): self
    {
        $options = is_array($this->getSchema('options')) ? $this->getSchema('options') : [];

        return $this->schema('options', ['' => $label] + $options);
    }

    /**
     * Modalità "legacy" (versione storica del select del backend, equivalente
     * al vecchio `select($label, $name, $opt, 'old', ...)`): wrappa il select
     * in un `<div id="container-…" class="wi-container-select">` con la label
     * sopra invece del pattern floating. Usata in alcune pagine di filtro.
     */
    public function legacyContainer(bool $legacy = true): self
    {
        return $this->schema('legacy_container', $legacy);
    }

    protected function renderInput(): string
    {
        return '';
    }
}
