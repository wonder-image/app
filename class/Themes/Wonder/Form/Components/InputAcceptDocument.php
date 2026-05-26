<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class InputAcceptDocument extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $documentType = $this->escape((string) ($this->schema['document_type'] ?? ''));
        $documentId = (int) ($this->schema['document_id'] ?? 0);
        $documentLabel = (string) ($this->schema['document_label'] ?? '');
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $checked = !empty($this->schema['attributes']['checked']) ? ' checked' : '';

        if (!empty($this->schema['attributes']['required'])) {
            $documentLabel .= '*';
        }

        return <<<HTML
<input type="hidden" name="{$documentType}_id" value="{$documentId}">
<div class="wi-input-container checkbox compiled">
    <div class="wi-checkbox-list">
        <div class="wi-checkbox-container">
            <input type="checkbox" id="{$id}" class="wi-checkbox" name="{$name}" value="true" data-wi-check="true"{$checked} {$attributes}>
            <div class="wi-checkbox-icon"><i class="bi bi-check-lg"></i></div>
            <label for="{$id}" class="wi-checkbox-label unselectable">{$documentLabel}</label>
        </div>
    </div>
</div>
HTML;
    }
}
