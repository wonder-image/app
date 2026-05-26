<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class SearchRemote extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $rawName = (string) ($this->schema['name'] ?? '');
        $url = $this->escape((string) ($this->schema['url'] ?? ''));
        $searchType = (string) ($this->schema['search_type'] ?? 'text');
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $isRadio = $searchType === 'radio';

        $nameAttr = $isRadio ? '' : ' name="'.$this->escape($rawName).'"';
        $searchAttr = $isRadio ? 'data-wi-search-radio="true"' : 'data-wi-search-text="true"';

        return <<<HTML
<div class="{$this->containerClass('search-url')}">
    {$this->renderLabel()}
    <input type="text" id="{$id}" class="wi-input {$this->escape($rawName)}-value" value="" data-wi-label="true" data-wi-name="{$this->escape($rawName)}-text"{$nameAttr} data-wi-search-url="{$url}" {$searchAttr} {$attributes}>
    <span class="alert-error"></span>
    <div id="list_{$id}" class="wi-input-list no-scrollbar">
        <div class="w-100 wi-input-list-body"></div>
        <div class="wi-input-list-footer">Cosa stai cercando?</div>
    </div>
</div>
HTML;
    }
}
