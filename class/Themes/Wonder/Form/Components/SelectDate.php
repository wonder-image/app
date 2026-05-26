<?php

namespace Wonder\Themes\Wonder\Form\Components;

/**
 * Renderer Wonder di `SelectDate`. Differenza rispetto a `DatePicker`:
 * include la validazione inline `on change` con i messaggi di errore
 * "deve essere minore/maggiore del…" usati nel frontend storico.
 */
class SelectDate extends DatePicker
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributesArray = (array) ($this->schema['attributes'] ?? []);
        $attributes = $this->renderAttributes($attributesArray);
        $class = $this->inputClass();
        $rawLabel = strtolower(str_replace('*', '', $this->resolvedLabel()));
        $labelJs = $this->jsString($rawLabel);
        $dateMin = (string) ($attributesArray['data-wi-min-date'] ?? '');
        $dateMax = (string) ($attributesArray['data-wi-max-date'] ?? '');
        $checkMin = $dateMin !== '' ? 'true' : 'false';
        $checkMax = $dateMax !== '' ? 'true' : 'false';
        $minJs = $this->jsString($dateMin);
        $maxJs = $this->jsString($dateMax);
        $valueJs = $this->jsString((string) ($this->schema['value'] ?? ''));

        return <<<HTML
<div class="{$this->containerClass('date')}">
    {$this->renderLabel()}
    <input type="text" id="{$id}" class="{$class}" name="{$name}" placeholder="gg/mm/aaaa" data-wi-check="true" {$attributes} value="{$value}">
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

        if ({$checkMin}) { options.minDate = {$minJs}; }
        if ({$checkMax}) { options.maxDate = {$maxJs}; }

        $('#{$id}').datepicker(options);

        $('#{$id}').on('change', function () {
            var input = document.getElementById('{$id}');
            var container = input.parentElement;
            var spanAlert = container.querySelector('.alert-error');
            var date = input.value;
            var label = {$labelJs};

            if (date === '') return;

            if (!moment(date, 'DD/MM/YYYY', true).isValid()) {
                input.setCustomValidity('Invalid date');
                container.classList.add('input-error');
                if (spanAlert) spanAlert.innerHTML = "<i class='bi bi-exclamation-triangle'></i> La " + label + " deve essere formato gg/mm/aaaa";
                return;
            }

            var dateMs = moment(date, 'DD/MM/YYYY');
            input.setCustomValidity('');
            container.classList.remove('input-error');
            if (spanAlert) spanAlert.innerHTML = '';

            if ({$checkMin}) {
                var min = moment({$minJs}, 'DD/MM/YYYY').milliseconds();
                if (dateMs < min) {
                    input.setCustomValidity('Invalid date');
                    container.classList.add('input-error');
                    if (spanAlert) spanAlert.innerHTML = "<i class='bi bi-exclamation-triangle'></i> La " + label + " deve essere minore del " + {$maxJs};
                }
            }

            if ({$checkMax}) {
                var max = moment({$maxJs}, 'DD/MM/YYYY');
                if (dateMs > max) {
                    input.setCustomValidity('Invalid date');
                    container.classList.add('input-error');
                    if (spanAlert) spanAlert.innerHTML = "<i class='bi bi-exclamation-triangle'></i> La " + label + " deve essere maggiore del " + {$maxJs};
                }
            }
        });

        if ({$valueJs} !== '') {
            $('#{$id}').datepicker('setDate', {$valueJs});
        }
    });
</script>
HTML;
    }
}
