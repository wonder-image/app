<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class DateRange extends Field
{
    public function renderInput(): string
    {
        $baseId = $this->escape((string) ($this->schema['id'] ?? 'date-range'));
        $name = (string) ($this->schema['name'] ?? '');
        $value = is_array($this->schema['value'] ?? null) ? $this->schema['value'] : ['', ''];
        $attributesArray = (array) ($this->schema['attributes'] ?? []);
        $attributes = $this->renderAttributes($attributesArray);
        $fromName = $this->escape($name.'_from');
        $toName = $this->escape($name.'_to');
        $fromValue = $this->escape((string) ($value[0] ?? ''));
        $toValue = $this->escape((string) ($value[1] ?? ''));
        $fromValueJs = $this->jsString($value[0] ?? '');
        $toValueJs = $this->jsString($value[1] ?? '');
        $min = $this->jsString($attributesArray['data-wi-min-date'] ?? '');
        $max = $this->jsString($attributesArray['data-wi-max-date'] ?? '');

        return <<<HTML
<div class="{$this->containerClass('daterange')}">
    {$this->renderLabel()}
    <input type="text" id="{$baseId}-from" class="wi-input wi-daterange-from" name="{$fromName}" value="{$fromValue}" placeholder="gg/mm/aaaa" data-wi-check="true"{$this->labelMarker()} readonly {$attributes}>
    <span class="wi-input-text">-</span>
    <input type="text" id="{$baseId}-to" class="wi-input wi-daterange-to" name="{$toName}" value="{$toValue}" placeholder="gg/mm/aaaa" data-wi-check="true"{$this->labelMarker()} readonly {$attributes}>
    {$this->renderError()}
</div>
<script>
    $(function () {
        var options = {
            showAnim: 'slideDown',
            yearRange: '1900:3000',
            showOn: 'focus',
            dateFormat: 'dd/mm/yy',
            changeYear: true,
            changeMonth: true,
            showMonthAfterYear: true,
            hideIfNoPrevNext: true,
            firstDay: 1,
            dayNames: [ 'Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato' ],
            dayNamesShort: [ 'Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab' ],
            dayNamesMin: [ 'Do', 'Lu', 'Ma', 'Me', 'Gi', 'Ve', 'Sa' ],
            monthNames: [ 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre' ],
            monthNamesShort: [ 'Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic' ],
            beforeShow: function () {
                document.getElementById('{$baseId}-from').parentElement.classList.add('selector-show');
                if (typeof customDateRange === 'function') {
                    customDateRange(document.getElementById('{$baseId}-from').parentElement);
                }
            },
            onClose: function () {
                document.getElementById('{$baseId}-from').parentElement.classList.remove('selector-show');
                if (typeof customDateRange === 'function') {
                    customDateRange(document.getElementById('{$baseId}-from').parentElement);
                }
            }
        };

        if ({$min} !== '') {
            options.minDate = {$min};
        }

        if ({$max} !== '') {
            options.maxDate = {$max};
        }

        $('#{$baseId}-from, #{$baseId}-to').datepicker(options);

        if ({$fromValueJs} !== '') {
            $('#{$baseId}-from').datepicker('setDate', {$fromValueJs});
        }

        if ({$toValueJs} !== '') {
            $('#{$baseId}-to').datepicker('setDate', {$toValueJs});
        }
    });
</script>
HTML;
    }

    private function jsString(mixed $value): string
    {
        return json_encode(is_scalar($value) ? (string) $value : '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
