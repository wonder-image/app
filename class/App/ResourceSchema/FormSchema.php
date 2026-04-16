<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Resource;
use Wonder\Elements\Form\Components\InputEmail;
use Wonder\Elements\Form\Components\InputNumber;
use Wonder\Elements\Form\Components\InputPassword;
use Wonder\Elements\Form\Components\InputTel;
use Wonder\Elements\Form\Components\InputText;
use Wonder\Elements\Form\Components\Select;
use Wonder\Elements\Form\Components\Textarea;

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

    public static function text(string $name): InputText
    {
        return new InputText($name);
    }

    public static function email(string $name): InputEmail
    {
        return new InputEmail($name);
    }

    public static function number(string $name): InputNumber
    {
        return new InputNumber($name);
    }

    public static function password(string $name): InputPassword
    {
        return new InputPassword($name);
    }

    public static function tel(string $name): InputTel
    {
        return new InputTel($name);
    }

    public static function textarea(string $name): Textarea
    {
        return new Textarea($name);
    }

    public static function select(string $name, array $options = []): Select
    {
        return (new Select($name))->options($options);
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
