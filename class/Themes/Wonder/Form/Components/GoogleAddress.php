<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class GoogleAddress extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $alias = (string) ($this->schema['alias'] ?? '');
        $aliasPrefix = ($name === 'address' || $alias === '') ? '' : $alias.'_';
        $breakdown = is_array($this->schema['breakdown'] ?? null) ? $this->schema['breakdown'] : [];
        $restriction = is_array($this->schema['restriction'] ?? null) ? $this->schema['restriction'] : [];
        $attributesArray = (array) ($this->schema['attributes'] ?? []);
        $isRequired = !empty($attributesArray['required']);
        $spiRequired = $isRequired ? 'required' : '';

        if ($isRequired) {
            unset($attributesArray['required']);
        }

        $attributes = $this->renderAttributes($attributesArray);
        $restrictionJson = $this->escape((string) json_encode($restriction));

        $country = (string) ($breakdown[$aliasPrefix.'country'] ?? '');
        $province = (string) ($breakdown[$aliasPrefix.'province'] ?? '');
        $city = (string) ($breakdown[$aliasPrefix.'city'] ?? '');
        $cap = (string) ($breakdown[$aliasPrefix.'cap'] ?? '');
        $street = (string) ($breakdown[$aliasPrefix.'street'] ?? '');
        $number = (string) ($breakdown[$aliasPrefix.'number'] ?? '');

        $address = '';

        if ($country !== '' && $province !== '' && $cap !== '' && $city !== '' && $street !== '' && $number !== '') {
            $address = $street.', '.$number.', '.$city.', '.$province.', '.$country;
        }

        $escapedAddress = $this->escape($address);

        $hiddenFields = [
            ['country', $country],
            ['province', $province],
            ['city', $city],
            ['cap', $cap],
            ['street', $street],
            ['number', $number],
        ];

        $hiddensHtml = '';

        foreach ($hiddenFields as [$key, $val]) {
            $fieldName = $this->escape($aliasPrefix.$key);
            $fieldValue = $this->escape($val);
            $hiddensHtml .= "<input type=\"hidden\" data-wi-spi=\"{$key}\" data-wi-check=\"true\" name=\"{$fieldName}\" value=\"{$fieldValue}\" {$spiRequired}>";
        }

        return <<<HTML
<div class="w-100">
    <div class="{$this->containerClass('text')}">
        {$this->renderLabel()}
        <input type="text" id="{$id}" class="wi-input" placeholder="" name="{$name}" value="{$escapedAddress}" data-wi-search-place="true" data-wi-restriction="{$restrictionJson}" data-wi-check="true" data-wi-label="true" {$attributes} disabled>
        {$this->renderError()}
        <div class="w-100">{$hiddensHtml}</div>
    </div>
</div>
HTML;
    }
}
