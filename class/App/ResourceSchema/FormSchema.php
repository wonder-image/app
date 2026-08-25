<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\Inputs\InputCheckBoolean;
use Wonder\App\ResourceSchema\Inputs\InputCheckTree;
use Wonder\App\ResourceSchema\Inputs\InputCheckbox;
use Wonder\App\ResourceSchema\Inputs\InputColor;
use Wonder\App\ResourceSchema\Inputs\InputDate;
use Wonder\App\ResourceSchema\Inputs\InputDateRange;
use Wonder\App\ResourceSchema\Inputs\InputDynamicCheck;
use Wonder\App\ResourceSchema\Inputs\InputEmail;
use Wonder\App\ResourceSchema\Inputs\InputFile;
use Wonder\App\ResourceSchema\Inputs\InputFileDragDrop;
use Wonder\App\ResourceSchema\Inputs\InputGoogleAddress;
use Wonder\App\ResourceSchema\Inputs\InputNumber;
use Wonder\App\ResourceSchema\Inputs\InputPassword;
use Wonder\App\ResourceSchema\Inputs\InputPercentige;
use Wonder\App\ResourceSchema\Inputs\InputPhone;
use Wonder\App\ResourceSchema\Inputs\InputPrice;
use Wonder\App\ResourceSchema\Inputs\InputSelect;
use Wonder\App\ResourceSchema\Inputs\InputSelectSearch;
use Wonder\App\ResourceSchema\Inputs\InputText;
use Wonder\App\ResourceSchema\Inputs\InputTextDate;
use Wonder\App\ResourceSchema\Inputs\InputTextDatetime;
use Wonder\App\ResourceSchema\Inputs\InputTextGenerator;
use Wonder\App\ResourceSchema\Inputs\InputTextarea;
use Wonder\App\ResourceSchema\Inputs\InputUrl;

final class FormSchema
{
    private array $schema;

    private function __construct(
        private readonly string $resourceClass,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }

