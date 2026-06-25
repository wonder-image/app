<?php

namespace Wonder\App\PageSchema;

abstract class CustomPageSchema
{
    public static function labelSchema(): array
    {
        return [];
    }

    protected static function applyLabelSchema(array $schema): array
    {
        $labels = static::labelSchema();

        foreach ($schema as $key => $field) {
            if (!is_object($field) || !property_exists($field, 'name')) {
                continue;
            }

            if (trim((string) $field->get('label')) !== '') {
                continue;
            }

            $name = (string) $field->name;

            if (isset($labels[$name]) && trim((string) $labels[$name]) !== '') {
                $field->label((string) $labels[$name]);
            }
        }

        return $schema;
    }
}
