<?php

namespace Wonder\App\Support;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\Support\AttributeString;
use Wonder\Elements\Form\Components\CheckBoolean;
use Wonder\Elements\Form\Components\CheckGroup;
use Wonder\Elements\Form\Components\CheckTree;
use Wonder\Elements\Form\Components\Checkbox;
use Wonder\Elements\Form\Components\Date;
use Wonder\Elements\Form\Components\DatePicker;
use Wonder\Elements\Form\Components\DateRange;
use Wonder\Elements\Form\Components\DynamicCheck;
use Wonder\Elements\Form\Components\File;
use Wonder\Elements\Form\Components\GoogleAddress;
use Wonder\Elements\Form\Components\Hidden;
use Wonder\Elements\Form\Components\InputAcceptDocument;
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
use Wonder\Elements\Form\Components\reCAPTCHA;
use Wonder\Elements\Form\Components\Repeater;
use Wonder\Elements\Form\Components\Select;
use Wonder\Elements\Form\Components\Textarea;
use Wonder\Elements\Form\Components\TextareaEditor;
use Wonder\Elements\Form\Components\TextGenerator;
use Wonder\Elements\Form\Field as ElementField;

final class FormFieldElementFactory
{
    /**
     * Render del `FormField` come Element del tema attivo.
     *
     * Ritorna `null` SOLO se l'helper non è gestito (mapping non
     * trovato in `make()`). NON cattura Throwable lanciati dal render:
     * un errore nel renderer del tema deve propagarsi così che il
     * chiamante possa diagnosticarlo invece che fallire silently.
     */
    public static function render(Input $field, ?string $theme = null): ?string
    {
        if ((string) ($field->get('helper') ?? '') === 'inputAcceptDocument'
            && self::resolveLegalDocument($field) === null) {
            return '';
        }

        $element = self::make($field);

        if (!$element instanceof ElementField) {
            return null;
        }

        return $element->render($theme);
    }

    private static function make(Input $field): ?ElementField
    {
        $helper = (string) ($field->get('helper') ?? 'text');
        $name = trim((string) ($field->name ?? ''));

        if ($name === '') {
            return null;
        }

        $element = match ($helper) {
            'hidden' => new Hidden($name),
            'text' => new InputText($name),
            'textGenerator' => self::textGeneratorElement($name, $field),
            'email' => new InputEmail($name),
            'tel', 'phone' => new InputTel($name),
            'number' => new InputNumber($name),
            'price' => new InputPrice($name),
            'percentige' => new InputPercentige($name),
            'password' => self::passwordElement($name, $field),
            'url' => new InputUrl($name),
            'color' => new InputColor($name),
            'textDate' => new Date($name),
            'textDatetime' => new InputDatetime($name),
            'dateInput' => new DatePicker($name),
            'dateRange' => new DateRange($name),
            'timeInput' => new InputTime($name),
            'textarea' => self::textareaElement($name, $field),
            'select' => self::selectElement($name, $field),
            'selectSearch' => self::selectSearchElement($name, $field),
            'inputCountry' => self::countryElement($name, $field),
            'inputStates' => self::statesElement($name, $field),
            'inputPhonePrefix' => self::phonePrefixElement($name, $field),
            'radio' => self::checkGroupElement($name, $field, 'radio'),
            'checkbox' => self::checkboxLikeElement($name, $field),
            'checkTree' => self::checkTreeElement($name, $field),
            'dynamicCheck' => self::dynamicCheckElement($name, $field),
            'checkBoolean' => self::checkBooleanElement($name, $field),
            'googleAddress' => self::googleAddressElement($name, $field),
            'inputFile', 'inputFileDragDrop' => self::fileElement($name, $field),
            'inputRepeater' => self::repeaterElement($name, $field),
            'inputAcceptDocument' => self::acceptDocumentElement($name, $field),
            'recaptcha' => self::recaptchaElement($name, $field),
            default => null,
        };

        if (!$element instanceof ElementField) {
            return null;
        }

        self::hydrate($element, $field);

        return $element;
    }

