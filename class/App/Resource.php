<?php

namespace Wonder\App;

use ReflectionObject;
use Throwable;
use Wonder\Data\Fields\Field as DataField;
use Wonder\Data\Fields\Number as NumberField;
use Wonder\Data\Fields\Text as TextField;
use Wonder\Data\Formatters\String\LowercaseFormatter;
use Wonder\Data\Formatters\String\SlugFormatter;
use Wonder\Data\Formatters\String\TitleCaseFormatter;
use Wonder\Data\Formatters\String\UppercaseFormatter;
use mysqli;
use RuntimeException;
use Wonder\App\ResourceSchema\ApiSchema as ResourceApiSchema;
use Wonder\App\ResourceSchema\NavigationSchema as ResourceNavigationSchema;
use Wonder\App\ResourceSchema\PageSchema as ResourcePageSchema;
use Wonder\App\ResourceSchema\PermissionSchema as ResourcePermissionSchema;
use Wonder\App\ResourceSchema\TableLayoutSchema as ResourceTableLayoutSchema;
use Wonder\Elements\Form\Form as BackendFormLayout;
use Wonder\Backend\Support\ResourceTableRenderer;
use Wonder\Backend\Table\Table as BackendTable;
use Wonder\Sql\Query;

abstract class Resource
{
    public static string $model = '';

    public static array|string $condition = ['deleted' => 'false'];
    public static string|int|null $limit = null;
    public static string $orderColumn = 'creation';
    public static string $orderDirection = 'DESC';

    public static function modelClass(): string
    {
        $modelClass = trim(static::$model);

        if ($modelClass === '' || !class_exists($modelClass)) {
            throw new RuntimeException('Model non valido per la resource '.static::class.'.');
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            throw new RuntimeException("{$modelClass} deve estendere ".Model::class.'.');
        }

        return $modelClass;
    }

    public static function modelTable(): string
    {
        return static::modelClass()::$table;
    }

    public static function path(): string
    {
        return trim((string) (static::modelClass()::$folder ?? ''), '/');
    }

    public static function legacyFolder(): string
    {
        return static::path();
    }

    public static function slug(): string
    {
        $path = static::path();

        if ($path === '') {
            return '';
        }

        $slug = str_replace(['\\', '/'], '-', $path);
        $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $slug) ?? $slug;

