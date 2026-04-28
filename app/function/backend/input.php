<?php

    use Wonder\App\Support\CssFontFamily;

    function backendInputEscape(mixed $value): string {

        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

    }

    function backendPageTableSchema(): array {

        global $NAME;
        global $TABLE;

        if (!isset($NAME->table)) {
            return [];
        }

        $tableName = strtoupper((string) $NAME->table);
        $tableSchema = isset($TABLE->$tableName) ? (array) $TABLE->$tableName : [];

        $resourceSchemaName = '';

        if (isset($NAME->schema) && is_string($NAME->schema)) {
            $resourceSchemaName = strtolower(trim($NAME->schema));
        }

        $resourceSchema = $resourceSchemaName !== ''
            ? (\Wonder\App\Table::$list[$resourceSchemaName] ?? [])
            : [];

        $modelSchema = \Wonder\App\Table::$list[strtolower((string) $NAME->table)] ?? [];

        return array_replace_recursive(
            is_array($tableSchema) ? $tableSchema : [],
            is_array($modelSchema) ? $modelSchema : [],
            is_array($resourceSchema) ? $resourceSchema : []
        );

    }

    function password($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div>
            <div class='form-floating'>
                <input type='password' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
                <label for='$id'>$label</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";
        
    }

    function email($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }
        $value = backendInputEscape($value);
        $label = backendInputEscape($label);

        return "
        <div>
            <div class='form-floating'>
                <input type='email' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
                <label for='$id'>$label</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function phone($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }
        $value = backendInputEscape($value);
        $label = backendInputEscape($label);

        return "
        <div>
            <div class='form-floating'>
                <input type='tel' inputmode='tel' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-phone='true' data-wi-check='true' $attribute>
                <label for='$id'>$label</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function text($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($name === 'font_family') { $value = CssFontFamily::normalize($value); }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }
        $value = backendInputEscape($value);
        $label = backendInputEscape($label);

        return "
        <div>
            <div class='form-floating'>
                <input type='text' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
                <label for='$id'>$label</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function textGenerator($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($name === 'font_family') { $value = CssFontFamily::normalize($value); }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div>
            <div class='form-floating'>
                <input type='text' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
                <label for='$id'>$label</label>
                <div class='btn btn-sm btn-dark text-light position-absolute top-50 end-0 me-2 translate-middle-y' onclick=\"generateCode('#$id')\">
                    GENERA
                </div>
                <div class='invalid-feedback'> </div>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function textDate($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = date('Y-m-d', strtotime($VALUES[$name])); }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div>
            <div class='form-floating'>
                <input type='date' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
                <label for='$id'>$label</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function textDatetime($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = date('Y-m-d H:i', strtotime($VALUES[$name])); }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div>
            <div class='form-floating'>
                <input type='datetime-local' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
                <label for='$id'>$label</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }
    
    function dateInput($label, $name, $dateMin = null, $dateMax = null, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = date('d/m/Y', strtotime($VALUES[$name])); }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }

        $min = ($dateMin == null) ? '' : 'data-wi-min-date="'.$dateMin.'"';
        $max = ($dateMax == null) ? '' : 'data-wi-max-date="'.$dateMax.'"';

        return "
        <div>
            <div class='form-floating'>
                <input type='text' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' data-wi-date='true' $min $max $attribute>
                <label for='$id'>$label</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function dateRange($label, $name, $dateMin = null, $dateMax = null, $attribute = null, $value = null) {
        
        global $VALUES;
        
        $idFrom = strtolower(code(10, 'letters', 'input_'));
        $idTo = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        $nameFrom = $name."_from";
        $nameTo = $name."_to";

        if (isset($VALUES[$nameFrom]) && isset($VALUES[$nameTo]) && !isset($value)) {
            $valueFrom = date('d/m/Y', strtotime($VALUES[$nameFrom]));
            $valueTo = date('d/m/Y', strtotime($VALUES[$nameTo]));
        } elseif (isset($value)) {
            $valueFrom = date('d/m/Y', strtotime($value[0]));
            $valueTo = date('d/m/Y', strtotime($value[1]));
        } else {
            $valueFrom = "";
            $valueTo = "";
        }

        if ($attribute != null && strpos($attribute, "required") !== false && !empty($label)) { $label .= "*"; }

        $min = ($dateMin == null) ? '' : 'data-wi-min-date="'.$dateMin.'"';
        $max = ($dateMax == null) ? '' : 'data-wi-max-date="'.$dateMax.'"';

        $label = empty($label) ?"" : "<label class='h6 form-label'>$label</label>"; 

        return "
        <div>
            $label
            <div class='input-group input-daterange mt-1' data-wi-date-range='true' $min $max>
                <span class='input-group-text'>Dal</span>
                <input id='$idFrom' type='text' class='$class' name='$nameFrom' value='$valueFrom' data-wi-check='true' readonly $attribute>
                <span class='input-group-text'>Al</span>
                <input id='$idTo' type='text' class='$class' name='$nameTo' value='$valueTo' data-wi-check='true' readonly $attribute>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function color($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }

        $color = !empty($value) ? "style='color: $value;'" : '';

        return "
        <div>
            <label class='h6 form-label' for='$id'>$label</label>
            <div class='input-group mt-1'>
                <span class='input-group-text'><i class='bi bi-circle-fill wi-show-color' $color></i></span>
                <input type='text' class='$class' id='$id' aria-describedby='$id-color' name='$name' value='$value' placeholder='$label' data-wi-check='true' data-wi-check-color='true' $attribute>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function number($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }
        $value = backendInputEscape($value);
        $label = backendInputEscape($label);

        return "
        <div>
            <div class='form-floating'>
                <input type='text' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-number='true' data-wi-check='true' $attribute>
                <label for='$id'>$label</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function price($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }
        $value = backendInputEscape($value);
        $label = backendInputEscape($label);

        return "
        <div>
            <div class='form-floating'>
                <input type='text' class='$class' id='$id' name='$name' value='$value' data-wi-check='true' data-wi-price='true' placeholder='$label' $attribute>
                <label for='$id'>$label</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function percentige($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div>
            <div class='form-floating'>
                <input type='text' class='$class' id='$id' name='$name' value='$value' data-wi-check='true' data-wi-percentige='true' placeholder='$label' $attribute>
                <label for='$id'>$label</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function url($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }
        $value = backendInputEscape($value);
        $label = backendInputEscape($label);

        return "
        <div>
            <div class='form-floating'>
                <input type='url' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
                <label for='$id'>$label</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function textarea($label, $name, $attribute = null, $version = null, $value = null) {

        global $VALUES;
        global $NAME;
        global $TABLE;

        if (isset($NAME->table)) {

            $PAGE_TABLE = backendPageTableSchema();
    
            $MAX_LENGHT = isset($PAGE_TABLE[$name]['sql']['lenght']) ? $PAGE_TABLE[$name]['sql']['lenght'] : 0;

        } else {

            $MAX_LENGHT = 0;

        }

        $id = strtolower(code(10, 'letters', 'input_'));

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }

        if ($version != null) {
            
            $valueEncoded =  empty($value) ? "" : base64_encode($value);

            return "
            <div>
                <div class='form-floating'>
                    <h6 class='mb-1'>$label</h6>
                    <textarea id='$id' class='d-none' name='$name' data-wi-value='$valueEncoded' data-wi-check='true' data-wi-textarea='$version' data-wi-folder='$NAME->folder' $attribute>$value</textarea>
                </div>
                <div class='invalid-feedback'> </div>
            </div>";

        } else {

            if ($MAX_LENGHT > 0) {
                if ($value == null) { $c = 0; } else { $c = strlen($value); }
                $MAX = "<div class='position-absolute bottom-0 end-0 m-2 me-3'><span class='wi-counter'>$c</span> / <span class='wi-max-lenght'>$MAX_LENGHT</span></div>";
            } else {
                $MAX = "";
            }

            return "
            <div>
                <div class='form-floating'>
                    $MAX
                    <textarea class='form-control' placeholder='$label' id='$id' style='height: 100px' name='$name' data-wi-check='true' data-wi-counter='true' $attribute>$value</textarea>
                    <label for='$id'>$label</label>
                </div>
                <div class='invalid-feedback'> </div>
            </div>";
            
        }
        
    }

    function selectSearch($label, $name, $option, $multiple = false, $version = null, $attribute = null, $value = null) {

        $attribute .= ' data-wi-select-search="true"';
        $attribute .= $multiple ? ' data-wi-select-search-multiple="true" multiple' : '';

        return select($label, $name, $option, $version, $attribute, $value);

    }

    function select($label, $name, $option, $version = null, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }
        $multiple = !is_null($attribute) && strpos($attribute, "multiple") ? true : false;

        $optionHTML = "";

        $i = 1;
        
        foreach ($option as $vl => $nm) {

            $att = "";
            $dataFilter = "";

            if (is_array($nm)) {
    
                $filter = isset($nm['filter']) ? $nm['filter'] : [];
                $nm = $nm['name'];

                foreach ($filter as $key => $v) { $dataFilter .= "data-$key='$v' "; }

            }

            if ($value != null) { 
                if (is_array($value)) {
                    $att = in_array($vl, $value) ? "selected" : ""; 
                } else {
                    $att = ($vl == $value) ? "selected" : ""; 
                }
            }

            $optionHTML .= "<option value='$vl' $att $dataFilter>$nm</option>";

            $i++;
            
        }

        $name .= $multiple ? "[]" : "";
        $inputHidden = $multiple ? "<input type='hidden' name='$name'>" : "";

        if ($version == 'old') {

            return "
            <div>
                <div id='container-$id' class='w-100 wi-container-select'>
                    $inputHidden
                    <label for='$id' class='h6 form-label'>$label</label>
                    <select id='$id' name='$name' class='form-select mt-1' data-wi-check='true' $attribute data-wi-attribute='$attribute'>
                        $optionHTML
                    </select>
                </div>
                <div class='invalid-feedback'> </div>
            </div>";

        } else {

            return "
            <div>
                <div class='form-floating'>
                    $inputHidden
                    <select id='$id' name='$name' class='form-select' data-wi-check='true' $attribute data-wi-attribute='$attribute'>
                        $optionHTML
                    </select>
                    <label for='$id'>$label</label>
                </div>
                <div class='invalid-feedback'> </div>
            </div>";

        }

    }

    function createOption($option, $type, $name, $value, $attribute, ) {

        $RETURN = ($type == 'list') ? "<ul>" : "";

        if (is_array($option)) {

            foreach ($option as $optionValue => $optionName) {
                
                $optionAttribute = $attribute;

                $optionChild = "";
                $listAttribute = "";
    
                if (is_array($value)) {
                    $optionAttribute .= ($value != null && in_array($optionValue, $value)) ? " checked" : "";
                    $listAttribute .= ($value != null && in_array($optionValue, $value)) ? " data-jstree='{\"selected\": true }'" : "";
                } else {
                    $optionAttribute .= ($value != null && $optionValue == $value) ? " checked" : "";
                    $listAttribute .= ($value != null && $optionValue == $value) ? " data-jstree='{\"selected\": true }'" : "";
                }
    
                if (is_array($optionName)) {
    
                    $filter = isset($optionName['filter']) ? $optionName['filter'] : [];
                    $child = isset($optionName['child']) ? $optionName['child'] : [];

                    $optionName = $optionName['name'];
    
                    foreach ($filter as $key => $v) { $optionAttribute .= " data-$key='$v'"; }

                    if (!empty($child)) {

                        $optionChild  .= ($type == 'list') ? "" : "<div class='w-100 ps-3'>";
                        $optionChild  .= createOption($child, $type, $name, $value, $attribute);
                        $optionChild  .= ($type == 'list') ? "" : "</div>";

                    }
    
                }

                if ($type == 'list') {
                    
                    $RETURN .= "<li id='$optionValue' $listAttribute><input class='d-none' type='checkbox' name='$name' value='$optionValue' $optionAttribute>$optionName$optionChild</li>";

                } else {

                    $RETURN .= "<div class='w-100'>
                        <div id='$name-$optionValue' class='form-check'>
                            <input class='form-check-input' type='$type' name='$name' value='$optionValue' id='$type-$name-$optionValue' data-wi-check='true' $optionAttribute>
                            <label class='form-check-label wi-check-label user-select-none' for='$type-$name-$optionValue'>$optionName</label>
                        </div>
                        $optionChild
                    </div>";

                }
    
            }

        }

        $RETURN .= ($type == 'list') ? "</ul>" : "";

        return $RETURN;

    }

    function check($label, $name, $option, $attribute = null, $type = 'checkbox', $searchBar = false, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        if (isset($VALUES[$name]) && $value == null) {
            $value = ($type == 'checkbox') ? json_decode($VALUES[$name], true) : $VALUES[$name];
        }

        if (!empty($attribute) && strpos($attribute, "required") !== false) { 

            $label .= "*"; 
            $attribute = str_replace('required', '', $attribute);
            $required = "wi-$type-required";

        } else {

            $required = "";
            
        }

        $optionHTML = "";
        $inputHidden = "";
        
        $bar = ($searchBar) ? "<input type='text' class='form-control card-header m-0 border-0 border-bottom bg-body' placeholder='Cerca...' aria-label='Cerca...' data-wi-search='true' >" : "";

        if ($type == 'checkbox') {
            $name .= '[]';
            $inputHidden = "<input type='hidden' name='$name'>";
        }

        $optionHTML = createOption($option, $type, $name, $value, $attribute);

        return "
        <div id='container-$id' class='w-100 wi-container-$type $required'>
            <h6>$label</h6>
            $inputHidden
            <div class='card border mt-1'>
                $bar
                <div class='card-body overflow-scroll p-2' style='height: 120px;'>
                    $optionHTML
                </div>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    } 

    function checkTree($label, $name, $option, $attribute = null, $type = 'checkbox', $searchBar = false, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        if (isset($VALUES[$name]) && $value == null) {
            $value = ($type == 'checkbox') ? json_decode($VALUES[$name], true) : $VALUES[$name];
        }

        if (!empty($attribute) && strpos($attribute, "required") !== false) { 

            $label .= "*"; 
            $attribute = str_replace('required', '', $attribute);
            $required = "wi-$type-required";

        } else {

            $required = "";
            
        }
        
        $inputHidden = "";
        $bar = ($searchBar) ? "<input type='text' class='form-control card-header m-0 border-0 border-bottom bg-body' placeholder='Cerca...' aria-label='Cerca...' data-wi-search='true' >" : "";

        if ($type == 'checkbox') {

            $name .= '[]';
            $inputHidden = "<input type='hidden' name='$name'>";

        }

        $optionHTML = createOption($option, 'list', $name, $value, $attribute);

        return "
        <div id='container-$id' class='w-100 wi-container-$type $required'>
            <h6>$label</h6>
            <div class='card border mt-1'>
                $bar
                $inputHidden
                <div class='card-body overflow-scroll p-2' style='max-height: 300px;' data-wi-tree='$type'>
                    $optionHTML
                </div>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    } 

    function dynamicCheck($label, $name, $url, $attribute = null, $type = 'checkbox', $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        if (isset($VALUES[$name]) && $value == null) {
            $value = ($type == 'checkbox') ? $VALUES[$name] : json_encode([$VALUES[$name]]);
        }

        if (!empty($attribute) && strpos($attribute, "required") !== false) { 

            $label .= "*"; 
            $attribute = str_replace('required', '', $attribute);
            $required = "wi-$type-required";

        } else {

            $required = "";
            
        }
        
        $inputHidden = "";
        
        if ($type == 'checkbox') {

            $name .= '[]';
            $inputHidden = "<input type='hidden' name='$name'>";

        }

        return "
        <div id='container-$id' class='w-100 wi-container-$type $required'>
            <h6>$label</h6>
            $inputHidden
            <div class='card border mt-1'>
                <input type='text' class='form-control card-header m-0 border-0 border-bottom bg-body' placeholder='Cerca...' aria-label='Cerca...' data-wi-name='$name' data-wi-value='$value' data-wi-search='true' data-wi-search-$type='true' data-wi-search-url='$url' data-wi-attribute='$attribute'>
                <div class='card-body overflow-scroll p-2' style='height: 120px;'>
                </div>
                <div class='card-footer border-top text-body-secondary'>
                    Cerca risultati
                </div>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    } 

    function checkbox($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        if (isset($VALUES[$name]) && $value == null) {
            $value = $VALUES[$name];
        }

        if (!empty($attribute) && strpos($attribute, "required") !== false) { 

            $label .= "*"; 
            $attribute = str_replace('required', '', $attribute);
            $required = "wi-checkbox-required";

        } else {

            $required = "";
            
        }
        
        $checked = ($value == 'true') ? 'checked' : '';

        return "
        <div id='container-$id' class='w-100 wi-container-checkbox $required'>
            <input type='hidden' name='$name'>
            <div class='input-group'>
                <span class='input-group-text'><input class='form-check-input mt-0' type='checkbox' name='$name' id='$id' $attribute $checked></span>
                <label for='$id' class='form-control user-select-none'>$label</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function checkBoolean($label, $name, $attribute = null, $option = [ '', 'true', 'false'],  $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        if (isset($VALUES[$name]) && $value == null) {
            $value = $VALUES[$name];
        }

        if (!empty($attribute) && strpos($attribute, "required") !== false) { 

            $label .= "*"; 
            $attribute = str_replace('required', '', $attribute);
            $required = "wi-checkbox-required";

        } else {

            $required = "";
            
        }

        # Check value
            $valueNull = $option[0];
            $valueTrue = $option[1];
            $valueFalse = $option[2];

            $idTrue = $name.'-'.$valueTrue;
            $idFalse = $name.'-'.$valueFalse;

            $checkedTrue = '';
            $checkedFalse = '';

            $classLabelTrue = '';
            $classLabelFalse = '';

            if ($valueTrue === $value) {
                $checkedTrue = 'checked';
                $classLabelTrue = 'btn-primary';
            } elseif ($valueFalse === $value) {
                $checkedFalse = 'checked';
                $classLabelFalse = 'btn-primary';
            }

        return "
        <div id='container-$id' class='w-100 wi-container-checkbox $required' data-wi-check-boolean='true'>
            <input type='hidden' class='wi-none' name='$name' value='$valueNull'>
            <input type='checkbox' class='btn-check wi-true' name='$name' value='$valueTrue' id='$idTrue' data-wi-check='true' $checkedTrue $attribute>
            <input type='checkbox' class='btn-check wi-false' name='$name' value='$valueFalse' id='$idFalse' data-wi-check='true' $checkedFalse $attribute>
            <div class='input-group'>
                <span class='form-control'>$label</span>
                <label class='btn border $classLabelTrue' for='$idTrue'>Si</label>
                <label class='btn border $classLabelFalse' for='$idFalse'>No</label>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function inputFile($label, $name, $file = 'image', $attribute = null, $value = null) {

        global $PATH;
        global $NAME;
        global $TABLE;
        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }

        $PAGE_TABLE = backendPageTableSchema();

        $TB = (array) (($PAGE_TABLE[$name]['input'] ?? []));
        $maxFile = $TB['format']['max_file'] ?? 1;
        $maxSize = $TB['format']['max_size'] ?? 1;

        if ($file == "image") {
            $ACCEPT = "image/png, image/jpeg";
            $EXTENSIONS_ACCEPT = ".png - .jpg - .jpeg";
        } elseif ($file == "pdf") {
            $ACCEPT = "application/pdf";
            $EXTENSIONS_ACCEPT = ".pdf";
        } elseif ($file == "png") {
            $ACCEPT = "image/png";
            $EXTENSIONS_ACCEPT = ".png";
        } elseif ($file == "ico") {
            $ACCEPT = "image/ico, image/x-icon";
            $EXTENSIONS_ACCEPT = ".ico";
        } elseif ($file == "video") {
            $ACCEPT = "video/mp4";
            $EXTENSIONS_ACCEPT = ".mp4";
        } elseif ($file == "jpg") {
            $ACCEPT = "image/jpeg";
            $EXTENSIONS_ACCEPT = ".jpg - .jpeg";
        } elseif ($file == "font") {
            $ACCEPT = "font/ttf";
            $EXTENSIONS_ACCEPT = ".ttf";
        } else {
            $ACCEPT = "";
            $EXTENSIONS_ACCEPT = "";
        }

        $OLD_FILES = "";
        $i = 0;

        if (!empty($value) && isset($VALUES['id'])) {

            $OLD_FILES = "<div class='row g-3'>";

            $ARRAY = json_decode($value, true);
            $N_IMAGES = count($ARRAY);
            $rowId = $VALUES['id'];

            if (isset($TB['format']['resize'])) {

                $imageResize = $TB['format']['resize'];

                if (isset($imageResize['width'])) {

                    $imageSize = $imageResize['width'].'x'.$imageResize['height'].'-';
                    $sizeBefore = true;

                } else if (isset($imageResize[0]['width'])) {
                    $s = 10000000;

                    foreach ($imageResize as $key => $size) {
                        if ($size['width'] < $s) {
                            $s = $size['width'];
                            $imageSize = $size['width'].'x'.$size['height'].'-';
                        }
                    }

                    $sizeBefore = true;

                } else {

                    $firstResize = is_array($imageResize) ? reset($imageResize) : $imageResize;
                    $imageSize = ($firstResize !== false && $firstResize !== null && $firstResize !== '')
                        ? '-'.$firstResize
                        : '';
                    $sizeBefore = false;

                }

            } else {

                $imageSize = "";

            }
            
            foreach ($ARRAY as $fileId => $fileName) {

                $n = $i + 1;

                $cardClass = "";

                $dir = isset($TB['format']['dir']) ? $TB['format']['dir'] : '/'; 

                if (substr($dir, -1) != '/') {
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $linkDowload = $PATH->upload.'/'.$NAME->folder.$dir.'.'.$extension;
                    $link = $PATH->upload.'/'.$NAME->folder.$dir.'.'.$extension;
                } else {
                    $linkDowload = $PATH->upload.'/'.$NAME->folder.$dir.$fileName;
                    if ($sizeBefore) {
                        $link = $PATH->upload.'/'.$NAME->folder.$dir.$imageSize.$fileName;
                    } else {
                        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                        $name = pathinfo($fileName, PATHINFO_FILENAME);
                        $link = $PATH->upload.'/'.$NAME->folder.$dir.$name.$imageSize.'.'.$extension;
                    }
                }

                if ($file == "image" || $file == "png" || $file == "ico" || $file == "jpg") {
                    $image = "<img class='w-100 object-fit-contain' src='$link' height='200' lazyload>";
                } else {
                    $image = "";
                }

                if ($N_IMAGES == 1) {
                    $ARROW_UP = "";
                    $ARROW_DOWN = "";
                } else {
                    $ARROW_UP = "<button type='button' class='btn btn-light btn-sm wi-arrow-up' onclick=\"moveFile('#container-$id', '#card-file-$fileId', 'up')\"><i class='bi bi-chevron-left'></i></button>";
                    $ARROW_DOWN = "<button type='button' class='btn btn-light btn-sm wi-arrow-down' onclick=\"moveFile('#container-$id', '#card-file-$fileId', 'down')\"><i class='bi bi-chevron-right'></i></i></button>";    
                }

                if ($n == 1) {
                    $cardClass .= "wi-first-file";
                } else if ($n == $N_IMAGES) {
                    $cardClass .= "wi-last-file";
                }

                $OLD_FILES .=  "
                <div id='card-file-$fileId' class='wi-card-file $cardClass col-4 order-$n' data-wi-order='$n' data-wi-n-file='$N_IMAGES' data-wi-db-table='$NAME->table' data-wi-db-column='$name' data-wi-db-row='$rowId' data-wi-folder='$NAME->folder' data-wi-file-id='$fileId' data-wi-file-name='$fileName'>
                    <div class='card border overflow-hidden'>
                        $image
                        <div class='card-body'>
                            <p class='card-title'>$fileName</p>
                            <div class='d-flex w-100 gap-2'>
                                $ARROW_UP
                                $ARROW_DOWN
                                <a href='$linkDowload' download class='btn btn-secondary btn-sm ms-auto'><i class='bi bi-download'></i></a>
                                <button type='button' class='btn btn-danger btn-sm' onclick=\"deleteFile('#container-$id', '#card-file-$fileId')\"><i class='bi bi-trash3'></i></button>
                            </div>
                        </div>
                    </div>
                </div>";

                $i++;

            }

            $OLD_FILES .= "</div>";

        }
        
        $x = $name.'[]';

        if ($i >=  $maxFile) {
            $attribute = "disabled";
            $multiple = "";
        } else {
            if ($maxFile == 1) {
                $multiple = "";
            } else {
                $multiple = "multiple";
            }
        }

        return "
        <div id='container-$id' class='w-100 wi-container-files'>
            <h6>$label</h6>
            <div class='w-100 mt-1'>
                <input class='form-control' style='width: 100%;' id='$id' type='file' accept='$ACCEPT' name='$x' data-wi-max-file='$maxFile' data-wi-max-size='$maxSize' data-wi-check='true' $multiple $attribute>
                <div class='invalid-feedback'> </div>
            </div>
            <div class='w-100 mt-1'>
                <small>
                    <ul>
                        <li>File ammessi: <b>$EXTENSIONS_ACCEPT</b></li>
                        <li>File massimi: <b>$maxFile</b></li>
                        <li>Peso massimo: <b>{$maxSize}Mb</b></li>
                    </ul> 
                </small>
            </div>
            $OLD_FILES
        </div>";

    }
    
    function inputFileDragDrop($label, $name, $type = 'classic', $file = 'image', $attribute = null, $value = null) {

        global $PATH;
        global $NAME;
        global $TABLE;
        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        $class = "w-100";

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if (!empty($attribute) && strpos($attribute, "required") !== false) { $label .= "*"; }

        $PAGE_TABLE = backendPageTableSchema();

        $columnName = (isset($NAME->column) && !is_array($NAME->column)) ? $NAME->column : $name;

        $TB = (array) (($PAGE_TABLE[$columnName]['input'] ?? []));
        $maxFile = $TB['format']['max_file'] ?? 1;
        $maxSize = $TB['format']['max_size'] ?? 1;

        if ($file == "image") {
            $ACCEPT = "image/png, image/jpeg";
            $ACCEPT_LABEL = "la tua immagine";
        } elseif ($file == "pdf") {
            $ACCEPT = "application/pdf";
            $ACCEPT_LABEL = "il tuo PDF";
        } elseif ($file == "png") {
            $ACCEPT = "image/png";
            $ACCEPT_LABEL = "la tua immagine";
        } elseif ($file == "ico") {
            $ACCEPT = "image/ico, image/x-icon";
            $ACCEPT_LABEL = "la tua immagine";
        } elseif ($file == "video") {
            $ACCEPT = "video/mp4";
            $ACCEPT_LABEL = "il tuo video";
        } elseif ($file == "jpg") {
            $ACCEPT = "image/jpeg";
            $ACCEPT_LABEL = "la tua immagine";
        } elseif ($file == "font") {
            $ACCEPT = "font/ttf";
            $ACCEPT_LABEL = "il tuo font";
        } else {
            $ACCEPT = "";
            $ACCEPT_LABEL = "il tuo file";
        }

        if (isset($TB['format']['resize'])) {

            $imageResize = $TB['format']['resize'];

            if (isset($imageResize['width'])) {

                $imageSize = $imageResize['width'].'x'.$imageResize['height'].'-';
                $sizeBefore = true;

            } else if (isset($imageResize[0]['width'])) {
                $s = 10000000;

                foreach ($imageResize as $key => $size) {
                    if ($size['width'] < $s) {
                        $s = $size['width'];
                        $imageSize = $size['width'].'x'.$size['height'].'-';
                    }
                }

                $sizeBefore = true;

            } else {

                $firstResize = is_array($imageResize) ? reset($imageResize) : $imageResize;
                $imageSize = ($firstResize !== false && $firstResize !== null)
                    ? (string) $firstResize
                    : '';
                $sizeBefore = false;

            }

        } else {

            $sizeBefore = false;
            $imageSize = "";

        }

        $x = $name.'[]';

        $multiple = ($maxFile > 1) ? "multiple" : "";
        $class = ($maxFile > 1) ? " filepond--multiple" : "";

        $legacyName = null;

        if (isset($NAME) && is_object($NAME)) {
            $legacyName = $NAME;
        } else {
            $legacyName = \Wonder\App\LegacyGlobals::get('NAME');
        }

        $folder = is_object($legacyName) ? trim((string) ($legacyName->folder ?? ''), '/') : '';

        $dir = rtrim((string) ($PATH->upload ?? ''), '/');
        $dir .= $folder !== '' ? '/'.$folder : '';
        $dir .= $TB['format']['dir'] ?? '/'; 

        if (substr($dir, -1) != '/') {
                                
            $NEW_NAME = explode('/', $dir);
            $lastKey = array_key_last($NEW_NAME);

            $NEW_NAME = $NEW_NAME[$lastKey];
            $dir = str_replace($NEW_NAME,'', $dir);

        }
        
        if (is_array($value)) { $value = ""; }

        if (!empty($label)) {
            $label = "<h6>$label</h6>";
            $class .= " mt-1";
        }
        
        return "
        <div id='container-$id' class='w-100'>
            $label
            <div class='$class'>
                <input id='$id' type='file' accept='$ACCEPT' name='$x' data-max-file-size='{$maxSize}MB' data-min-size-image='$imageSize' data-size-before='$sizeBefore' data-max-files='$maxFile' data-wi-dir='$dir' data-wi-value='$value' data-wi-uploader='$type' data-wi-uploader-label='$ACCEPT_LABEL' data-wi-check='true' $multiple $attribute>
            </div>
            <div class='invalid-feedback'> </div>
        </div>";

    }

    function googleAddress($label, $name, $callback = null, $attributes = null, $value = null) 
    {

        return text(
            $label,
            $name,
            "$attributes data-wi-search-place=\"true\" data-wi-callback=\"$callback\"",
            $value 
        );

    }

    function countryList($continent, $label, $name, $attribute = null, $value = null) {

        $options = countries();
        return check($label, $name, $options, $attribute, 'radio', true, $value);

    }

    function provinceList($country, $label, $name, $value = null, $attribute = null) {

        $options = (!empty($country)) ? states($country) : [];
        return check($label, $name, $options, $attribute, 'radio', true, $value);

    }

    function inputCountry($label, $name, $value = null, $nameState = null, $attribute = '') {

        $options = countries();

        if (!empty($nameState)) {
            $attribute .= ' data-wi-input-country="true" data-wi-input-state="'.$nameState.'"';
        }

        return selectSearch(
            $label, 
            $name, 
            $options, 
            false,
            null, 
            $attribute,
            $value
        );

    }

    function inputStates($label, $name, $country = null, $value = null, $attribute = '') {

        $options = (!empty($country)) ? states($country) : [];

        return selectSearch(
            $label, 
            $name, 
            $options, 
            false,
            null,
            $attribute.' data-wi-input-state="true" data-wi-list-states="'.$country.'" data-wi-input-attribute="'.$attribute.'"',
            $value
        );

    }

    function inputPhonePrefix($label, $name, $value = null, $attribute = '') {

        $options = phonePrefix();

        return selectSearch(
            $label, 
            $name, 
            $options, 
            false,
            null,
            $attribute,
            $value
        );

    }

    function inputRepeater($label, $name, $attribute = null, $value = null, array $config = []) {

        $id = strtolower(code(10, 'letters', 'input_'));
        $rowId = $id.'-rows';
        $templateId = $id.'-template';
        $addLabel = trim((string) ($config['add_label'] ?? 'Aggiungi linea'));
        $addButtonClass = trim((string) ($config['add_button_class'] ?? 'btn btn-secondary'));
        $deleteModalTitle = trim((string) ($config['delete_modal_title'] ?? 'Conferma eliminazione'));
        $deleteModalText = trim((string) ($config['delete_modal_text'] ?? "Confermi l'eliminazione della riga?"));
        $deleteModalCancelLabel = trim((string) ($config['delete_modal_cancel_label'] ?? 'Annulla'));
        $deleteModalConfirmLabel = trim((string) ($config['delete_modal_confirm_label'] ?? 'Elimina'));
        $deleteModalConfirmClass = trim((string) ($config['delete_modal_confirm_class'] ?? 'btn btn-danger'));
        $columns = is_array($config['columns'] ?? null) ? $config['columns'] : [];
        $nested = (bool) ($config['nested'] ?? false);
        $sortable = (bool) ($config['sortable'] ?? false);

        if ($columns === []) {
            $columns = [[
                'name' => $name,
                'label' => '',
                'helper' => 'text',
                'col' => 11,
                'attribute' => $attribute,
                'options' => [],
                'search_bar' => false,
                'version' => null,
            ]];
        }

        $rows = [];

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);

            if ($nested && $isAssoc) {
                $rows = $value;
            } elseif (!$isAssoc) {
                $rowIndex = 1;

                foreach ($value as $item) {
                    $rowKey = 'row_'.$rowIndex;

                    if (is_array($item)) {
                        $rows[$rowKey] = $item;
                    } elseif (count($columns) === 1) {
                        $firstColumn = $columns[0] ?? null;
                        $firstColumnName = $firstColumn instanceof \Wonder\App\ResourceSchema\FormField
                            ? (string) $firstColumn->name
                            : (string) (($firstColumn['name'] ?? $name));

                        $rows[$rowKey] = [
                            $firstColumnName => $item,
                        ];
                    }

                    $rowIndex++;
                }
            }
        }

        if ($rows === []) {
            $rows['row_1'] = [];
        }

        $renderField = static function (mixed $column, array $rowValue = [], string $rowKey = '__ROW_KEY__') use ($name, $attribute, $nested): string {
            if ($column instanceof \Wonder\App\ResourceSchema\FormField) {
                $field = clone $column;
                $columnName = trim((string) $field->name);
                $fieldValue = $rowValue[$columnName] ?? $field->get('value');
                $inputName = $nested
                    ? "{$name}[{$rowKey}][{$columnName}]"
                    : $columnName.'[]';
                $field->inputName($inputName)->value($fieldValue);

                return $field->render();
            }

            $column = is_array($column) ? $column : [];
            $columnName = trim((string) ($column['name'] ?? $name));
            $helper = trim((string) ($column['helper'] ?? 'text'));
            $columnLabel = (string) ($column['label'] ?? '');
            $columnAttribute = trim((string) (($column['attribute'] ?? '') ?: $attribute));
            $columnOptions = is_array($column['options'] ?? null) ? $column['options'] : [];
            $searchBar = (bool) ($column['search_bar'] ?? false);
            $version = $column['version'] ?? null;
            $fieldValue = $rowValue[$columnName] ?? ($column['value'] ?? null);
            $fieldName = $nested
                ? "{$name}[{$rowKey}][{$columnName}]"
                : $columnName.'[]';

            return match ($helper) {
                'hidden' => "<input type='hidden' name='{$fieldName}' value='".htmlspecialchars((string) ($fieldValue ?? ''), ENT_QUOTES, 'UTF-8')."' {$columnAttribute}>",
                'select' => select($columnLabel, $fieldName, $columnOptions, $version, $columnAttribute, $fieldValue),
                'selectSearch' => selectSearch($columnLabel, $fieldName, $columnOptions, false, $version, $columnAttribute, $fieldValue),
                'radio' => check($columnLabel, $fieldName, $columnOptions, $columnAttribute, 'radio', $searchBar, $fieldValue),
                'textarea' => textarea($columnLabel, $fieldName, $columnAttribute, $version, $fieldValue),
                'email' => email($columnLabel, $fieldName, $columnAttribute, $fieldValue),
                'phone', 'tel' => phone($columnLabel, $fieldName, $columnAttribute, $fieldValue),
                'number' => number($columnLabel, $fieldName, $columnAttribute, $fieldValue),
                'price' => price($columnLabel, $fieldName, $columnAttribute, $fieldValue),
                'percentige' => percentige($columnLabel, $fieldName, $columnAttribute, $fieldValue),
                default => text($columnLabel, $fieldName, $columnAttribute, $fieldValue),
            };
        };

        $renderRow = static function (array $rowValue = [], string $rowKey = '__ROW_KEY__', bool $template = false) use ($columns, $renderField, $sortable, $deleteModalTitle, $deleteModalText, $deleteModalCancelLabel, $deleteModalConfirmLabel, $deleteModalConfirmClass): string {
            $rowClass = $template ? ' d-none' : '';
            $html = "<div class=\"col-12 wi-repeater-row{$rowClass}\" data-wi-row-key=\"{$rowKey}\">";
            $html .= '<div class="card border-0 bg-light-subtle">';
            $html .= '<div class="card-body">';
            $html .= '<div class="row g-2 align-items-start">';

            foreach ($columns as $column) {
                if ($column instanceof \Wonder\App\ResourceSchema\FormField) {
                    $col = (int) (($column->columnSpan['default'] ?? null) ?: 11);
                    $isHidden = ($column->get('helper') === 'hidden');
                } else {
                    $column = is_array($column) ? $column : [];
                    $col = (int) ($column['col'] ?? 11);
                    $isHidden = (($column['helper'] ?? 'text') === 'hidden');
                }

                if ($col <= 0 || $col > 12) {
                    $col = 11;
                }

                if ($isHidden) {
                    $html .= $renderField($column, $rowValue, $rowKey);
                    continue;
                }

                $html .= "<div class=\"col-{$col}\">";
                $html .= $renderField($column, $rowValue, $rowKey);
                $html .= '</div>';
            }

            $actionColumnClass = $sortable ? 'col-3' : 'col-1';
            $html .= "<div class=\"{$actionColumnClass} d-flex align-items-stretch\">";
            $html .= '<div class="d-flex flex-row gap-2 w-100">';

            if ($sortable) {
                $html .= '<button type="button" class="btn btn-outline-secondary flex-fill wi-repeater-move-up" onclick="window.wiRepeaterMoveRowUp(this)"><i class="bi bi-chevron-up"></i></button>';
                $html .= '<button type="button" class="btn btn-outline-secondary flex-fill wi-repeater-move-down" onclick="window.wiRepeaterMoveRowDown(this)"><i class="bi bi-chevron-down"></i></button>';
            }

            $deleteAttrs = sprintf(
                ' data-wi-delete-title="%s" data-wi-delete-text="%s" data-wi-delete-cancel-label="%s" data-wi-delete-confirm-label="%s" data-wi-delete-confirm-class="%s"',
                backendInputEscape($deleteModalTitle),
                backendInputEscape($deleteModalText),
                backendInputEscape($deleteModalCancelLabel),
                backendInputEscape($deleteModalConfirmLabel),
                backendInputEscape($deleteModalConfirmClass)
            );
            $html .= '<button type="button" class="btn btn-danger flex-fill wi-repeater-delete" onclick="window.wiRepeaterRemoveRow(this)"'.$deleteAttrs.'><i class="bi bi-trash3"></i></button>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

            return $html;
        };

        $html = "<div id=\"{$id}\" class=\"w-100 wi-input-repeater\">";
        $html .= "<h6>{$label}</h6>";
        $html .= "<div id=\"{$rowId}\" class=\"row g-2\">";

        foreach ($rows as $rowKey => $row) {
            $html .= $renderRow(is_array($row) ? $row : [], (string) $rowKey, false);
        }

        $html .= '</div>';
        $html .= "<template id=\"{$templateId}\">".$renderRow([], '__ROW_KEY__', true).'</template>';
        $html .= '<div class="mt-2 d-flex justify-content-end">';
        $html .= "<button type=\"button\" class=\"{$addButtonClass}\" onclick=\"window.wiRepeaterAddRow('{$rowId}', '{$templateId}')\"><i class=\"bi bi-plus-lg\"></i> {$addLabel}</button>";
        $html .= '</div>';
        $html .= <<<'HTML'