    private static function hydrate(ElementField $element, Input $field): void
    {
        $label = trim((string) ($field->get('label') ?? ''));
        $value = $field->get('value');
        $error = trim((string) ($field->get('error') ?? ''));
        $attributes = AttributeString::parse((string) ($field->get('attribute') ?? ''));

        if ($label === '') {
            $label = ucwords(str_replace(['_', '-'], ' ', $field->name));
        }

        if ($field->name === 'font_family' && is_scalar($value)) {
            $value = CssFontFamily::normalize((string) $value);
        }

        if ($element instanceof Date) {
            $value = self::formatNativeDateValue($value);
        }

        if ($element instanceof InputDatetime) {
            $value = self::formatDatetimeValue($value);
        }

        if ($element instanceof DatePicker) {
            $value = self::formatPickerDateValue($value);
        }

        if ($element instanceof DateRange) {
            $value = self::normalizeDateRangeValue($field, $value);
        }

        $element->label($label)->value($value);

        if ($error !== '') {
            $element->error($error);
        }

        if ($attributes !== []) {
            $element->attributes($attributes);
        }

        if ($element instanceof Date || $element instanceof DatePicker || $element instanceof DateRange) {
            $dateMin = $field->get('date_min');
            $dateMax = $field->get('date_max');

            if (is_string($dateMin) && trim($dateMin) !== '') {
                $element->min(trim($dateMin));
            }

            if (is_string($dateMax) && trim($dateMax) !== '') {
                $element->max(trim($dateMax));
            }
        }

        if ($element instanceof InputTime) {
            $timeStep = $field->get('time_step');

            if (is_numeric($timeStep) && (int) $timeStep > 0) {
                $element->step((int) $timeStep);
            }
        }
    }

    private static function textareaElement(string $name, Input $field): ElementField
    {
        $version = $field->get('version');

        if (!is_string($version) || trim($version) === '') {
            return new Textarea($name);
        }

        return (new TextareaEditor($name))
            ->version(trim($version))
            ->folder(self::legacyFolder());
    }

    private static function selectElement(string $name, Input $field): Select
    {
        return (new Select($name))
            ->options(self::normalizeOptions((array) ($field->get('options') ?? [])));
    }

    private static function selectSearchElement(string $name, Input $field): Select
    {
        $select = self::selectElement($name, $field);
        $multiple = (bool) ($field->get('multiple') ?? false);

        $select->attr('data-wi-select-search', 'true');

        if ($multiple) {
            $select->attr('data-wi-select-search-multiple', 'true');
            $select->attr('multiple', true);
        }

        return $select;
    }

    private static function countryElement(string $name, Input $field): ?Select
    {
        if (!function_exists('countries')) {
            return null;
        }

        $select = self::selectSearchElement($name, $field)
            ->options(self::normalizeOptions(countries()));

        $context = (array) ($field->get('context') ?? []);
        $stateField = $context['state_field'] ?? null;

        if (is_string($stateField) && trim($stateField) !== '') {
            $select->attr('data-wi-input-country', 'true');
            $select->attr('data-wi-input-state', trim($stateField));
        }

        return $select;
    }

    private static function statesElement(string $name, Input $field): ?Select
    {
        if (!function_exists('states')) {
            return null;
        }

        $context = (array) ($field->get('context') ?? []);
        $country = (string) ($context['country'] ?? '');
        $attribute = (string) ($field->get('attribute') ?? '');

        return self::selectSearchElement($name, $field)
            ->options(self::normalizeOptions($country !== '' ? states($country) : []))
            ->attr('data-wi-input-state', 'true')
            ->attr('data-wi-list-states', $country)
            ->attr('data-wi-input-attribute', $attribute);
    }

    private static function phonePrefixElement(string $name, Input $field): ?Select
    {
        if (!function_exists('phonePrefix')) {
            return null;
        }

        return self::selectSearchElement($name, $field)
            ->options(self::normalizeOptions(phonePrefix()));
    }

    private static function checkboxLikeElement(string $name, Input $field): ElementField
    {
        $options = (array) ($field->get('options') ?? []);

        if ($options === []) {
            return self::checkboxElement($name, $field);
        }

        return self::checkGroupElement($name, $field, 'checkbox');
    }

