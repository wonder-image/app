<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

/**
 * Renderer fedele alla vecchia `password()` di `app/function/frontend/input.php`
 * + icona occhio (`togglePassword`) + lista regole dinamiche.
 *
 * Le regole vivono in `schema['password_rules']` (vedi
 * `Wonder\Elements\Form\Components\InputPassword`). Il client `wonder-image/lib`
 * legge `data-wi-password-rules` sul `<ul>` per aggiornare le icone
 * `bi-x` ↔ `bi-check2` ad ogni `input` event.
 */
class InputPassword extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $inputClass = $this->inputClass();
        $containerClass = $this->containerClass('password').' wi-input-icon-end';

        return <<<HTML
<div class="{$containerClass}">
    <label for="{$id}" class="wi-label">{$this->escape($this->resolvedLabel())}</label>
    <input type="password" id="{$id}" class="{$inputClass}" name="{$name}" value="{$value}" data-wi-check="true"{$this->labelMarker()} {$attributes}>
    <div class="wi-input-icon c-pointer">
        <i class="bi bi-eye" onclick="togglePassword(this, '{$id}')"></i>
    </div>
    {$this->renderError()}
    {$this->renderPasswordRules($id)}
</div>
HTML;
    }

    private function renderPasswordRules(string $id): string
    {
        $rules = (array) ($this->schema['password_rules'] ?? []);

        if ($rules === []) {
            return '';
        }

        $items = [];

        if (isset($rules['min_length']) && (int) $rules['min_length'] > 0) {
            $min = (int) $rules['min_length'];
            $label = $this->translate('Almeno %d caratteri', $min);
            $items[] = $this->renderRuleItem('min-length', $label, ['data-wi-min="'.$min.'"']);
        }

        if (!empty($rules['uppercase'])) {
            $items[] = $this->renderRuleItem('uppercase', $this->translate('Una lettera maiuscola'));
        }

        if (!empty($rules['lowercase'])) {
            $items[] = $this->renderRuleItem('lowercase', $this->translate('Una lettera minuscola'));
        }

        if (!empty($rules['number'])) {
            $items[] = $this->renderRuleItem('number', $this->translate('Un numero'));
        }

        if (!empty($rules['special'])) {
            $items[] = $this->renderRuleItem('special', $this->translate('Un carattere speciale'));
        }

        if ($items === []) {
            return '';
        }

        $list = implode("\n", $items);

        return <<<HTML
<ul class="wi-password-rules" data-wi-password-rules data-wi-target="{$id}">
{$list}
</ul>
HTML;
    }

    /**
     * @param string[] $extraAttrs
     */
    private function renderRuleItem(string $rule, string $label, array $extraAttrs = []): string
    {
        $extra = $extraAttrs === [] ? '' : ' '.implode(' ', $extraAttrs);

        return '<li data-wi-rule="'.$this->escape($rule).'"'.$extra.'><i class="bi bi-x"></i> '.$this->escape($label).'</li>';
    }

    private function translate(string $key, int|string|null $arg = null): string
    {
        if (function_exists('__t')) {
            $translated = (string) __t($key);

            if ($arg !== null && str_contains($translated, '%d')) {
                return sprintf($translated, (int) $arg);
            }

            if ($arg !== null && str_contains($translated, '%s')) {
                return sprintf($translated, (string) $arg);
            }

            return $translated;
        }

        if ($arg !== null && str_contains($key, '%d')) {
            return sprintf($key, (int) $arg);
        }

        return $key;
    }
}
