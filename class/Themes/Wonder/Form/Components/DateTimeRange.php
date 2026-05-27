<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class DateTimeRange extends Field
{
    public function renderInput(): string
    {
        $baseId = $this->escape((string) ($this->schema['id'] ?? 'datetime-range'));
        $name = (string) ($this->schema['name'] ?? '');
        $value = is_array($this->schema['value'] ?? null) ? $this->schema['value'] : ['', ''];
        $attributesArray = (array) ($this->schema['attributes'] ?? []);
        $attributes = $this->renderAttributes($attributesArray);
        $fromName = $this->escape($name.'-from');
        $toName = $this->escape($name.'-to');
        $fromValue = $this->escape((string) ($value[0] ?? ''));
        $toValue = $this->escape((string) ($value[1] ?? ''));
        $min = $this->jsString($attributesArray['data-wi-min-date'] ?? '');
        $max = $this->jsString($attributesArray['data-wi-max-date'] ?? '');

        return <<<HTML
<div class="{$this->containerClass('datetimerange')}">
    {$this->renderLabel()}
    <input type="text" id="{$baseId}-from" class="wi-input wi-datetimerange-from" name="{$fromName}" placeholder="gg/mm/aaaa h:m" value="{$fromValue}" data-wi-check="true"{$this->labelMarker()} {$attributes}>
    <span class="wi-input-text">-</span>
    <input type="text" id="{$baseId}-to" class="wi-input wi-datetimerange-to" name="{$toName}" placeholder="gg/mm/aaaa h:m" value="{$toValue}" data-wi-check="true"{$this->labelMarker()} {$attributes}>
    {$this->renderError()}
</div>
<script>
    $(function () {
        var options = {
            showAnim: 'slideDown',
            yearRange: '1900:3000',
            controlType: 'select',
            oneLine: true,
            showOn: 'focus',
            dateFormat: 'dd/mm/yy',
            timeFormat: 'HH:mm',
            changeYear: true,
            changeMonth: true,
            showMonthAfterYear: true,
            hideIfNoPrevNext: true,
            stepMinute: 5,
            firstDay: 1,
            dayNames: [ 'Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato' ],
            dayNamesShort: [ 'Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab' ],
            dayNamesMin: [ 'Do', 'Lu', 'Ma', 'Me', 'Gi', 'Ve', 'Sa' ],
            monthNames: [ 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre' ],
            monthNamesShort: [ 'Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic' ],
            beforeShow: function () {
                document.getElementById('{$baseId}-from').parentElement.classList.add('selector-show');
                if (typeof customDateTimeRange === 'function') {
                    customDateTimeRange(document.getElementById('{$baseId}-from').parentElement);
                }
            },
            onClose: function () {
                document.getElementById('{$baseId}-from').parentElement.classList.remove('selector-show');
                if (typeof customDateTimeRange === 'function') {
                    customDateTimeRange(document.getElementById('{$baseId}-from').parentElement);
                }
            }
        };

        if ({$min} !== '') { options.minDate = {$min}; }
        if ({$max} !== '') { options.maxDate = {$max}; }

        $('#{$baseId}-from, #{$baseId}-to').datetimepicker(options);
    });
</script>
HTML;
    }

    private function jsString(mixed $value): string
    {
        return json_encode(is_scalar($value) ? (string) $value : '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