    private static function checkboxElement(string $name, Input $field): Checkbox
    {
        $checkbox = new Checkbox($name);
        $value = $field->get('value');

        if (
            $value === true
            || $value === 1
            || $value === '1'
            || $value === 'true'
            || $value === 'on'
        ) {
            $checkbox->checked();
        }

        return $checkbox;
    }

    private static function checkGroupElement(string $name, Input $field, string $type): CheckGroup
    {
        $value = $field->get('value');

        if ($type === 'checkbox' && is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        return (new CheckGroup($name))
            ->options(self::normalizeOptions((array) ($field->get('options') ?? [])))
            ->searchBar((bool) ($field->get('search_bar') ?? false))
            ->inputType($type)
            ->value($value);
    }

    /**
     * Costruisce l'`InputPassword` Element e gli copia la password policy
     * letta da `prepare['password_rules']` (settata dai setters fluent su
     * `FormField->minLength()`, `->requireUppercase()`, ecc.).
     *
     * Le stesse regole rimangono in `prepare`/`format` per la validazione
     * server-side eseguita da `formToArray()`.
     */
    private static function passwordElement(string $name, Input $field): InputPassword
    {
        $element = new InputPassword($name);
        $prepare = (array) ($field->get('prepare') ?? []);
        $rules = is_array($prepare['password_rules'] ?? null) ? $prepare['password_rules'] : [];

        if (isset($rules['min_length']) && (int) $rules['min_length'] > 0) {
            $element->minLength((int) $rules['min_length']);
        }

        if (!empty($rules['uppercase'])) {
            $element->requireUppercase();
        }

        if (!empty($rules['lowercase'])) {
            $element->requireLowercase();
        }

        if (!empty($rules['number'])) {
            $element->requireNumber();
        }

        if (!empty($rules['special'])) {
            $element->requireSpecial();
        }

        return $element;
    }

    private static function fileElement(string $name, Input $field): File
    {
        $format = self::fileFormat($field);
        $directory = self::fileDirectory(isset($format['dir']) ? (string) $format['dir'] : null);

        return (new File($name))
            ->file((string) ($field->get('file') ?? 'image'))
            ->uploader((string) ($field->get('uploader') ?? 'classic'))
            ->maxFile((int) ($format['max_file'] ?? 1))
            ->maxSize((int) ($format['max_size'] ?? 5))
            ->directory($directory)
            ->fileValue($field->get('value'))
            ->sizeBefore((bool) ($format['size_before'] ?? false))
            ->minSizeImage(isset($format['min_size_image']) ? (string) $format['min_size_image'] : null);
    }

    private static function repeaterElement(string $name, Input $field): Repeater
    {
        return (new Repeater($name))
            ->columns(is_array($field->get('context')['columns'] ?? null) ? $field->get('context')['columns'] : [])
            ->context((array) ($field->get('context') ?? []))
            ->value($field->get('value'));
    }

    private static function textGeneratorElement(string $name, Input $field): TextGenerator
    {
        $context = (array) ($field->get('context') ?? []);
        $element = new TextGenerator($name);

        if (isset($context['button_label']) && is_string($context['button_label'])) {
            $element->buttonLabel($context['button_label']);
        }

        if (isset($context['callback']) && is_string($context['callback'])) {
            $element->callback($context['callback']);
        }

        return $element;
    }

    private static function checkTreeElement(string $name, Input $field): CheckTree
    {
        $context = (array) ($field->get('context') ?? []);
        $type = (string) ($context['input_type'] ?? 'checkbox');
        $value = $field->get('value');

        if ($type === 'checkbox' && is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        return (new CheckTree($name))
            ->options(self::normalizeOptions((array) ($field->get('options') ?? [])))
            ->searchBar((bool) ($field->get('search_bar') ?? false))
            ->inputType($type)
            ->value($value);
    }

    private static function dynamicCheckElement(string $name, Input $field): DynamicCheck
    {
        $context = (array) ($field->get('context') ?? []);
        $url = (string) ($context['url'] ?? '');
        $type = (string) ($context['input_type'] ?? 'checkbox');

        return (new DynamicCheck($name))
            ->url($url)
            ->inputType($type)
            ->value($field->get('value'));
    }

    private static function checkBooleanElement(string $name, Input $field): CheckBoolean
    {
        $context = (array) ($field->get('context') ?? []);
        $values = is_array($context['boolean_values'] ?? null) ? $context['boolean_values'] : ['', 'true', 'false'];
        $values = array_pad($values, 3, '');

        $element = (new CheckBoolean($name))
            ->values((string) $values[0], (string) $values[1], (string) $values[2])
            ->value($field->get('value'));

        if (isset($context['true_label']) && is_string($context['true_label'])) {
            $element->trueLabel($context['true_label']);
        }

        if (isset($context['false_label']) && is_string($context['false_label'])) {
            $element->falseLabel($context['false_label']);
        }

        return $element;
    }

    private static function acceptDocumentElement(string $name, Input $field): ?InputAcceptDocument
    {
        $document = self::resolveLegalDocument($field);

        if ($document === null) {
            return null;
        }

        $element = (new InputAcceptDocument($name))
            ->documentType((string) ($field->get('context')['document_type'] ?? ''))
            ->documentId((int) ($document->id ?? 0))
            ->documentLabel((string) ($document->renderLabel ?? ''));

        $value = $field->get('value');
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

        return $element;
    }

    private static function resolveLegalDocument(Input $field): ?object
    {
        $context = (array) ($field->get('context') ?? []);
        $type = (string) ($context['document_type'] ?? '');

        if ($type === '') {
            return null;
        }

        if (
            class_exists(\Wonder\Consent\LegalDocumentTypeContext::class)
            && !\Wonder\Consent\LegalDocumentTypeContext::hasType($type)
            && function_exists('__log')
        ) {
            __log(new \Exception('Non esiste il documento '.$type), 'wonder-renderer', 'verify_document_type');
        }

        if (!function_exists('sqlSelect') || !function_exists('infoLegalDocument') || !function_exists('__l')) {
            return null;
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
            if (function_exists('__log')) {
                __log(new \Exception('Non esiste il documento '.$type.' nella lingua '.__l()), 'wonder-renderer', 'verify_document_type_lang');
            }

            return null;
        }

        $document = infoLegalDocument($SQL->id);

        return is_object($document) ? $document : null;
    }

    private static function recaptchaElement(string $name, Input $field): reCAPTCHA
    {
        $context = (array) ($field->get('context') ?? []);

        $element = new reCAPTCHA($name);

        if (isset($context['recaptcha_action']) && is_string($context['recaptcha_action'])) {
            $element->action($context['recaptcha_action']);
        }

        if (isset($context['recaptcha_theme']) && is_string($context['recaptcha_theme'])) {
            $element->theme($context['recaptcha_theme']);
        }

        if (isset($context['recaptcha_size']) && is_string($context['recaptcha_size'])) {
            $element->size($context['recaptcha_size']);
        }

        return $element;
    }

    private static function googleAddressElement(string $name, Input $field): GoogleAddress
    {
        $context = (array) ($field->get('context') ?? []);
        $value = $field->get('value');

        $element = (new GoogleAddress($name))
            ->alias((string) ($context['alias'] ?? $name))
            ->restriction(is_array($context['restriction'] ?? null) ? $context['restriction'] : []);

        # breakdown: priorità a context['breakdown'], fallback al value se è array
        if (is_array($context['breakdown'] ?? null)) {
            $element->breakdown($context['breakdown']);
        } elseif (is_array($value)) {
            $element->breakdown($value);
        }

        return $element;
    }

    private static function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $value => $label) {
            if (is_array($label)) {
                $normalized[$value] = [
                    'name' => (string) ($label['name'] ?? $value),
                    'filter' => is_array($label['filter'] ?? null) ? $label['filter'] : [],
                    'child' => is_array($label['child'] ?? null) ? $label['child'] : [],
                ];
                continue;
            }

            $normalized[$value] = $label;
        }

        return $normalized;
    }