        $this->schema = [
            'method' => 'POST',
            'enctype' => 'multipart/form-data',
            'fields' => [],
            'sidebar_fields' => [],
            'options' => [],
            'meta' => [],
        ];
    }

    public static function for(string $resourceClass): self
    {
        return new self($resourceClass);
    }

    /**
     * Escape hatch generico: costruisce la facade `FormField` con l'helper
     * dato, per i tipi che non hanno (ancora) una factory dedicata qui.
     *
     * Le factory tipizzate qui sotto sono da preferire: ritornano la classe
     * `Inputs\Input*` del tipo, quindi espongono in autocomplete solo i
     * modificatori che quel tipo supporta davvero.
     */
    public static function input(string $helper, string $name): FormField
    {
        return new FormField($name, $helper);
    }

    public static function text(string $name): InputText
    {
        return new InputText($name);
    }

    public static function textGenerator(string $name): InputTextGenerator
    {
        return new InputTextGenerator($name);
    }

    public static function textDate(string $name): InputTextDate
    {
        return new InputTextDate($name);
    }

    public static function textDatetime(string $name): InputTextDatetime
    {
        return new InputTextDatetime($name);
    }

    public static function dateInput(string $name): InputDate
    {
        return new InputDate($name);
    }

    public static function dateRange(string $name): InputDateRange
    {
        return new InputDateRange($name);
    }

    public static function color(string $name): InputColor
    {
        return new InputColor($name);
    }

    public static function email(string $name): InputEmail
    {
        return new InputEmail($name);
    }

    public static function number(string $name): InputNumber
    {
        return new InputNumber($name);
    }

    public static function price(string $name): InputPrice
    {
        return new InputPrice($name);
    }

    public static function percentige(string $name): InputPercentige
    {
        return new InputPercentige($name);
    }

    public static function password(string $name): InputPassword
    {
        return new InputPassword($name);
    }

    public static function tel(string $name): InputPhone
    {
        return new InputPhone($name);
    }

    public static function url(string $name): InputUrl
    {
        return new InputUrl($name);
    }

    public static function textarea(string $name): InputTextarea
    {
        return new InputTextarea($name);
    }

    public static function select(string $name, array $options = []): InputSelect
    {
        return (new InputSelect($name))->options($options);
    }

    public static function selectSearch(string $name, array $options = []): InputSelectSearch
    {
        return (new InputSelectSearch($name))->options($options);
    }

    public static function checkbox(string $name): InputCheckbox
    {
        return new InputCheckbox($name);
    }

    public static function inputFile(string $name, string $file = 'image'): InputFile
    {
        return (new InputFile($name))->accept($file);
    }

    public static function inputFileDragDrop(string $name, string $file = 'image', string $uploader = 'classic'): InputFileDragDrop
    {
        return (new InputFileDragDrop($name))
            ->accept($file)
            ->uploader($uploader);
    }

    public static function checkTree(string $name, array $options = []): InputCheckTree
    {
        return (new InputCheckTree($name))->options($options);
    }

    public static function dynamicCheck(string $name, string $url): InputDynamicCheck
    {
        return (new InputDynamicCheck($name))
            ->url($url)
            ->inputType('checkbox');
    }

    public static function checkBoolean(string $name): InputCheckBoolean
    {
        return new InputCheckBoolean($name);
    }

    public static function googleAddress(string $name): InputGoogleAddress
    {
        return new InputGoogleAddress($name);
    }

    public function method(string $method): self
    {
        $this->schema['method'] = strtoupper(trim($method));

        return $this;
    }

    public function enctype(string $enctype): self
    {
        $this->schema['enctype'] = trim($enctype);

        return $this;
    }

    public function field(object $field, string $slot = 'fields', bool $autoLabel = true): self
    {
        $slot = $this->normalizeSlot($slot);

        if ($autoLabel) {
            $field = $this->applyLabel($field);
        }

        $this->schema[$slot][] = $field;

        return $this;
    }

    public function fields(array $fields, string $slot = 'fields', bool $autoLabel = true): self
    {
        foreach ($fields as $field) {
            if (!is_object($field)) {
                continue;
            }

            $this->field($field, $slot, $autoLabel);
        }

        return $this;
    }

    public function sidebarField(object $field, bool $autoLabel = true): self
    {
        return $this->field($field, 'sidebar_fields', $autoLabel);
    }

    public function sidebarFields(array $fields, bool $autoLabel = true): self
    {
        return $this->fields($fields, 'sidebar_fields', $autoLabel);
    }

    public function option(string $field, array $values): self
    {
        $this->schema['options'][trim($field)] = $values;

        return $this;
    }

    public function options(array $options): self
    {
        foreach ($options as $field => $values) {
            if (!is_string($field) || !is_array($values)) {
                continue;
            }

            $this->option($field, $values);
        }

        return $this;
    }

    public function meta(string $key, mixed $value): self
    {
        $this->schema['meta'][trim($key)] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->all();
    }

    public function get(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->schema;
        }

        return $this->schema[$key] ?? null;
    }

    public function all(): array
    {
        return $this->schema;
    }

    private function applyLabel(object $field): object
    {
        if (!property_exists($field, 'name')) {
            return $field;
        }

        $name = trim((string) ($field->name ?? ''));

        if ($name === '') {
            return $field;
        }

        $label = $this->resourceClass::getLabel($name);

        if ($label === '' || !method_exists($field, 'label')) {
            return $field;
        }

        $schema = method_exists($field, 'getSchema') ? $field->getSchema() : [];

        if (!is_array($schema) || trim((string) ($schema['label'] ?? '')) === '') {
            $field->label((string) $label);
        }

        return $field;
    }

    private function normalizeSlot(string $slot): string
    {
        $slot = trim($slot);

        return match ($slot) {
            'sidebar', 'sidebar_fields' => 'sidebar_fields',
            default => 'fields',
        };
    }
}
