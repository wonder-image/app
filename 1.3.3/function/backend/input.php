<?php

    function password($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='form-floating'>
            <input type='password' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
            <label for='$id'>$label</label>
        </div>
        ";
        
    }

    function email($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='form-floating'>
            <input type='email' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
            <label for='$id'>$label</label>
        </div>
        ";

    }

    function text($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='form-floating'>
            <input type='text' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
            <label for='$id'>$label</label>
        </div>";

    }

    function textGenerator($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='form-floating'>
            <input type='text' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
            <label for='$id'>$label</label>
            <div class='btn btn-sm btn-dark text-light position-absolute top-50 end-0 me-2 translate-middle-y' onclick=\"generateCode('#$id')\">
                GENERA
            </div>
        </div>";

    }

    function textDate($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = date('Y-m-d', strtotime($VALUES[$name])); }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='form-floating'>
            <input type='date' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
            <label for='$id'>$label</label>
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
        <div class='form-floating'>
            <input type='datetime-local' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
            <label for='$id'>$label</label>
        </div>";

    }
    
    function dateInput($label, $name, $dateMin = null, $dateMax = null, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = date('d/m/Y', strtotime($VALUES[$name])); }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        $min = ($dateMin == null) ? '' : 'data-wi-min-date="'.$dateMin.'"';
        $max = ($dateMax == null) ? '' : 'data-wi-max-date="'.$dateMax.'"';

        return "
        <div class='form-floating'>
            <input type='text' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' data-wi-date='true' $min $max $attribute>
            <label for='$id'>$label</label>
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

        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        $min = ($dateMin == null) ? '' : 'data-wi-min-date="'.$dateMin.'"';
        $max = ($dateMax == null) ? '' : 'data-wi-max-date="'.$dateMax.'"';

        return "
        <h6>$label</h6>
        <div class='input-group input-group input-daterange mt-1' data-wi-date-range='true' $min $max>
            <span class='input-group-text'>Dal</span>
            <input id='$idFrom' type='text' class='$class' name='$nameFrom' value='$valueFrom' data-wi-check='true' readonly $attribute>
            <span class='input-group-text'>Al</span>
            <input id='$idTo' type='text' class='$class' name='$nameTo' value='$valueTo' data-wi-check='true' readonly $attribute>
        </div>";

    }

    function color($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        $color = !empty($value) ? "style='color: $value;'" : '';

        return "
        <h6>$label</h6>
        <div class='input-group mt-1'>
            <span class='input-group-text'><i class='bi bi-circle-fill wi-show-color' $color></i></span>
            <input type='text' class='$class' id='$id' aria-describedby='$id-color' name='$name' value='$value' placeholder='$label' data-wi-check='true' data-wi-check-color='true' $attribute>
        </div>";

    }

    function number($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='form-floating'>
            <input type='text' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-number='true' data-wi-check='true' $attribute>
            <label for='$id'>$label</label>
        </div>";

    }

    function price($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='form-floating'>
            <input type='text' class='$class' id='$id' name='$name' value='$value' data-wi-check='true' data-wi-price='true' placeholder='$label' $attribute>
            <label for='$id'>$label</label>
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
        <div class='form-floating'>
            <input type='text' class='$class' id='$id' name='$name' value='$value' data-wi-check='true' data-wi-percentige='true' placeholder='$label' $attribute>
            <label for='$id'>$label</label>
        </div>";

    }

    function url($label, $name, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        $class = "form-control ";
        $class .= attributeSearchClass($attribute);

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        return "
        <div class='form-floating'>
            <input type='url' class='$class' id='$id' name='$name' value='$value' placeholder='$label' data-wi-check='true' $attribute>
            <label for='$id'>$label</label>
        </div>
        ";

    }

    function textarea($label, $name, $attribute = null, $version = null, $value = null) {

        global $VALUES;
        global $PAGE_TABLE;

        $id = strtolower(code(10, 'letters', 'input_'));

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        if ($version != null) {
            
            $valueEncoded =  empty($value) ? "" : base64_encode($value);

            return "
            <div class='form-floating'>
                <h6 class='mb-1'>$label</h6>
                <textarea id='$id' class='d-none' name='$name' data-wi-value='$valueEncoded' data-wi-check='true' data-wi-textarea='$version' $attribute>$value</textarea>
            </div>";

        } else {

            $MAX_LENGHT = isset($PAGE_TABLE[$name]['sql']['lenght']) ? $PAGE_TABLE[$name]['sql']['lenght'] : 0;

            if ($MAX_LENGHT > 0) {
                if ($value == null) { $c = 0; } else { $c = strlen($value); }
                $MAX = "<div class='position-absolute bottom-0 end-0 m-2 me-3'><span class='wi-counter'>$c</span> / <span class='wi-max-lenght'>$MAX_LENGHT</span></div>";
            } else {
                $MAX = "";
            }

            return "
            <div class='form-floating'>
                $MAX
                <textarea class='form-control' placeholder='$label' id='$id' style='height: 100px' name='$name' data-wi-check='true' data-wi-counter='true' $attribute>$value</textarea>
                <label for='$id'>$label</label>
            </div>";
            
        }
        
    }

    function select($label, $name, $option, $version = null, $attribute = null, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));
        
        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        $optionHTML = "";

        $i = 1;
        
        foreach ($option as $vl => $nm) {

            $dataFilter = "";

            if (is_array($nm)) {
    
                $filter = isset($nm['filter']) ? $nm['filter'] : [];
                $nm = $nm['name'];

                foreach ($filter as $key => $v) { $dataFilter .= "data-$key='$v' "; }

            }

            if ($value != null) {
                $att = ($vl == $value) ? "selected" : "";
            } else {
                $att = ($i == 1) ? "selected" : "";
            }

            $optionHTML .= "<option value='$vl' $att $dataFilter>$nm</option>";

            $i++;
            
        }

        if ($version == 'old') {

            return "
            <div id='container-$id' class='w-100 wi-container-select'>
                <h6>$label</h6>
                <select id='$id' name='$name' class='form-select mt-1' data-wi-check='true' $attribute>
                    $optionHTML
                </select>
            </div>";

        } else {

            return "
            <div class='form-floating'>
                <select id='$id' name='$name' class='form-select' data-wi-check='true' $attribute>
                    $optionHTML
                </select>
                <label for='floatingSelect'>$label</label>
            </div>
            ";

        }

    }   

    function check($label, $name, $option, $attribute = null, $type = 'checkbox', $searchBar = false, $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        if (isset($VALUES[$name]) && $value == null) {
            $value = ($type == 'checkbox') ? json_decode($VALUES[$name], true) : $VALUES[$name];
        }

        if ($attribute != null && strpos($attribute, "required") !== false) { 

            $label .= "*"; 
            $attribute = str_replace('required', '', $attribute);
            $required = "wi-$type-required";

        } else {

            $required = "";
            
        }

        $checkHTML = "";
        $inputHidden = "";
        
        $bar = ($searchBar) ? "<input type='text' class='form-control card-header m-0 border-0 border-bottom bg-body' placeholder='Cerca...' aria-label='Cerca...' data-wi-search='true' >" : "";

        if ($type == 'checkbox') {
            $name .= '[]';
            $inputHidden = "<input type='hidden' name='$name'>";
        }

        if (is_array($option)) {

            $dataFilter = "";

            foreach ($option as $nm => $vl) {
    
                if (is_array($value)) {
                    $att = in_array($nm, $value) ? "checked" : "";
                } else {
                    $att = ($nm == $value) ? "checked" : "";
                }
    
                if (is_array($vl)) {
    
                    $filter = isset($vl['filter']) ? $vl['filter'] : [];
                    $vl = $vl['name'];
    
                    foreach ($filter as $key => $v) { $dataFilter .= "data-$key='$v' "; }
    
                }
    
                $checkHTML .= "
                <div id='$name-$nm' class='form-check'>
                    <input class='form-check-input' type='$type' name='$name' value='$nm' id='$type-$name-$nm' data-wi-check='true' $att $dataFilter $attribute>
                    <label class='form-check-label wi-check-label' for='$type-$name-$nm'>$vl</label>
                </div>";
    
            }

        }

        return "
        <div id='container-$id' class='w-100 wi-container-$type $required'>
            <h6>$label</h6>
            $inputHidden
            <div class='card border mt-1'>
                $bar
                <div class='card-body overflow-scroll p-2' style='height: 120px;'>
                    $checkHTML
                </div>
            </div>
        </div>";

    } 

    function dynamicCheck($label, $name, $url, $attribute = null, $type = 'checkbox', $value = null) {

        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        if (isset($VALUES[$name]) && $value == null) {
            $value = ($type == 'checkbox') ? $VALUES[$name] : json_encode([$VALUES[$name]]);
        }

        if ($attribute != null && strpos($attribute, "required") !== false) { 

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
        </div>";

    } 

    function inputFile($label, $name, $file = 'image', $attribute = null, $value = null) {

        global $PATH;
        global $NAME;
        global $TABLE;
        global $VALUES;

        $id = strtolower(code(10, 'letters', 'input_'));

        if (isset($VALUES[$name]) && !isset($value)) { $value = $VALUES[$name]; }
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        $TABLE_NAME = strtoupper($NAME->table);
        $PAGE_TABLE = $TABLE->$TABLE_NAME;

        $TB = $PAGE_TABLE[$name]['input'];
        $maxFile = isset($TB['format']['max_file']) ? $TB['format']['max_file'] : 1;

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
            $ACCEPT = "image/ico";
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
            
            foreach ($ARRAY as $fileId => $fileName) {

                $n = $i + 1;

                $cardClass = "";

                $dir = isset($TB['format']['dir']) ? $TB['format']['dir'] : '/'; 

                if (substr($dir, -1) != '/') {
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $link = $PATH->upload.'/'.$NAME->folder.$dir.'.'.$extension;
                } else {
                    $link = $PATH->upload.'/'.$NAME->folder.$dir.$fileName;
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
                                <a href='$link' download class='btn btn-secondary btn-sm ms-auto'><i class='bi bi-download'></i></a>
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
                <input class='form-control' style='width: 100%;' id='$id' type='file' accept='$ACCEPT' name='$x' data-wi-max-file='$maxFile' data-wi-check='true' $multiple $attribute>
            </div>
            <div class='w-100 mt-1'>
                <small>
                    <ul>
                        <li>File ammessi: <b>$EXTENSIONS_ACCEPT</b></li>
                        <li>File massimi: <b>$maxFile</b></li>
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
        if ($attribute != null && strpos($attribute, "required") !== false) { $label .= "*"; }

        $TABLE_NAME = strtoupper($NAME->table);
        $PAGE_TABLE = $TABLE->$TABLE_NAME;

        $TB = $PAGE_TABLE[$name]['input'];
        $maxFile = isset($TB['format']['max_file']) ? $TB['format']['max_file'] : 1;
        $maxSize = isset($TB['format']['max_size']) ? $TB['format']['max_size'] : 1;

        if ($file == "image") {
            $ACCEPT = "image/png, image/jpeg";
        } elseif ($file == "pdf") {
            $ACCEPT = "application/pdf";
        } elseif ($file == "png") {
            $ACCEPT = "image/png";
        } elseif ($file == "ico") {
            $ACCEPT = "image/ico";
        } elseif ($file == "video") {
            $ACCEPT = "video/mp4";
        } elseif ($file == "jpg") {
            $ACCEPT = "image/jpeg";
        } elseif ($file == "font") {
            $ACCEPT = "font/ttf";
        } else {
            $ACCEPT = "";
        }

        $x = $name.'[]';

        $multiple = ($maxFile > 1) ? "multiple" : "";

        $dir = $PATH->upload.'/'.$NAME->folder;
        $dir .= isset($TB['format']['dir']) ? $TB['format']['dir'] : '/'; 

        if (is_array($value)) { $value = ""; }

        if (!empty($label)) {
            $label = "<h6>$label</h6>";
            $class .= " mt-1";
        }
        
        return "
        <div id='container-$id' class='w-100'>
            $label
            <div class='$class'>
                <input id='$id' type='file' accept='$ACCEPT' name='$x' data-max-file-size='{$maxSize}MB' data-max-files='$maxFile' data-wi-dir='$dir' data-wi-value='$value' data-wi-uploader='$type' data-wi-check='true' $multiple $attribute>
            </div>
        </div>";

    }

    function countryList($continent, $label, $name, $attribute = null, $value = null) {

        $country = geoCountry($continent);
        return check($label, $name, $country, $attribute, 'radio', true, $value);

    }

    function provinceList($country, $label, $name, $value = null, $attribute = null) {

        $province = geoProvince($country);
        return check($label, $name, $province, $attribute, 'radio', true, $value);

    }

    function submit($label = 'Salva', $name = 'upload', $class = null) {

        $id = strtolower(code(10, 'letters', 'input_'));

        return "<button type='submit' id='$id' name='$name' class='float-end btn btn-dark $class' disabled>$label</button>";

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

?>