    private static function fileFormat(Input $field): array
    {
        $format = [];
        $prepare = (array) ($field->get('prepare') ?? []);

        $format['max_file'] = isset($prepare['max_file'])
            ? max(1, (int) $prepare['max_file'])
            : ((bool) ($field->get('multiple') ?? false) ? 10 : 1);
        $format['max_size'] = isset($prepare['max_size'])
            ? max(1, (int) $prepare['max_size'])
            : 5;

        if (isset($prepare['dir']) && is_string($prepare['dir'])) {
            $format['dir'] = $prepare['dir'];
        }

        if (!isset($prepare['resize'])) {
            return $format;
        }

        $resize = $prepare['resize'];

        if (is_array($resize) && isset($resize['width'], $resize['height'])) {
            $format['min_size_image'] = $resize['width'].'x'.$resize['height'].'-';
            $format['size_before'] = true;

            return $format;
        }

        if (is_array($resize) && isset($resize[0]['width'], $resize[0]['height'])) {
            $smallest = null;

            foreach ($resize as $size) {
                if (!is_array($size) || !isset($size['width'], $size['height'])) {
                    continue;
                }

                if ($smallest === null || (int) $size['width'] < (int) $smallest['width']) {
                    $smallest = $size;
                }
            }

            if (is_array($smallest)) {
                $format['min_size_image'] = $smallest['width'].'x'.$smallest['height'].'-';
                $format['size_before'] = true;
            }

            return $format;
        }

        $firstResize = is_array($resize) ? reset($resize) : $resize;
        $format['min_size_image'] = $firstResize !== false && $firstResize !== null
            ? (string) $firstResize
            : '';
        $format['size_before'] = false;

        return $format;
    }