        return trim(strtolower($slug), '-');
    }

    public static function icon(): string
    {
        return trim((string) (static::modelClass()::$icon ?? ''));
    }

    public static function labelSchema(): array
    {
        return [];
    }

    public static function textSchema(): array
    {
        return [];
    }

    public static function formSchema(): array
    {
        return [];
    }

    public static function formLayoutSchema(): ?BackendFormLayout
    {
        return null;
    }

    public static function singletonRecordId(): int|string|null
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [];
    }

    public static function tableLayoutSchema(): ResourceTableLayoutSchema
    {
        return static::tableLayout();
    }

    public static function pageSchema(): ResourcePageSchema
    {
        return static::page();
    }

    public static function apiSchema(): ResourceApiSchema
    {
        return static::api();
    }

    public static function permissionSchema(): ResourcePermissionSchema
    {
        return static::permission();
    }

    public static function navigationSchema(): ResourceNavigationSchema
    {
        return static::navigation();
    }

    public static function querySchema(): array
    {
        return [
            'condition' => static::$condition,
            'limit' => static::$limit,
            'order' => [
                'column' => static::$orderColumn,
                'direction' => static::$orderDirection,
            ],
        ];
    }

    public static function prepareSchemaName(): string
    {
        return 'resource:'.static::slug();
    }

    public static function prepareSchema(): array
    {
        $schema = [];
        $fields = static::modelFields();
        $inputs = static::safeFormFieldsByKey();

        foreach (array_unique(array_merge(array_keys($fields), array_keys($inputs))) as $key) {
            $entry = [];
            $format = array_merge(
                static::prepareFormatFromModelField($fields[$key] ?? null),
                static::prepareFormatFromInput($inputs[$key] ?? null)
            );

            if ($format !== []) {
                $entry['input']['format'] = $format;
            }

            $schema[$key] = $entry;
        }

        return $schema;
    }

    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        return $values;
    }

    public static function mutateFormValues(
        array $values,
        string $mode,
        string $context = 'backend'
    ): array {
        return $values;
    }

    public static function query(): Query
    {
        $modelClass = static::modelClass();

        return $modelClass::query();
    }

    public static function connection(): mysqli
    {
        $modelClass = static::modelClass();

        return $modelClass::connection();
    }

    public static function formFields(): array
    {
        $fields = [];

        foreach (static::flattenFormItems(static::formSchema()) as $item) {
            if (!is_object($item)) {
                continue;
            }

            $fields[] = static::normalizeFormField($item);
        }

        return $fields;
    }

    public static function formFieldsByKey(): array
    {
        $fields = [];

        foreach (static::formFields() as $field) {
            if (!is_object($field) || !property_exists($field, 'name')) {
                continue;
            }

            $fields[(string) $field->name] = clone $field;
        }

        return $fields;
    }

    public static function safeFormFieldsByKey(): array
    {
        try {
            return static::formFieldsByKey();
        } catch (Throwable) {
            return [];
        }
    }

    public static function getInput(string $key): object
    {
        foreach (static::formFields() as $item) {
            if (is_object($item) && property_exists($item, 'name') && $item->name === $key) {
                return clone $item;
            }
        }

        throw new RuntimeException("Input resource non trovato: {$key} in ".static::class.'.');
    }

    public static function getLabel(?string $key = null): string|array
    {
        $labels = static::labelSchema();

        return $key === null ? $labels : ($labels[$key] ?? '');
    }

    public static function getText(?string $key = null): string|array
    {
        $texts = static::textSchema();

        return $key === null ? $texts : ($texts[$key] ?? '');
    }

    public static function getTable(?string $key = null): mixed
    {
        $schema = static::tableSchema();

        if ($key === null) {
            return $schema;
        }

        foreach ($schema as $item) {
            if (is_object($item) && property_exists($item, 'name') && $item->name === $key) {
                return clone $item;
            }
        }

        throw new RuntimeException("Colonna resource non trovata: {$key} in ".static::class.'.');
    }

    public static function getColumn(string $key): object
    {
        return static::getTable($key);
    }

    public static function getPage(?string $key = null): mixed
    {
        $schema = static::pageSchema();

        return $key === null ? $schema : $schema->get($key);
    }

    public static function getApi(?string $key = null): mixed
    {
        $schema = static::apiSchema();

        return $key === null ? $schema : $schema->get($key);
    }

    public static function getPermission(?string $key = null): mixed
    {
        $schema = static::permissionSchema();

        return $key === null ? $schema : $schema->get($key);
    }

    public static function getNavigation(?string $key = null): mixed
    {
        $schema = static::navigationSchema();

        return $key === null ? $schema : $schema->get($key);
    }

    public static function getQuery(?string $key = null): mixed
    {
        $schema = static::querySchema();

        return $key === null ? $schema : ($schema[$key] ?? null);
    }

    public static function page(): ResourcePageSchema
    {
        return ResourcePageSchema::for(static::class);
    }

    public static function tableLayout(): ResourceTableLayoutSchema
    {
        return ResourceTableLayoutSchema::for(static::class);
    }

    public static function api(): ResourceApiSchema
    {
        return ResourceApiSchema::for(static::class);
    }

    public static function navigation(): ResourceNavigationSchema
    {
        return ResourceNavigationSchema::for(static::class);
    }

    public static function permission(): ResourcePermissionSchema
    {
        return ResourcePermissionSchema::for(static::class);
    }

    public static function backendTable(): BackendTable
    {
        return ResourceTableRenderer::make(static::class);
    }

    public static function isSingleton(): bool
    {
        $id = static::singletonRecordId();

        return $id !== null && $id !== '';
    }

    public static function afterStore(object $result, array $values = []): void
    {
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
    }

    public static function afterDelete(int|string $id, object $result): void
    {
    }

    public static function registerBackendRoutes(string $rootApp, string $slug): void
    {
    }

    public static function registerApiRoutes(string $rootApp, string $slug): void
    {
    }

    public static function label(): string
    {
        $label = trim((string) static::getText('label'));

        return $label !== '' ? $label : static::fallbackTitle(static::pathLeaf());
    }

    public static function pluralLabel(): string
    {
        $pluralLabel = trim((string) static::getText('plural_label'));

        if ($pluralLabel !== '') {
            return $pluralLabel;
        }

        return static::label();
    }

    public static function titleLabel(): string
    {
        return static::fallbackTitle(static::label());
    }

    public static function titlePluralLabel(): string
    {
        return static::fallbackTitle(static::pluralLabel());
    }

    public static function defaultPageTitles(): array
    {
        return [
            'list' => 'Lista '.static::pluralLabel(),
            'create' => 'Aggiungi '.static::label(),
            'edit' => 'Modifica '.static::label(),
            'view' => static::titleLabel(),
        ];
    }

    private static function flattenFormItems(array $items): array
    {
        $flattened = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $flattened = array_merge($flattened, static::flattenFormItems($item));
                continue;
            }

            $flattened[] = $item;
        }

        return $flattened;
    }

    private static function normalizeFormField(object $field): object
    {
        $clone = clone $field;

        if (!property_exists($clone, 'name') || !method_exists($clone, 'label')) {
            return $clone;
        }

        $name = trim((string) ($clone->name ?? ''));

        if ($name === '') {
            return $clone;
        }

        $label = method_exists($clone, 'get') ? (string) ($clone->get('label') ?? '') : '';

        if ($label !== '') {
            return $clone;
        }

        $defaultLabel = trim((string) static::getLabel($name));

        if ($defaultLabel === '') {
            $defaultLabel = static::fallbackTitle($name);
        }

        if ($defaultLabel !== '') {
            $clone->label($defaultLabel);
        }

        return $clone;
    }

    private static function modelFields(): array
    {
        $fields = [];

        foreach (static::modelClass()::dataSchema() as $field) {
            if (!$field instanceof DataField) {
                continue;
            }

            $fields[(string) $field->key] = $field;
        }

        return $fields;
    }

    private static function prepareFormatFromModelField(?DataField $field): array
    {
        if ($field === null) {
            return [];
        }

        $format = [];
        $schema = method_exists($field, 'getSchema') ? (array) $field->getSchema() : [];

        if (($schema['unique'] ?? false) === true) {
            $format['unique'] = true;
        }

        if (array_key_exists('sanitize', $schema)) {
            $format['sanitize'] = (bool) $schema['sanitize'];
        }

        if ($field instanceof NumberField) {
            $format['number'] = true;
        }

        if (isset($schema['decimals']) && is_numeric($schema['decimals'])) {
            $format['decimals'] = (int) $schema['decimals'];
        }

        foreach ((array) ($schema['formatters'] ?? []) as $formatter) {
            $class = is_object($formatter) ? $formatter::class : null;

            if ($class === LowercaseFormatter::class) {
                $format['lower'] = true;
            }

            if ($class === UppercaseFormatter::class) {
                $format['upper'] = true;
            }

            if ($class === TitleCaseFormatter::class) {
                $format['ucwords'] = true;
            }

            if ($class === SlugFormatter::class) {
                $format['link'] = true;
            }
        }

        if ($field instanceof TextField
            && ($format['lower'] ?? false)
            && ($format['ucwords'] ?? false)
            && (($format['sanitize'] ?? null) === true)
        ) {
            $format['sanitizeFirst'] = true;
        }

        return $format;
    }

    private static function prepareFormatFromInput(?object $input): array
    {
        if ($input === null || !method_exists($input, 'get')) {
            return [];
        }

        $prepare = (array) ($input->get('prepare') ?? []);
        $helper = '';

        $reflection = new ReflectionObject($input);

        if ($reflection->hasProperty('helper')) {
            $property = $reflection->getProperty('helper');
            $property->setAccessible(true);
            $helper = (string) $property->getValue($input);
        }

        if (in_array($helper, ['inputFile', 'inputFileDragDrop'], true)) {
            $prepare = array_merge([
                'sanitize' => false,
                'file' => true,
                'reset' => true,
                'max_size' => 5,
                'max_file' => (bool) ($input->get('multiple') ?? false) ? 10 : 1,
            ], $prepare);

            $file = (string) ($input->get('file') ?? 'image');
            $extensions = static::fileExtensionsFor($file);

            if ($extensions !== [] && !isset($prepare['extensions'])) {
                $prepare['extensions'] = $extensions;
            }
        }

        return $prepare;
    }

    private static function fileExtensionsFor(string $file): array
    {
        return match (trim(strtolower($file))) {
            'pdf' => ['pdf'],
            'png' => ['png'],
            'jpg', 'jpeg' => ['jpg', 'jpeg'],
            'svg' => ['svg'],
            'webp' => ['webp'],
            default => [],
        };
    }

    private static function fallbackTitle(string $value): string
    {
        $value = trim(str_replace(['-', '_'], ' ', $value));

        if ($value === '') {
            return '';
        }

        return function_exists('mb_convert_case')
            ? mb_convert_case($value, MB_CASE_TITLE, 'UTF-8')
            : ucfirst($value);
    }

    private static function pathLeaf(): string
    {
        $path = str_replace('\\', '/', static::path());
        $path = trim($path, '/');

        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);

        return (string) end($segments);
    }
}
