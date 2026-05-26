<?php

    /**
     * Helper procedurali per i form del frontend pubblico (tema Wonder).
     *
     * Backward compatibility per le pagine frontend scritte prima del
     * sistema `Elements + Themes`. Ogni funzione costruisce l'Element
     * neutro di `Wonder\Elements\Form\Components` e delega al renderer
     * Wonder.
     *
     * NUOVO CODICE: usa la DSL `Wonder\App\ResourceSchema\FormField`
     * oppure costruisci direttamente gli Element. Le Resource del
     * frontend (es. `Resources/Communications/*`) sono già su FormField.
     */

    use Wonder\App\Support\AttributeString;
    use Wonder\Elements\Form\Components\CheckGroup;
    use Wonder\Elements\Form\Components\DateRange;
    use Wonder\Elements\Form\Components\DateTimeRange;
    use Wonder\Elements\Form\Components\File as FileElement;
    use Wonder\Elements\Form\Components\GoogleAddress;
    use Wonder\Elements\Form\Components\InputAcceptDocument;
    use Wonder\Elements\Form\Components\InputEmail;
    use Wonder\Elements\Form\Components\InputNumber;
    use Wonder\Elements\Form\Components\InputPassword;
    use Wonder\Elements\Form\Components\InputPercentige;
    use Wonder\Elements\Form\Components\InputPrice;
    use Wonder\Elements\Form\Components\InputTel;
    use Wonder\Elements\Form\Components\InputText;
    use Wonder\Elements\Form\Components\InputUrl;
    use Wonder\Elements\Form\Components\SearchRemote;
    use Wonder\Elements\Form\Components\Select;
    use Wonder\Elements\Form\Components\SelectDate;
    use Wonder\Elements\Form\Components\SelectOld;
    use Wonder\Elements\Form\Components\Submit as SubmitElement;
    use Wonder\Elements\Form\Components\SubmitRecaptcha as SubmitRecaptchaElement;
    use Wonder\Elements\Form\Components\Textarea;
    use Wonder\Elements\Form\Components\TextList;

    /**
     * Helper interno: applica label/value/error e parsa lo stringa-attributo
     * comune a tutti i campi del frontend.
     */
    function frontendApplyCommon($element, string $label, mixed $value, string $attribute, mixed $error): object
    {

        $element
            ->label($label)
            ->value($value)
            ->attributes(AttributeString::parse($attribute));

        if (!empty($error)) {
            $element->error((string) $error);
        }

        return $element;

    }

    function text($label, $name, $value = null, $attribute = '', $error = false) {

        return frontendApplyCommon(new InputText($name), (string) $label, $value, (string) $attribute, $error)->render();

    }

    function number($label, $name, $value = null, $attribute = '', $error = false) {

        return frontendApplyCommon(new InputNumber($name), (string) $label, $value, (string) $attribute, $error)->render();

    }

    function phone($label, $name, $value = null, $attribute = '', $error = false) {

        return frontendApplyCommon(new InputTel($name), (string) $label, $value, (string) $attribute, $error)->render();

    }

    function price($label, $name, $value = null, $attribute = '', $error = false) {

        return frontendApplyCommon(new InputPrice($name), (string) $label, $value, (string) $attribute, $error)->render();

    }

    function percentige($label, $name, $value = null, $attribute = '', $error = false) {

        return frontendApplyCommon(new InputPercentige($name), (string) $label, $value, (string) $attribute, $error)->render();

    }

    function url($label, $name, $value = null, $attribute = '', $error = false) {

        return frontendApplyCommon(new InputUrl($name), (string) $label, $value, (string) $attribute, $error)->render();

    }

    function email($label, $name, $value = null, $attribute = '', $error = false) {

        return frontendApplyCommon(new InputEmail($name), (string) $label, $value, (string) $attribute, $error)->render();

    }

    function textarea($label, $name, $value = null, $attribute = '', $error = false) {

        return frontendApplyCommon(new Textarea($name), (string) $label, $value, (string) $attribute, $error)->render();

    }

    function password($label, $name, $value = null, $attribute = '', $error = false) {

        return frontendApplyCommon(new InputPassword($name), (string) $label, $value, (string) $attribute, $error)->render();

    }

    function inputFile($label, $name, $type = 'image', $maxSize = 1, $maxFile = 1, $value = null, $attribute = '', $error = false) {

        $element = (new FileElement($name))
            ->label((string) $label)
            ->file((string) $type)
            ->maxFile((int) $maxFile)
            ->maxSize((int) $maxSize)
            ->fileValue($value)
            ->attributes(AttributeString::parse((string) $attribute));

        if (!empty($error)) {
            $element->error((string) $error);
        }

        return $element->render();

    }

    function selectDate($label, $name, $value = null, $attribute = '', $dateMin = null, $dateMax = null, $error = false) {

        $element = (new SelectDate($name))
            ->label((string) $label)
            ->value($value)
            ->attributes(AttributeString::parse((string) $attribute));

        if (is_string($dateMin) && trim($dateMin) !== '') { $element->min(trim($dateMin)); }
        if (is_string($dateMax) && trim($dateMax) !== '') { $element->max(trim($dateMax)); }
        if (!empty($error)) { $element->error((string) $error); }

        return $element->render();

    }

    function dateRange($label, $name, $value = null, $attribute = '', $dateMin = null, $dateMax = null, $error = false) {

        $element = (new DateRange($name))
            ->label((string) $label)
            ->value(is_array($value) ? $value : ['', ''])
            ->attributes(AttributeString::parse((string) $attribute));

        if (is_string($dateMin) && trim($dateMin) !== '') { $element->min(trim($dateMin)); }
        if (is_string($dateMax) && trim($dateMax) !== '') { $element->max(trim($dateMax)); }
        if (!empty($error)) { $element->error((string) $error); }

        return $element->render();

    }

    function dateTimeRange($label, $name, $value = null, $attribute = '', $dateMin = null, $dateMax = null, $error = false) {

        $element = (new DateTimeRange($name))
            ->label((string) $label)
            ->value(is_array($value) ? $value : ['', ''])
            ->attributes(AttributeString::parse((string) $attribute));

        if (is_string($dateMin) && trim($dateMin) !== '') { $element->min(trim($dateMin)); }
        if (is_string($dateMax) && trim($dateMax) !== '') { $element->max(trim($dateMax)); }
        if (!empty($error)) { $element->error((string) $error); }

        return $element->render();

    }

    function textList($label, $name, $options, $value = null, $attribute = '', $error = false) {

        $element = (new TextList($name))
            ->label((string) $label)
            ->value($value)
            ->options(is_array($options) ? $options : [])
            ->attributes(AttributeString::parse((string) $attribute));

        if (!empty($error)) {
            $element->error((string) $error);
        }

        return $element->render();

    }

    function searchText($label, $name, $url, $value = null, $attribute = '') {

        return (new SearchRemote($name))
            ->label((string) $label)
            ->value($value)
            ->url((string) $url)
            ->searchType('text')
            ->attributes(AttributeString::parse((string) $attribute))
            ->render();

    }

    function searchRadio($label, $name, $url, $value = null, $attribute = '') {

        return (new SearchRemote($name))
            ->label((string) $label)
            ->value($value)
            ->url((string) $url)
            ->searchType('radio')
            ->attributes(AttributeString::parse((string) $attribute))
            ->render();

    }

    function countryList($continent, $label, $name, $value = null, $attribute = '') {

        $country = geoCountry($continent);
        return textList($label, $name, $country, $value, $attribute);

    }

    function provinceList($country, $label, $name, $value = null, $attribute = '') {

        $province = geoProvince($country);
        return textList($label, $name, $province, $value, $attribute);

    }

    function inputCountry($label, $name, $value = null, $nameState = null, $attribute = '') {

        $options = countries();

        if (!empty($nameState)) {
            $attribute .= ' data-wi-input-country="true" data-wi-input-state="'.$nameState.'"';
        }

        return textList($label, $name, $options, $value, $attribute);

    }

    function inputStates($label, $name, $country = null, $value = null, $attribute = '') {

        $options = !empty($country) ? states($country) : [];

        return textList(
            $label,
            $name,
            $options,
            $value,
            $attribute.' data-wi-input-state="true" data-wi-list-states="'.$country.'" data-wi-input-attribute="'.$attribute.'"'
        );

    }

    function inputPhonePrefix($label, $name, $value = null, $attribute = '') {

        $options = phonePrefix();
        return textList($label, $name, $options, $value, $attribute);

    }

    function select($label, $name, $option, $value = null, $attribute = '', $error = false) {

        $element = (new Select($name))
            ->label((string) $label)
            ->value($value)
            ->options(is_array($option) ? $option : [])
            ->attributes(AttributeString::parse((string) $attribute));

        if (!empty($error)) {
            $element->error((string) $error);
        }

        return $element->render();

    }

    function selectOld($label, $name, $option, $value = null, $attribute = '', $error = false) {

        $element = (new SelectOld($name))
            ->label((string) $label)
            ->value($value)
            ->options(is_array($option) ? $option : [])
            ->attributes(AttributeString::parse((string) $attribute));

        if (!empty($error)) {
            $element->error((string) $error);
        }

        return $element->render();

    }

    function checkbox($label, $name, $option, $type = 'checkbox', $value = null) {

        return (new CheckGroup($name))
            ->label((string) $label)
            ->value($value)
            ->options(is_array($option) ? $option : [])
            ->inputType((string) $type)
            ->render();

    }

    function inputAcceptDocument(string $type, ?string $attributes = null, $value = null): string
    {

        $type = strtolower(trim($type));
        $type = preg_replace('/[^a-z0-9_-]/', '', $type) ?? '';

        if (!Wonder\Consent\LegalDocumentTypeContext::hasType($type)) {
            __log(new Exception('Non esiste il documento '.$type), 'wonder-renderer', 'verify_document_type');
        }

        $SQL = sqlSelect(
            'legal_documents',
            [
                'doc_type' => $type,
                'language_code' => __l(),
                'active' => 'true',
            ],
            1,
            'published_at DESC, id',
            'DESC'
        );

        if (!$SQL->exists) {
            __log(new Exception('Non esiste il documento '.$type.' nella lingua '.__l()), 'wonder-renderer', 'verify_document_type_lang');
            return '';
        }

        $document = infoLegalDocument($SQL->id);
        $fieldName = 'accept_'.$type;
        $attributesArray = AttributeString::parse((string) $attributes);

        $element = (new InputAcceptDocument($fieldName))
            ->documentType($type)
            ->documentId((int) $document->id)
            ->documentLabel((string) $document->renderLabel)
            ->attributes($attributesArray);

        $checked = false;

        if (is_array($value)) {
            foreach ($value as $item) {
                if (filter_var($item, FILTER_VALIDATE_BOOLEAN)) {
                    $checked = true;
                    break;
                }
            }
        } elseif ($value !== null) {
            $checked = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($checked) {
            $element->checked();
        }

        return $element->render();

    }

    function googleAddress($label, $name = "address", $value = [
        'country' => '',
        'province' => '',
        'city' => '',
        'cap' => '',
        'street' => '',
        'number' => '',
    ], $attribute = '', $restriction = null, $error = false) {

        $element = (new GoogleAddress($name))
            ->label((string) $label)
            ->alias((string) $name)
            ->restriction(is_array($restriction) ? $restriction : [])
            ->breakdown(is_array($value) ? $value : [])
            ->attributes(AttributeString::parse((string) $attribute));

        if (!empty($error)) {
            $element->error((string) $error);
        }

        return $element->render();

    }

    function submit($label, $name, $class = 'btn-success', $onclick = null) {

        $element = (new SubmitElement((string) $name))
            ->label((string) $label)
            ->buttonClass('btn '.$class);

        if (!empty($onclick)) {
            $element->onclick((string) $onclick);
        }

        return $element->render();

    }

    function submitRecaptcha($label, $name, $class = 'btn-success', $callback = 'sendForm') {

        $siteKey = \Wonder\App\Credentials::api()->g_recaptcha_site_key;

        return (new SubmitRecaptchaElement((string) $name))
            ->label((string) $label)
            ->buttonClass('btn '.$class)
            ->siteKey((string) $siteKey)
            ->callback((string) $callback)
            ->action('submit')
            ->render();

    }
