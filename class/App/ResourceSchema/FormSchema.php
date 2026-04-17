<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Resource;

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

    public static function input(string $helper, string $name): FormField
    {
        return new FormField(trim($helper), $name);
    }

    public static function text(string $name): FormField
    {
        return self::input('text', $name);
    }

    public static function textGenerator(string $name): FormField
    {
        return self::input('textGenerator', $name);
    }

    public static function textDate(string $name): FormField
    {
        return self::input('textDate', $name);
    }

    public static function textDatetime(string $name): FormField
    {
        return self::input('textDatetime', $name);
    }

    public static function dateInput(string $name): FormField
    {
        return self::input('dateInput', $name);
    }

    public static function dateRange(string $name): FormField
    {
        return self::input('dateRange', $name);
    }

    public static function color(string $name): FormField
    {
        return self::input('color', $name);
    }

    public static function email(string $name): FormField
    {
        return self::input('email', $name);
    }

    public static function number(string $name): FormField
    {
        return self::input('number', $name);
    }

    public static function price(string $name): FormField
    {
        return self::input('price', $name);
    }

    public static function percentige(string $name): FormField
    {
        return self::input('percentige', $name);
    }

    public static function password(string $name): FormField
    {
        return self::input('password', $name);
    }

    public static function tel(string $name): FormField
    {
        return self::input('phone', $name);
    }

    public static function url(string $name): FormField
    {
        return self::input('url', $name);
    }

    public static function textarea(string $name): FormField
    {
        return self::input('textarea', $name);
    }

    public static function select(string $name, array $options = []): FormField
    {
        return self::input('select', $name)->options($options);
    }

    public static function selectSearch(string $name, array $options = []): FormField
    {
        return self::input('selectSearch', $name)->options($options);
    }

    public static function checkbox(string $name): FormField
    {
        return self::input('checkbox', $name);
    }

    public static function inputFile(string $name, string $file = 'image'): FormField
    {
        return self::input('inputFile', $name)->file($file);
    }

    public static function inputFileDragDrop(string $name, string $file = 'image', string $uploader = 'classic'): FormField
    {
        return self::input('inputFileDragDrop', $name)
            ->file($file)
            ->uploader($uploader);
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
