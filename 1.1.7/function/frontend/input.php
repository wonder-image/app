<?php

    function text($label, $name, $value = null, $attribute = '', $error = false){

        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "";

        if (!empty($error)) {
            $class .= " input-error";
            $alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> $error</span>";
        } else {
            $alert = "<span class='alert-error'></span>";
        }

        if (!empty($value)) { $class .= " compiled"; }
        if (strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='wi-input-container text$class'>
            <label for='$id' class='wi-label'>$label</label>
            <input type='text' id='$id' class='wi-input' name='$name' value='$value' data-wi-check='true' data-wi-label='true' $attribute>
            $alert
        </div>
        ";

    }

    function number($label, $name, $value = null, $attribute = '', $error = false){

        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "";

        if (!empty($error)) {
            $class .= " input-error";
            $alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> $error</span>";
        } else {
            $alert = "<span class='alert-error'></span>";
        }

        if (!empty($value) || $value == 0) { $class .= " compiled"; }
        if (strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='wi-input-container text$class'>
            <label for='$id' class='wi-label'>$label</label>
            <input type='text' id='$id' class='wi-input' name='$name' value='$value' data-wi-check='true' data-wi-label='true' data-wi-number='true' $attribute>
            $alert
        </div>";

    }

    function price($label, $name, $value = null, $attribute = '', $error = false){

        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "";

        if (!empty($error)) {
            $class .= " input-error";
            $alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> $error</span>";
        } else {
            $alert = "<span class='alert-error'></span>";
        }

        if (!empty($value) || $value == 0) { $class .= " compiled"; }
        if (strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='wi-input-container text$class'>
            <label for='$id' class='wi-label'>$label</label>
            <input type='text' id='$id' class='wi-input a-r' name='$name' value='$value' data-wi-check='true' data-wi-label='true' data-wi-price='true' $attribute>
            $alert
        </div>";

    }

    function percentige($label, $name, $value = null, $attribute = '', $error = false){

        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "";

        if (!empty($error)) {
            $class .= " input-error";
            $alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> $error</span>";
        } else {
            $alert = "<span class='alert-error'></span>";
        }

        if (!empty($value) || $value == 0) { $class .= " compiled"; }
        if (strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='wi-input-container text$class'>
            <label for='$id' class='wi-label'>$label</label>
            <input type='text' id='$id' class='wi-input a-r' name='$name' value='$value' data-wi-check='true' data-wi-label='true' data-wi-percentige='true' $attribute>
            $alert
        </div>";

    }

    function email($label, $name, $value = null, $attribute = '', $error = false){

        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "";

        if (!empty($error)) {
            $class .= " input-error";
            $alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> $error</span>";
        } else {
            $alert = "<span class='alert-error'></span>";
        }

        if (!empty($value) ) { $class .= " compiled"; }
        if (strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='wi-input-container email$class'>
            <label for='$id' class='wi-label'>$label</label>
            <input type='email' id='$id' class='wi-input' name='$name' value='$value' data-wi-check='true' data-wi-label='true' $attribute>
            $alert
        </div>";

    }

    function textarea($label, $name, $value = null, $attribute = '', $error = false){

        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "";

        if (!empty($error)) {
            $class .= " input-error";
            $alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> $error</span>";
        } else {
            $alert = "<span class='alert-error'></span>";
        }

        if (!empty($value)) { $class .= " compiled"; }
        if (strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='wi-input-container textarea$class'>
            <label for='$id' class='wi-label'>$label</label>
            <textarea name='$name' id='$id' class='wi-input' onkeyup='check()' data-wi-check='true' data-wi-label='true' $attribute>$value</textarea>
            $alert
        </div>";

    }

    function password($label, $name, $value = null, $attribute = '', $error = false) {

        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "";

        if (!empty($error)) {
            $class .= " input-error";
            $alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> $error</span>";
        } else {
            $alert = "<span class='alert-error'></span>";
        }

        if (!empty($value)) { $class .= " compiled"; }
        if (strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='wi-input-container wi-input-icon-end password$class'>
            <label for='$id' class='wi-label'>$label</label>
            <input type='password' id='$id' class='wi-input' name='$name' value='$value' data-wi-check='true' data-wi-label='true' $attribute>
            <div class='wi-input-icon c-pointer'>
                <i class='bi bi-eye' onclick=\"togglePassword(this, '$id')\"></i>
            </div>
            $alert
        </div>";
        
    }

    function selectDate($label, $name, $value = null, $attribute = '', $dateMin = null, $dateMax = null, $error = false){

        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "";
        $setUpDate = "";

        if (!empty($error)) {
            $class .= " input-error";
            $alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> $error</span>";
        } else {
            $alert = "<span class='alert-error'></span>";
        }

        // Date format: dd/mm/yyyy = date('d/m/Y')

        if ($dateMin == null) {
            $checkMin = 'false';
            $dateMin = '';
            $min = '';
        }else{
            $checkMin = 'true';
            $min = "minDate: '$dateMin',";
        }

        if ($dateMax == null) {
            $checkMax = 'false';
            $dateMax = '';
            $max = '';
        }else{
            $checkMax = 'true';
            $max = "maxDate: '$dateMax',";
        }

        if (!empty($value)) { $setUpDate = "$('#$id').datepicker('setDate','$value');"; }
        if (strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='wi-input-container date compiled$class'>
            <label for='$id' class='wi-label'>$label</label>
            <input type='text' id='$id' class='wi-input' name='$name' placeholder='gg/mm/aaaa' data-wi-check='true' $attribute>
            $alert
        </div>
        <script>
            $(function(){

                $('#$id').datepicker({
                    showAnim: 'slideDown',
                    yearRange: '1900:3000',
                    $max
                    $min
                    showOn: 'focus',
                    dateFormat:'dd/mm/yy',
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
                        document.getElementById('$id').parentElement.classList.add('selector-show');
                    },
                    onClose: function () {
                        document.getElementById('$id').parentElement.classList.remove('selector-show');
                    }
                });

                $('#$id').datepicker().on('change', function () {
                    
                    var input = document.getElementById('$id');
                    var container = input.parentElement;
                    var spanAlert = container.querySelector('.alert-error');
                    var date = input.value;

                    if (date != '') {
                        if (moment(date, 'DD/MM/YYYY', true).isValid()) {

                            var dateMilliseconds = moment(date, 'DD/MM/YYYY');

                            input.setCustomValidity('');
                            container.classList.remove('input-error');
                            spanAlert.innerHTML = ''; 

                            if ($checkMin) {
                                var min = moment('$dateMin', 'DD/MM/YYYY').milliseconds();
                                if (dateMilliseconds < min) {
                                    input.setCustomValidity('Invalid date');
                                    container.classList.add('input-error');
                                    spanAlert.innerHTML = \"<i class='bi bi-exclamation-triangle'></i> La ".strtolower(str_replace('*', '', $label))." deve essere minore del $dateMax\"; 
                                }
                            }

                            if ($checkMax) {
                                var max = moment('$dateMax', 'DD/MM/YYYY');
                                if (dateMilliseconds > max) {
                                    input.setCustomValidity('Invalid date');
                                    container.classList.add('input-error');
                                    spanAlert.innerHTML = \"<i class='bi bi-exclamation-triangle'></i> La ".strtolower(str_replace('*', '', $label))." deve essere maggiore del $dateMax\"; 
                                }
                            }

                        } else {
                            input.setCustomValidity('Invalid date');
                            container.classList.add('input-error');
                            spanAlert.innerHTML = \"<i class='bi bi-exclamation-triangle'></i> La ".strtolower(str_replace('*', '', $label))." deve essere formato gg/mm/aaaa\"; 
                        }
                    }

                });

                $setUpDate

            });
        </script>
        ";

    }

    function dateRange($label, $name, $value = null, $attribute = '', $dateMin = null, $dateMax = null, $error = false){

        $idFrom = strtolower(code(10, 'letters', 'input_'));
        $idTo = strtolower(code(10, 'letters', 'input_'));

        $class = "";
        $setUpDate = "";

        if (!empty($error)) {
            $class .= " input-error";
            $alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> $error</span>";
        } else {
            $alert = "<span class='alert-error'></span>";
        }

        // Date format: dd/mm/yyyy = date('d/m/Y')

        if ($dateMin == null) {
            $checkMin = 'false';
            $dateMin = '';
            $min = '';
        }else{
            $checkMin = 'true';
            $min = "minDate: '$dateMin',";
        }

        if ($dateMax == null) {
            $checkMax = 'false';
            $dateMax = '';
            $max = '';
        }else{
            $checkMax = 'true';
            $max = "maxDate: '$dateMax',";
        }

        if (!empty($value)) {
            $valueFrom = $value[0];
            $valueTo = $value[1];
        }

        if (!empty($valueFrom)) { $setUpDate .= "$('#$idFrom').datepicker('setDate','$valueFrom');"; }
        if (!empty($valueTo)) { $setUpDate .= "$('#$idTo').datepicker('setDate','$valueTo');"; }
        if (strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='wi-input-container daterange compiled$class'>
            <label for='$idFrom' class='wi-label'>$label</label>
            <input type='text' id='$idFrom' class='wi-input wi-daterange-from' name='$name-from' placeholder='gg/mm/aaaa' data-wi-check='true' $attribute>
            <span class='wi-input-text'>-</span>
            <input type='text' id='$idTo' class='wi-input wi-daterange-to' name='$name-to' placeholder='gg/mm/aaaa' data-wi-check='true' $attribute>
            $alert
        </div>
        <script>
            $(function(){
                $('#$idFrom, #$idTo').datepicker({
                    showAnim: 'slideDown',
                    yearRange: '1900:3000',
                    $max
                    $min
                    showOn: 'focus',
                    dateFormat:'dd/mm/yy',
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
                        document.getElementById('$idFrom').parentElement.classList.add('selector-show');
                        customDateRange(document.getElementById('$idFrom').parentElement);
                    },
                    onClose: function () {
                        document.getElementById('$idFrom').parentElement.classList.remove('selector-show');
                        customDateRange(document.getElementById('$idFrom').parentElement);
                    }
                });

                $('#$idFrom, #$idTo').datepicker().on('change', function () {

                    check();
                    
                    var inputFrom = document.getElementById('$idFrom');
                    var inputTo = document.getElementById('$idTo');
                    var container = inputFrom.parentElement;
                    var spanAlert = container.querySelector('.alert-error');

                    var dateFrom = inputFrom.value;
                    var dateTo = inputFrom.value;

                    if (dateFrom != '' || dateTo != '') {

                        if (moment(dateFrom, 'DD/MM/YYYY', true).isValid() || moment(dateTo, 'DD/MM/YYYY', true).isValid()) {

                            var dateMillisecondsFrom = moment(dateFrom, 'DD/MM/YYYY');
                            var dateMillisecondsTo = moment(dateTo, 'DD/MM/YYYY');

                            inputFrom.setCustomValidity('');
                            inputTo.setCustomValidity('');
                            container.classList.remove('input-error');
                            spanAlert.innerHTML = ''; 

                            if ($checkMin) {
                                var min = moment('$dateMin', 'DD/MM/YYYY').milliseconds();
                                if (dateMillisecondsFrom < min || dateMillisecondsTo < min) {
                                    inputFrom.setCustomValidity('Invalid date');
                                    inputTo.setCustomValidity('Invalid date');
                                    container.classList.add('input-error');
                                    spanAlert.innerHTML = \"<i class='bi bi-exclamation-triangle'></i> La ".strtolower(str_replace('*', '', $label))." deve essere minore del $dateMax\"; 
                                }
                            }

                            if ($checkMax) {
                                var max = moment('$dateMax', 'DD/MM/YYYY');
                                if (dateMillisecondsFrom < max || dateMillisecondsTo < max) {
                                    inputFrom.setCustomValidity('Invalid date');
                                    inputTo.setCustomValidity('Invalid date');
                                    container.classList.add('input-error');
                                    spanAlert.innerHTML = \"<i class='bi bi-exclamation-triangle'></i> La ".strtolower(str_replace('*', '', $label))." deve essere maggiore del $dateMax\"; 
                                }
                            }

                        } else {
                            inputFrom.setCustomValidity('Invalid date');
                            inputTo.setCustomValidity('Invalid date');
                            container.classList.add('input-error');
                            spanAlert.innerHTML = \"<i class='bi bi-exclamation-triangle'></i> La ".strtolower(str_replace('*', '', $label))." deve essere formato gg/mm/aaaa\"; 
                        }
                    }

                });

                $setUpDate

            });
        </script>
        ";

    }

    function dateTimeRange($label, $name, $value = null, $attribute = '', $dateMin = null, $dateMax = null, $error = false){

        $idFrom = strtolower(code(10, 'letters', 'input_'));
        $idTo = strtolower(code(10, 'letters', 'input_'));

        $class = "";
        $setUpDate = "";

        if (!empty($error)) {
            $class .= " input-error";
            $alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> $error</span>";
        } else {
            $alert = "<span class='alert-error'></span>";
        }

        if ($dateMin == null) {
            $checkMin = 'false';
            $dateMin = '';
            $min = '';
        }else{
            $checkMin = 'true';
            $min = "minDate: '$dateMin',";
        }

        if ($dateMax == null) {
            $checkMax = 'false';
            $dateMax = '';
            $max = '';
        }else{
            $checkMax = 'true';
            $max = "maxDate: '$dateMax',";
        }

        if (!empty($value)) {
            $valueFrom = $value[0];
            $valueTo = $value[1];
        } else {
            $valueFrom = "";
            $valueTo = "";
        }

        if (strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='wi-input-container datetimerange compiled$class'>
            <label for='$idFrom' class='wi-label'>$label</label>
            <input type='text' id='$idFrom' class='wi-input wi-datetimerange-from' name='$name-from' placeholder='gg/mm/aaaa h:m' value='$valueFrom' data-wi-check='true' $attribute>
            <span class='wi-input-text'>-</span>
            <input type='text' id='$idTo' class='wi-input wi-datetimerange-to' name='$name-to' placeholder='gg/mm/aaaa h:m' value='$valueTo' data-wi-check='true' $attribute>
            $alert
        </div>
        <script>
            $(function(){

                $('#$idFrom, #$idTo').datetimepicker({
                    showAnim: 'slideDown',
                    yearRange: '1900:3000',
                    controlType: 'select',
	                oneLine: true,
                    $max
                    $min
                    showOn: 'focus',
                    dateFormat:'dd/mm/yy',
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
                        document.getElementById('$idFrom').parentElement.classList.add('selector-show');
                        customDateTimeRange(document.getElementById('$idFrom').parentElement);
                    },
                    onClose: function () {
                        document.getElementById('$idFrom').parentElement.classList.remove('selector-show');
                        customDateTimeRange(document.getElementById('$idFrom').parentElement);
                    }
                });

                $('#$idFrom, #$idTo').datetimepicker().on('change', function () {

                    check();
                    
                    var inputFrom = document.getElementById('$idFrom');
                    var inputTo = document.getElementById('$idTo');
                    var container = inputFrom.parentElement;
                    var spanAlert = container.querySelector('.alert-error');

                    var dateFrom = inputFrom.value;
                    var dateTo = inputFrom.value;

                    if (dateFrom != '' || dateTo != '') {

                        if (moment(dateFrom, 'DD/MM/YYYY HH:mm', true).isValid() || moment(dateTo, 'DD/MM/YYYY HH:mm', true).isValid()) {

                            var dateMillisecondsFrom = moment(dateFrom, 'DD/MM/YYYY HH:mm');
                            var dateMillisecondsTo = moment(dateTo, 'DD/MM/YYYY HH:mm');

                            inputFrom.setCustomValidity('');
                            inputTo.setCustomValidity('');
                            container.classList.remove('input-error');
                            spanAlert.innerHTML = ''; 

                            if ($checkMin) {
                                var min = moment('$dateMin', 'DD/MM/YYYY HH:mm').milliseconds();
                                if (dateMillisecondsFrom < min || dateMillisecondsTo < min) {
                                    inputFrom.setCustomValidity('Invalid date');
                                    inputTo.setCustomValidity('Invalid date');
                                    container.classList.add('input-error');
                                    spanAlert.innerHTML = \"<i class='bi bi-exclamation-triangle'></i> La ".strtolower(str_replace('*', '', $label))." deve essere minore del $dateMax\"; 
                                }
                            }

                            if ($checkMax) {
                                var max = moment('$dateMax', 'DD/MM/YYYY HH:mm');
                                if (dateMillisecondsFrom < max || dateMillisecondsTo < max) {
                                    inputFrom.setCustomValidity('Invalid date');
                                    inputTo.setCustomValidity('Invalid date');
                                    container.classList.add('input-error');
                                    spanAlert.innerHTML = \"<i class='bi bi-exclamation-triangle'></i> La ".strtolower(str_replace('*', '', $label))." deve essere maggiore del $dateMax\"; 
                                }
                            }

                        } else {

                            inputFrom.setCustomValidity('Invalid date');
                            inputTo.setCustomValidity('Invalid date');
                            container.classList.add('input-error');
                            spanAlert.innerHTML = \"<i class='bi bi-exclamation-triangle'></i> La ".strtolower(str_replace('*', '', $label))." deve essere formato gg/mm/aaaa hh:mm\"; 

                        }
                    }

                });

            });
        </script>";

    }

    function textList($label, $name, $options, $value = null, $attribute = '') {

        $id = strtolower(code(10, 'letters', 'input_'));

        $inputValue = "";
        $optionHTML = "";
        $listValues = [];

        foreach ($options as $vl => $nm) {

            $code = strtolower(code(10, 'letters', 'value_'));

            if ($value != null) {
                if ($vl == $value) {
                    $inputValue = $nm;
                    $checked = "checked";
                }else{
                    $checked = "";
                }
            }else{
                $search = "";
                $checked = "";
            }

            array_push($listValues, $nm);

            $optionHTML .= "
            <div class='wi-input-list-value $checked' data-wi-list-value='true'>
                <input id='$code' data-wi-keyword='$nm $vl' data-wi-input='$id' data-wi-name='$nm' type='radio' name='$name' value='$vl' $checked>
                $nm
            </div>
            ";

        }

        $class = "";

        if (!empty($error)) {
            $class .= " input-error";
            $alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> $error</span>";
        } else {
            $alert = "<span class='alert-error'></span>";
        }

        if (!empty($value)) { $class .= " compiled"; }
        if (strpos($attribute, "required") !== false) { $label .= "*"; }

        $listValues = implode("|",$listValues);
        $listValues = str_replace("'", "",$listValues);

        return "
        <div class='wi-input-container text-list$class'>
            <label for='$id' class='wi-label'>$label</label>
            <input type='text' id='$id' class='wi-input $name-value' value='$inputValue' data-wi-label='true' data-wi-list-input='true' data-wi-list-array='$listValues' onfocus $attribute>
            $alert
            <div id='list_$id' class='wi-input-list no-scrollbar'>
                $optionHTML
            </div>
        </div>
        ";

    }

    function countryList($continent, $label, $name, $value = null, $attribute = '') {

        $country = geoCountry($continent);
        return textList($label, $name, $country, $value, $attribute);

    }

    function provinceList($country, $label, $name, $value = null, $attribute = '') {

        $province = geoProvince($country);
        return textList($label, $name, $province, $value, $attribute);

    }

    function select($label, $name, $option, $value = null, $attribute = '') {
        
        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "";

        if (!empty($error)) {
            $class .= " input-error";
            $alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> $error</span>";
        } else {
            $alert = "<span class='alert-error'></span>";
        }

        if (strpos($attribute, "required") !== false) { $label .= "*"; }

        $selected = "";
        $optionHTML = "";
        $i = 1;
        
        foreach ($option as $vl => $nm) {
            
            $selected = "";

            if ($value != null) {
                if ($value == $vl) {
                    $selected = "selected";
                }
            }else{
                if ($i == 1) {
                    if ($value != '') {
                        $selected = "selected";
                    }
                }
            }

            $optionHTML .= "<option value='$vl' $selected>$nm</option>";
            $i++;
            
        }

        return "
        <div class='wi-input-container select compiled$class' data-wi-select='true'>
            <label for='$id' class='wi-label'>$label</label>
            <select id='$id' name='$name' class='wi-input d-none' data-wi-check='true' data-wi-label='true' $attribute>
                $optionHTML
            </select>
            $alert
        </div>
        ";

    }

    function checkbox($label, $name, $option, $type = 'checkbox', $value = null) {

        if ($type == 'checkbox') { $name .= "[]"; }

        $optionHTML = "";
        $i = 1;
        foreach ($option as $vl => $nm) {

            $checkboxLabel = "";
            $attribute = "";

            if ($value != null) {
                if ($type == 'checkbox') {
                    if (in_array($vl, $value)) {
                        $attribute = " checked";
                    }
                }else{
                    if ($value == $vl) {
                        $attribute = " checked";
                    }
                }
            }

            if (is_array($nm)) {
                $checkboxLabel = $nm['label'];
                $attribute = $nm['attribute'];
            } else {
                $checkboxLabel = $nm;
            }

            $id = strtolower(code(10, 'letters', 'checkbox_'));

            if (strpos($attribute, "required") !== false) { $checkboxLabel .= "*"; }

            $optionHTML .= "
            <div class='wi-checkbox-container'>
                <input type='$type' id='$id' class='wi-checkbox' name='$name' value='$vl' data-wi-check='true'$attribute>
                <div class='wi-checkbox-icon'>
                    <i class='bi bi-check-lg'></i>
                </div>
                <label for='$id' class='wi-checkbox-label'>$checkboxLabel</label>
            </div>";

            $i++;
        }

        if (!empty($label)) {
            $label = "<div class='wi-label'>
                $label
            </div>";
        }

        return "
        <div class='wi-input-container checkbox compiled'>
            $label
            <div class='wi-checkbox-list'>
                $optionHTML 
            </div>
        </div>
        ";

    }

    function submit($label, $name, $class = 'btn-success', $onclick = null) {

        $id = strtolower(code(10, 'letters', 'button_'));

        if ($onclick == null) {
            $action = "type='submit'";
        }else{
            $action = "type='button' onclick=\"$onclick\"";
        }

        return "<button $action id='$id' class='btn $class wi-submit' name='$name' disabled>$label</button>";

    }

?>