<script>
    window.wiRepeaterAddRow = window.wiRepeaterAddRow || function (containerId, templateId) {
        const container = document.getElementById(containerId);
        const template = document.getElementById(templateId);

        if (!container || !template) {
            return;
        }

        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('.wi-repeater-row');
        const rowKey = 'row_' + Date.now() + '_' + Math.floor(Math.random() * 1000);

        if (row) {
            row.classList.remove('d-none');
            row.setAttribute('data-wi-row-key', rowKey);
        }

        fragment.querySelectorAll('[name]').forEach((input) => {
            input.name = input.name.replaceAll('__ROW_KEY__', rowKey);
        });

        fragment.querySelectorAll('[data-wi-row-key]').forEach((element) => {
            if (element.getAttribute('data-wi-row-key') === '__ROW_KEY__') {
                element.setAttribute('data-wi-row-key', rowKey);
            }
        });

        container.appendChild(fragment);
    };

    window.wiRepeaterEnsureDeleteModal = window.wiRepeaterEnsureDeleteModal || function (config = {}) {
        let modalEl = document.getElementById('wi-repeater-delete-modal');

        const defaults = {
            title: "Conferma eliminazione",
            text: "Confermi l'eliminazione della riga?",
            cancelLabel: "Annulla",
            confirmLabel: "Elimina",
            confirmClass: "btn btn-danger",
        };
        const options = { ...defaults, ...config };

        if (!modalEl) {
            modalEl = document.createElement('div');
            modalEl.id = 'wi-repeater-delete-modal';
            modalEl.className = 'modal fade';
            modalEl.tabIndex = -1;
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" data-wi-modal-title="true"></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0" data-wi-modal-text="true"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-wi-modal-cancel="true"></button>
                            <button type="button" data-wi-confirm-delete="true"></button>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modalEl);
        }

        modalEl.querySelector('[data-wi-modal-title="true"]').textContent = options.title;
        modalEl.querySelector('[data-wi-modal-text="true"]').textContent = options.text;
        modalEl.querySelector('[data-wi-modal-cancel="true"]').textContent = options.cancelLabel;

        const confirmBtn = modalEl.querySelector('[data-wi-confirm-delete="true"]');
        confirmBtn.textContent = options.confirmLabel;
        confirmBtn.className = options.confirmClass;

        return modalEl;
    };

    window.wiRepeaterConfirmDelete = window.wiRepeaterConfirmDelete || function (onConfirm, config = {}) {
        if (!window.bootstrap || !window.bootstrap.Modal) {
            const fallbackText = config.text || "Confermi l'eliminazione della riga?";

            if (window.confirm(fallbackText)) {
                onConfirm();
            }

            return;
        }

        const modalEl = window.wiRepeaterEnsureDeleteModal(config);
        const confirmBtn = modalEl.querySelector('[data-wi-confirm-delete="true"]');
        const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);

        confirmBtn.onclick = function () {
            modal.hide();
            onConfirm();
        };

        modal.show();
    };

    window.wiRepeaterRemoveRow = window.wiRepeaterRemoveRow || function (button) {
        const row = button.closest('.wi-repeater-row');
        const container = row ? row.parentElement : null;

        if (!row || !container) {
            return;
        }

        const deleteConfig = {
            title: button.getAttribute('data-wi-delete-title') || 'Conferma eliminazione',
            text: button.getAttribute('data-wi-delete-text') || "Confermi l'eliminazione della riga?",
            cancelLabel: button.getAttribute('data-wi-delete-cancel-label') || 'Annulla',
            confirmLabel: button.getAttribute('data-wi-delete-confirm-label') || 'Elimina',
            confirmClass: button.getAttribute('data-wi-delete-confirm-class') || 'btn btn-danger',
        };

        window.wiRepeaterConfirmDelete(function () {
            if (container.querySelectorAll('.wi-repeater-row:not(.d-none)').length <= 1) {
                row.querySelectorAll('input, textarea, select').forEach((input) => {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = false;
                    } else {
                        input.value = '';
                    }
                });

                return;
            }

            row.remove();
        }, deleteConfig);
    };

    window.wiRepeaterMoveRowUp = window.wiRepeaterMoveRowUp || function (button) {
        const row = button.closest('.wi-repeater-row');
        const previous = row ? row.previousElementSibling : null;

        if (!row || !previous) {
            return;
        }

        row.parentElement.insertBefore(row, previous);
    };

    window.wiRepeaterMoveRowDown = window.wiRepeaterMoveRowDown || function (button) {
        const row = button.closest('.wi-repeater-row');
        const next = row ? row.nextElementSibling : null;

        if (!row || !next) {
            return;
        }

        row.parentElement.insertBefore(next, row);
    };
</script>
HTML;
        $html .= '</div>';

        return $html;

    }

    function submit($label = 'Salva', $name = 'upload', $class = null, $onclick = null) {

        $id = strtolower(code(10, 'letters', 'input_'));
        $action = ($onclick == null) ? "type='submit'" : "type='button' onclick=\"$onclick\"";

        return "<button $action id='$id' name='$name' class='float-end btn btn-dark $class wi-submit' disabled>$label</button>";

    }

    function submitAdd() {

        $submit = submit('Salva', 'upload');
        $submitAdd = submit('Salva e aggiungi', 'upload-add');

        return "
        <div class='container' style='max-width: 100%;'>
            <div class='row row-cols-auto gap-2 justify-content-end'>
                $submitAdd
                $submit
            </div>
        </div>";

    }