    private static function fileDirectory(?string $subDirectory = null): string
    {
        $name = $GLOBALS['NAME'] ?? null;
        $path = $GLOBALS['PATH'] ?? null;

        $folder = is_object($name) ? trim((string) ($name->folder ?? ''), '/') : '';
        $directory = rtrim((string) ($path->upload ?? ''), '/');
        $directory .= $folder !== '' ? '/'.$folder : '';

        $subDirectory = $subDirectory !== null ? trim($subDirectory) : '';

        if ($subDirectory !== '') {
            if ($subDirectory[0] !== '/') {
                $directory .= '/';
            }

            $directory .= ltrim($subDirectory, '/');
        }

        return $directory.'/';
    }

    private static function legacyFolder(): ?string
    {
        $name = $GLOBALS['NAME'] ?? null;

        if (!is_object($name)) {
            return null;
        }

        $folder = trim((string) ($name->folder ?? ''));

        return $folder !== '' ? $folder : null;
    }

    private static function formatNativeDateValue(mixed $value): mixed
    {
        if (!is_scalar($value)) {
            return $value;
        }

        $formatted = self::formatDateString((string) $value, 'Y-m-d');

        return $formatted ?? $value;
    }

    private static function formatDatetimeValue(mixed $value): mixed
    {
        if (!is_scalar($value)) {
            return $value;
        }

        $formatted = self::formatDateString((string) $value, 'Y-m-d\TH:i');

        return $formatted ?? $value;
    }

    private static function formatPickerDateValue(mixed $value): mixed
    {
        if (!is_scalar($value)) {
            return $value;
        }

        $formatted = self::formatDateString((string) $value, 'd/m/Y');

        return $formatted ?? $value;
    }

    private static function normalizeDateRangeValue(Input $field, mixed $value): array
    {
        if (is_array($value)) {
            return self::formatDateRangePair($value);
        }

        $nameFrom = $field->name.'_from';
        $nameTo = $field->name.'_to';
        $from = $GLOBALS['VALUES'][$nameFrom] ?? null;
        $to = $GLOBALS['VALUES'][$nameTo] ?? null;

        return self::formatDateRangePair([$from, $to]);
    }

    private static function formatDateRangePair(array $value): array
    {
        $from = self::formatDateString((string) ($value[0] ?? ''), 'd/m/Y');
        $to = self::formatDateString((string) ($value[1] ?? ''), 'd/m/Y');

        return [
            $from ?? '',
            $to ?? '',
        ];
    }

    private static function formatDateString(string $value, string $format): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date($format, $timestamp);
    }
}
