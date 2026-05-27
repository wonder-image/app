<?php

    /**
     * Helper procedurali per i form del backend (tema Bootstrap).
     *
     * Questo file espone funzioni globali (`text()`, `email()`,
     * `select()`, …) per backward compatibility con le pagine
     * dell'admin scritte prima dell'introduzione del sistema
     * `Elements + Themes`.
     *
     * IMPLEMENTAZIONE: ogni funzione è una "compatibility shim" che
     * costruisce l'Element di `Wonder\Elements\Form\Components` e
     * delega il rendering al tema attivo (Bootstrap qui).
     *
     * NUOVO CODICE: usa direttamente la DSL `Wonder\App\ResourceSchema\
     * FormField::key(...)->text()->required()` oppure costruisci
     * l'Element. `FormField` NON dipende più da queste funzioni: il
     * rendering passa per `FormFieldElementFactory`.
     */

    use Wonder\App\Support\AttributeString;
    use Wonder\App\Support\CssFontFamily;
    use Wonder\Elements\Form\Components\CheckBoolean;
    use Wonder\Elements\Form\Components\CheckGroup;
    use Wonder\Elements\Form\Components\CheckTree;
    use Wonder\Elements\Form\Components\Checkbox;
    use Wonder\Elements\Form\Components\Date as DateElement;
    use Wonder\Elements\Form\Components\DatePicker;
    use Wonder\Elements\Form\Components\DateRange;
    use Wonder\Elements\Form\Components\DynamicCheck;
    use Wonder\Elements\Form\Components\File as FileElement;
    use Wonder\Elements\Form\Components\InputColor;
    use Wonder\Elements\Form\Components\InputDatetime;
    use Wonder\Elements\Form\Components\InputEmail;
    use Wonder\Elements\Form\Components\InputNumber;
    use Wonder\Elements\Form\Components\InputPassword;
    use Wonder\Elements\Form\Components\InputPercentige;
    use Wonder\Elements\Form\Components\InputPrice;
    use Wonder\Elements\Form\Components\InputTel;
    use Wonder\Elements\Form\Components\InputText;
    use Wonder\Elements\Form\Components\InputTime;
    use Wonder\Elements\Form\Components\InputUrl;
    use Wonder\Elements\Form\Components\Repeater;
    use Wonder\Elements\Form\Components\Select;
    use Wonder\Elements\Form\Components\Submit as SubmitElement;
    use Wonder\Elements\Form\Components\Textarea;
    use Wonder\Elements\Form\Components\TextareaEditor;
    use Wonder\Elements\Form\Components\TextGenerator;

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

    /**
     * Helper interno: legge $VALUES[$name] come default se $value non passato.
     */
    function backendValueDefault(string $name, mixed $value): mixed
    {
        global $VALUES;

        if ($value === null && isset($VALUES[$name])) {
            return $VALUES[$name];
        }

        return $value;
    }

    function password($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        return (new InputPassword($name))
            ->label((string) $label)
            ->value($value)
            ->placeholder((string) $label)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function email($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        return (new InputEmail($name))
            ->label((string) $label)
            ->value($value)
            ->placeholder((string) $label)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function phone($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        return (new InputTel($name))
            ->label((string) $label)
            ->value($value)
            ->placeholder((string) $label)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function text($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        if ($name === 'font_family') {
            $value = CssFontFamily::normalize($value);
        }

        return (new InputText($name))
            ->label((string) $label)
            ->value($value)
            ->placeholder((string) $label)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function textGenerator($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        if ($name === 'font_family') {
            $value = CssFontFamily::normalize($value);
        }

        return (new TextGenerator($name))
            ->label((string) $label)
            ->value($value)
            ->placeholder((string) $label)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function textDate($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        if (is_scalar($value) && trim((string) $value) !== '') {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $value = date('Y-m-d', $ts);
            }
        }

        return (new DateElement($name))
            ->label((string) $label)
            ->value($value)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function textDatetime($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        if (is_scalar($value) && trim((string) $value) !== '') {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $value = date('Y-m-d\TH:i', $ts);
            }
        }

        return (new InputDatetime($name))
            ->label((string) $label)
            ->value($value)
            ->placeholder((string) $label)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function dateInput($label, $name, $dateMin = null, $dateMax = null, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        if (is_scalar($value) && trim((string) $value) !== '') {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $value = date('d/m/Y', $ts);
            }
        }

        $element = (new DatePicker($name))
            ->label((string) $label)
            ->value($value)
            ->attributes(AttributeString::parse($attribute));

        if (is_string($dateMin) && trim($dateMin) !== '') { $element->min(trim($dateMin)); }
        if (is_string($dateMax) && trim($dateMax) !== '') { $element->max(trim($dateMax)); }

        return $element->render();

    }

    function timeInput($label, $name, $step = 900, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        if (!empty($value)) {
            $value = substr((string) $value, 0, 5);
        }

        $element = (new InputTime($name))
            ->label((string) $label)
            ->value($value)
            ->placeholder((string) $label)
            ->attributes(AttributeString::parse($attribute));

        if (is_numeric($step) && (int) $step > 0) {
            $element->step((int) $step);
        }

        return $element->render();

    }

    function dateRange($label, $name, $dateMin = null, $dateMax = null, $attribute = null, $value = null) {

        global $VALUES;

        $nameFrom = $name.'_from';
        $nameTo = $name.'_to';

        if (isset($VALUES[$nameFrom]) && isset($VALUES[$nameTo]) && !isset($value)) {
            $valueFrom = date('d/m/Y', strtotime($VALUES[$nameFrom]));
            $valueTo = date('d/m/Y', strtotime($VALUES[$nameTo]));
        } elseif (isset($value) && is_array($value)) {
            $valueFrom = date('d/m/Y', strtotime((string) $value[0]));
            $valueTo = date('d/m/Y', strtotime((string) $value[1]));
        } else {
            $valueFrom = '';
            $valueTo = '';
        }

        $element = (new DateRange($name))
            ->label((string) $label)
            ->value([$valueFrom, $valueTo])
            ->attributes(AttributeString::parse($attribute));

        if (is_string($dateMin) && trim($dateMin) !== '') { $element->min(trim($dateMin)); }
        if (is_string($dateMax) && trim($dateMax) !== '') { $element->max(trim($dateMax)); }

        return $element->render();

    }

    function color($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        return (new InputColor($name))
            ->label((string) $label)
            ->value($value)
            ->placeholder((string) $label)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function number($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        return (new InputNumber($name))
            ->label((string) $label)
            ->value($value)
            ->placeholder((string) $label)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function price($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        return (new InputPrice($name))
            ->label((string) $label)
            ->value($value)
            ->placeholder((string) $label)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function percentige($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        return (new InputPercentige($name))
            ->label((string) $label)
            ->value($value)
            ->placeholder((string) $label)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function url($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        return (new InputUrl($name))
            ->label((string) $label)
            ->value($value)
            ->placeholder((string) $label)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function textarea($label, $name, $attribute = null, $version = null, $value = null) {

        global $VALUES;
        global $NAME;
        global $TABLE;

        $value = backendValueDefault($name, $value);

        $attributesArray = AttributeString::parse($attribute);

        if ($version !== null) {

            $element = (new TextareaEditor($name))
                ->label((string) $label)
                ->value($value)
                ->version((string) $version)
                ->attributes($attributesArray);

            if (isset($NAME) && is_object($NAME) && isset($NAME->folder)) {
                $element->folder((string) $NAME->folder);
            }

            return $element->render();

        }

        if (isset($NAME->table)) {
            $PAGE_TABLE = backendPageTableSchema();
            $MAX_LENGHT = isset($PAGE_TABLE[$name]['sql']['lenght']) ? $PAGE_TABLE[$name]['sql']['lenght'] : 0;
        } else {
            $MAX_LENGHT = 0;
        }

        $element = (new Textarea($name))
            ->label((string) $label)
            ->value($value)
            ->counter()
            ->attributes($attributesArray);

        if ($MAX_LENGHT > 0) {
            $element->maxLength((int) $MAX_LENGHT);
        }

        return $element->render();

    }

    function selectSearch($label, $name, $option, $multiple = false, $version = null, $attribute = null, $value = null) {

        $attributesArray = AttributeString::parse($attribute);
        $attributesArray['data-wi-select-search'] = 'true';

        if ($multiple) {
            $attributesArray['data-wi-select-search-multiple'] = 'true';
            $attributesArray['multiple'] = true;
        }

        return selectRender($label, $name, $option, $version, $attributesArray, $value);

    }

    function select($label, $name, $option, $version = null, $attribute = null, $value = null) {

        return selectRender($label, $name, $option, $version, AttributeString::parse($attribute), $value);

    }

    /**
     * Helper interno usato da `select()` / `selectSearch()` per costruire l'Element.
     * Tiene fuori dalla API pubblica il dettaglio "version=old usa container + label esterna".
     */
    function selectRender($label, $name, $option, $version, array $attributesArray, $value)
    {

        $value = backendValueDefault($name, $value);

        $element = (new Select($name))
            ->label((string) $label)
            ->value($value)
            ->options(is_array($option) ? $option : [])
            ->attributes($attributesArray);

        if ($version === 'old') {
            $element->legacyContainer();
        }

        return $element->render();

    }

    function check($label, $name, $option, $attribute = null, $type = 'checkbox', $searchBar = false, $value = null) {

        global $VALUES;

        if ($value === null && isset($VALUES[$name])) {
            $value = ($type == 'checkbox') ? json_decode($VALUES[$name], true) : $VALUES[$name];
        }

        return (new CheckGroup($name))
            ->label((string) $label)
            ->value($value)
            ->options(is_array($option) ? $option : [])
            ->inputType((string) $type)
            ->searchBar((bool) $searchBar)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function checkTree($label, $name, $option, $attribute = null, $type = 'checkbox', $searchBar = false, $value = null) {

        global $VALUES;

        if ($value === null && isset($VALUES[$name])) {
            $value = ($type == 'checkbox') ? json_decode($VALUES[$name], true) : $VALUES[$name];
        }

        return (new CheckTree($name))
            ->label((string) $label)
            ->value($value)
            ->options(is_array($option) ? $option : [])
            ->inputType((string) $type)
            ->searchBar((bool) $searchBar)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function dynamicCheck($label, $name, $url, $attribute = null, $type = 'checkbox', $value = null) {

        global $VALUES;

        if ($value === null && isset($VALUES[$name])) {
            $value = ($type == 'checkbox') ? $VALUES[$name] : json_encode([$VALUES[$name]]);
        }

        return (new DynamicCheck($name))
            ->label((string) $label)
            ->value($value)
            ->url((string) $url)
            ->inputType((string) $type)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function checkbox($label, $name, $attribute = null, $value = null) {

        $value = backendValueDefault($name, $value);

        $checkbox = (new Checkbox($name))
            ->label((string) $label)
            ->attributes(AttributeString::parse($attribute));

        if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on') {
            $checkbox->checked();
        }

        return $checkbox->render();

    }

    function checkBoolean($label, $name, $attribute = null, $option = ['', 'true', 'false'], $value = null) {

        $value = backendValueDefault($name, $value);

        $option = is_array($option) ? array_pad($option, 3, '') : ['', 'true', 'false'];

        return (new CheckBoolean($name))
            ->label((string) $label)
            ->value($value)
            ->values((string) $option[0], (string) $option[1], (string) $option[2])
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function inputFile($label, $name, $file = 'image', $attribute = null, $value = null) {

        global $PATH;
        global $NAME;
        global $VALUES;

        $value = backendValueDefault($name, $value);

        $PAGE_TABLE = backendPageTableSchema();
        $TB = (array) (($PAGE_TABLE[$name]['input'] ?? []));
        $maxFile = (int) ($TB['format']['max_file'] ?? 1);
        $maxSize = (int) ($TB['format']['max_size'] ?? 1);

        $extensionsAccept = match ((string) $file) {
            'image' => '.png - .jpg - .jpeg',
            'pdf' => '.pdf',
            'png' => '.png',
            'ico' => '.ico',
            'media' => '.png, .jpg, .jpeg, .webp, .pdf',
            'video' => '.mp4',
            'jpg' => '.jpg - .jpeg',
            'font' => '.ttf',
            default => '',
        };

        $element = (new FileElement($name))
            ->label((string) $label)
            ->file((string) $file)
            ->mode('classic')
            ->maxFile($maxFile)
            ->maxSize($maxSize)
            ->extensionsAccept($extensionsAccept)
            ->attributes(AttributeString::parse($attribute));

        $gallery = backendInputFileGallery($element->id, $name, $value, $TB, $file);

        if ($gallery !== '') {
            $element->gallery($gallery);
        }

        # se il numero di file caricati >= max file, l'input upload diventa disabled
        $alreadyUploaded = is_string($value) && isset($VALUES['id'])
            ? count((array) (json_decode((string) $value, true) ?: []))
            : 0;

        if ($alreadyUploaded >= $maxFile) {
            $element->disabled();
        }

        return $element->render();

    }

    /**
     * Costruisce l'HTML della gallery dei file esistenti per `inputFile()`.
     *
     * Vive qui (non nel renderer) perché ha bisogno dei globals
     * (`$PATH`, `$NAME`, `$VALUES`) e dello schema risolto da
     * `backendPageTableSchema()`. Il renderer del tema riceve solo
     * la stringa HTML pronta da inserire.
     */
    function backendInputFileGallery(string $containerId, string $name, mixed $value, array $TB, string $file): string
    {

        global $PATH;
        global $NAME;
        global $VALUES;

        if (empty($value) || !isset($VALUES['id'])) {
            return '';
        }

        $array = json_decode((string) $value, true);

        if (!is_array($array) || $array === []) {
            return '';
        }

        $rowId = $VALUES['id'];
        $count = count($array);
        $imageSize = '';
        $sizeBefore = false;

        if (isset($TB['format']['resize'])) {
            $imageResize = $TB['format']['resize'];

            if (isset($imageResize['width'])) {
                $imageSize = $imageResize['width'].'x'.$imageResize['height'].'-';
                $sizeBefore = true;
            } else if (isset($imageResize[0]['width'])) {
                $smallest = PHP_INT_MAX;
                foreach ($imageResize as $size) {
                    if ($size['width'] < $smallest) {
                        $smallest = $size['width'];
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
        }

        $dir = isset($TB['format']['dir']) ? $TB['format']['dir'] : '/';

        $html = "<div class='row g-3'>";
        $i = 0;

        foreach ($array as $fileId => $fileName) {
            $n = $i + 1;
            $cardClass = '';

            if (substr($dir, -1) != '/') {
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                $linkDownload = $PATH->upload.'/'.$NAME->folder.$dir.'.'.$extension;
                $link = $linkDownload;
            } else {
                $linkDownload = $PATH->upload.'/'.$NAME->folder.$dir.$fileName;
                if ($sizeBefore) {
                    $link = $PATH->upload.'/'.$NAME->folder.$dir.$imageSize.$fileName;
                } else {
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                    $link = $PATH->upload.'/'.$NAME->folder.$dir.$baseName.$imageSize.'.'.$extension;
                }
            }

            $image = in_array($file, ['image', 'png', 'ico', 'jpg'], true)
                ? "<img class='w-100 object-fit-contain' src='$link' height='200' lazyload>"
                : '';

            if ($count == 1) {
                $arrowUp = '';
                $arrowDown = '';
            } else {
                $arrowUp = "<button type='button' class='btn btn-light btn-sm wi-arrow-up' onclick=\"moveFile('#container-$containerId', '#card-file-$fileId', 'up')\"><i class='bi bi-chevron-left'></i></button>";
                $arrowDown = "<button type='button' class='btn btn-light btn-sm wi-arrow-down' onclick=\"moveFile('#container-$containerId', '#card-file-$fileId', 'down')\"><i class='bi bi-chevron-right'></i></button>";
            }

            if ($n == 1) {
                $cardClass .= 'wi-first-file';
            } else if ($n == $count) {
                $cardClass .= 'wi-last-file';
            }

            $html .= "
            <div id='card-file-$fileId' class='wi-card-file $cardClass col-4 order-$n' data-wi-order='$n' data-wi-n-file='$count' data-wi-db-table='{$NAME->table}' data-wi-db-column='$name' data-wi-db-row='$rowId' data-wi-folder='{$NAME->folder}' data-wi-file-id='$fileId' data-wi-file-name='$fileName'>
                <div class='card border overflow-hidden'>
                    $image
                    <div class='card-body'>
                        <p class='card-title'>$fileName</p>
                        <div class='d-flex w-100 gap-2'>
                            $arrowUp
                            $arrowDown
                            <a href='$linkDownload' download class='btn btn-secondary btn-sm ms-auto'><i class='bi bi-download'></i></a>
                            <button type='button' class='btn btn-danger btn-sm' onclick=\"deleteFile('#container-$containerId', '#card-file-$fileId')\"><i class='bi bi-trash3'></i></button>
                        </div>
                    </div>
                </div>
            </div>";

            $i++;
        }

        $html .= '</div>';

        return $html;

    }

    function inputFileDragDrop($label, $name, $type = 'classic', $file = 'image', $attribute = null, $value = null) {

        global $PATH;
        global $NAME;
        global $VALUES;

        $value = backendValueDefault($name, $value);

        $PAGE_TABLE = backendPageTableSchema();

        $columnName = (isset($NAME->column) && !is_array($NAME->column)) ? $NAME->column : $name;

        $TB = (array) (($PAGE_TABLE[$columnName]['input'] ?? []));
        $maxFile = $TB['format']['max_file'] ?? 1;
        $maxSize = $TB['format']['max_size'] ?? 1;

        # min size image + size before (riproduce la logica originale)
        $imageSize = '';
        $sizeBefore = false;

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

        }

        # directory upload (riproduce la logica originale, ritaglio l'ultimo segmento se dir non termina con /)
        $legacyName = (isset($NAME) && is_object($NAME))
            ? $NAME
            : \Wonder\App\LegacyGlobals::get('NAME');

        $folder = is_object($legacyName) ? trim((string) ($legacyName->folder ?? ''), '/') : '';

        $dir = rtrim((string) ($PATH->upload ?? ''), '/');
        $dir .= $folder !== '' ? '/'.$folder : '';
        $dir .= $TB['format']['dir'] ?? '/';

        if (substr($dir, -1) != '/') {
            $NEW_NAME = explode('/', $dir);
            $lastKey = array_key_last($NEW_NAME);
            $NEW_NAME = $NEW_NAME[$lastKey];
            $dir = str_replace($NEW_NAME, '', $dir);
        }

        if (is_array($value)) { $value = ""; }

        return (new FileElement($name))
            ->label((string) $label)
            ->file((string) $file)
            ->uploader((string) $type)
            ->maxFile((int) $maxFile)
            ->maxSize((int) $maxSize)
            ->directory($dir)
            ->fileValue($value)
            ->sizeBefore((bool) $sizeBefore)
            ->minSizeImage($imageSize !== '' ? $imageSize : null)
            ->attributes(AttributeString::parse($attribute))
            ->render();

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

        $columns = is_array($config['columns'] ?? null) ? $config['columns'] : [];

        return (new Repeater($name))
            ->label((string) $label)
            ->value($value)
            ->columns($columns)
            ->context($config)
            ->attributes(AttributeString::parse($attribute))
            ->render();

    }

    function submit($label = 'Salva', $name = 'upload', $class = null, $onclick = null) {

        # Shim backend Bootstrap: l'Element non porta più un default
        # tema-specifico (vedi `Wonder\Elements\Form\Components\Submit`),
        # quindi qui inizializziamo esplicitamente la baseline Bootstrap
        # `float-end btn btn-dark` prima di accodare la variante colore
        # richiesta dal caller.
        $element = (new SubmitElement((string) $name))
            ->label((string) $label)
            ->buttonClass('float-end btn btn-dark');

        if (!empty($class)) {
            $element->addButtonClass((string) $class);
        }

        if (!empty($onclick)) {
            $element->onclick((string) $onclick);
        }

        return $element->render();

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
