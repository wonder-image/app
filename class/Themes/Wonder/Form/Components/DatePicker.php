<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class DatePicker extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributesArray = (array) ($this->schema['attributes'] ?? []);
        $attributes = $this->renderAttributes($attributesArray);
        $class = $this->inputClass();
        $min = $this->jsString($attributesArray['data-wi-min-date'] ?? '');
        $max = $this->jsString($attributesArray['data-wi-max-date'] ?? '');
        $valueJs = $this->jsString($this->schema['value'] ?? '');

        return <<<HTML
<div class="{$this->containerClass('date')}">
    {$this->renderLabel()}
    <input type="text" id="{$id}" class="{$class}" name="{$name}" placeholder="gg/mm/aaaa" value="{$value}" data-wi-check="true"{$this->labelMarker()} {$attributes}>
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
                document.getElementById('{$id}').parentElement.classList.add('selector-show');
            },
            onClose: function () {
                document.getElementById('{$id}').parentElement.classList.remove('selector-show');
            }
        };

        if ({$min} !== '') {
            options.minDate = {$min};
        }

        if ({$max} !== '') {
            options.maxDate = {$max};
        }

        $('#{$id}').datepicker(options);

        if ({$valueJs} !== '') {
            $('#{$id}').datepicker('setDate', {$valueJs});
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
