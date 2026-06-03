<?php

namespace Wonder\App;

use Exception;
use mysqli;
use Wonder\App\Path;
use Wonder\App\Support\MediaFileManager;
use Wonder\Data\Fields\Field as DataField;
use Wonder\Data\Fields\Number as NumberField;
use Wonder\Data\Fields\Text as TextField;
use Wonder\Data\Formatters\String\LowercaseFormatter;
use Wonder\Data\Formatters\String\SlugFormatter;
use Wonder\Data\Formatters\String\TitleCaseFormatter;
use Wonder\Data\Formatters\String\UppercaseFormatter;
use Wonder\Sql\TableSchema as SqlColumn;
use Wonder\Sql\{Connection, CreateTable, Query};

abstract class Model
{
    public static string $table = '';
    public static string $folder = '';
    public static string $icon = 'bi bi-circle';

    protected static ?string $dbHostname = null;
    protected static ?string $dbUsername = null;
    protected static ?string $dbPassword = null;
    protected static ?string $dbName = null;

    protected static string|array|null $defaultCondition = ['deleted' => 'false'];

    public static Connection $connection;

    abstract public static function tableSchema(): array;
    abstract public static function dataSchema(): array;

    public static function tableOptions(): array
    {
        return [];
    }

    public static function tablePseudos(): array
    {
        return [];
    }

    public static function query(): Query
    {
        return new Query(static::connection());
    }

    public static function setConnection(): void
    {
        static::$connection = new Connection(
            static::$dbHostname ?? null,
            static::$dbUsername ?? null,
            static::$dbPassword ?? null,
            static::$dbName ?? null
        );
    }

    public static function connection(): mysqli
    {
        if (!isset(static::$connection)) {
            static::setConnection();
        }

        return static::$connection->Connect();
    }

    public static function getColumns(): array
    {
        $columns = [];

        foreach (static::tableSchema() as $column) {
            if (!isset($column->name, $column->schema)) {
                continue;
            }

            $columns[$column->name] = $column->schema;
        }

        return $columns;
    }

    public static function dataFields(): array
    {
        $fields = [];

        foreach (static::dataSchema() as $field) {
            if (!$field instanceof DataField) {
                continue;
            }

            $fields[(string) $field->key] = $field;
        }

        return $fields;
    }

    public static function sqlColumnFromField(?DataField $field): ?SqlColumn
    {
        if ($field === null || !isset($field->key) || trim((string) $field->key) === '') {
            return null;
        }

        $column = SqlColumn::key((string) $field->key);
        $sqlSchema = method_exists($field, 'sqlSchema')
            ? (array) $field->sqlSchema()
            : [];
        $fieldSchema = method_exists($field, 'getSchema')
            ? (array) $field->getSchema()
            : [];

        if (isset($sqlSchema['type']) && is_string($sqlSchema['type']) && trim($sqlSchema['type']) !== '') {
            $column->type(trim($sqlSchema['type']));
        }

        if (isset($sqlSchema['length'])) {
            $column->schema('length', $sqlSchema['length']);
        }

        if (array_key_exists('null', $sqlSchema)) {
            $column->null((bool) $sqlSchema['null']);
        }

        if (isset($sqlSchema['default']) && is_string($sqlSchema['default'])) {
            $column->default($sqlSchema['default']);
        }

        if (isset($sqlSchema['enum']) && is_array($sqlSchema['enum'])) {
            $column->enum($sqlSchema['enum']);
        }

        if (isset($sqlSchema['index'])) {
            $column->index($sqlSchema['index']);
        }

        if (isset($sqlSchema['primary'])) {
            $column->primary($sqlSchema['primary']);
        }

        if (isset($sqlSchema['unique'])) {
            $column->unique($sqlSchema['unique']);
        } elseif (($fieldSchema['unique'] ?? false) === true) {
            $column->unique();
        }

        if (isset($sqlSchema['foreign_table']) && is_string($sqlSchema['foreign_table']) && trim($sqlSchema['foreign_table']) !== '') {
            $column->foreign(
                trim($sqlSchema['foreign_table']),
                (string) ($sqlSchema['foreign_key'] ?? 'id')
            );
        }

        if (isset($sqlSchema['foreign_on_update']) && is_string($sqlSchema['foreign_on_update'])) {
            $column->foreignOnUpdate($sqlSchema['foreign_on_update']);
        }

        if (isset($sqlSchema['foreign_on_delete']) && is_string($sqlSchema['foreign_on_delete'])) {
            $column->foreignOnDelete($sqlSchema['foreign_on_delete']);
        }

        if (isset($sqlSchema['after']) && is_string($sqlSchema['after'])) {
            $column->schema('after', $sqlSchema['after']);
        }

        if (isset($sqlSchema['on_update']) && is_string($sqlSchema['on_update'])) {
            $column->schema('on_update', $sqlSchema['on_update']);
        }

        if (($sqlSchema['auto_increment'] ?? false) === true) {
            $column->schema('auto_increment', true);
        }

        return $column;
    }

    public static function sqlColumnsFromDataSchema(array|string|null $only = null): array
    {
        $fields = static::dataFields();

        if (is_string($only)) {
            $only = [$only];
        }

        if (is_array($only)) {
            $only = array_values(array_filter(array_map(
                static fn ($key) => is_string($key) ? trim($key) : '',
                $only
            )));
            $fields = array_intersect_key($fields, array_flip($only));
        }

        $columns = [];

        foreach ($fields as $field) {
            $column = static::sqlColumnFromField($field);

            if ($column !== null) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    public static function prepareFormatFromField(?DataField $field): array
    {
        if ($field === null) {
            return [];
        }

        $format = method_exists($field, 'defaultInputFormat')
            ? (array) $field->defaultInputFormat()
            : [];
        $schema = method_exists($field, 'getSchema') ? (array) $field->getSchema() : [];

        if (($schema['unique'] ?? false) === true) {
            $format['unique'] = true;
        }

        if (($schema['link_unique'] ?? false) === true) {
            $format['link_unique'] = true;
        }

        if (isset($schema['unique_code']) && is_array($schema['unique_code'])) {
            $format['unique_code'] = $schema['unique_code'];
        }

        if (array_key_exists('sanitize', $schema)) {
            $format['sanitize'] = (bool) $schema['sanitize'];
        }

        if (($schema['immutable_on_update'] ?? false) === true) {
            $format['immutable_on_update'] = true;
        }

        if (($schema['json'] ?? false) === true) {
            $format['json'] = true;
            $format['sanitize'] = $format['sanitize'] ?? false;
        }

        if (($schema['html_to_text'] ?? false) === true) {
            $format['html_to_text'] = true;
        }

        if (($schema['file_to_array'] ?? false) === true) {
            $format['file_to_array'] = true;
        }

        if (($schema['file'] ?? false) === true) {
            $format['file'] = true;
            $format['sanitize'] = $format['sanitize'] ?? false;
        }

        if (isset($schema['extensions']) && is_array($schema['extensions']) && $schema['extensions'] !== []) {
            $format['extensions'] = array_values($schema['extensions']);
        }

        if (isset($schema['max_size']) && is_numeric($schema['max_size'])) {
            $format['max_size'] = (int) $schema['max_size'];
        } elseif (isset($schema['max-size']) && is_numeric($schema['max-size'])) {
            $format['max_size'] = (int) ceil(((int) $schema['max-size']) / 1048576);
        }

        if (isset($schema['max_file']) && is_numeric($schema['max_file'])) {
            $format['max_file'] = (int) $schema['max_file'];
        } elseif (isset($schema['max-file']) && is_numeric($schema['max-file'])) {
            $format['max_file'] = (int) $schema['max-file'];
        }

        if (isset($schema['dir']) && is_string($schema['dir']) && trim($schema['dir']) !== '') {
            $format['dir'] = trim($schema['dir']);
        } elseif (isset($schema['path']) && is_string($schema['path']) && trim($schema['path']) !== '') {
            $format['dir'] = trim($schema['path']);
        }

        if (array_key_exists('reset', $schema)) {
            $format['reset'] = (bool) $schema['reset'];
        }

        if (isset($schema['resize']) && is_array($schema['resize']) && $schema['resize'] !== []) {
            $format['resize'] = $schema['resize'];
        }

        if (isset($schema['name']) && is_string($schema['name']) && trim($schema['name']) !== '') {
            $format['name'] = trim($schema['name']);
        }

        if (array_key_exists('webp', $schema)) {
            $format['webp'] = (bool) $schema['webp'];
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
                if (!isset($format['link_unique']) || $format['link_unique'] !== true) {
                    $format['link'] = true;
                }
            }
        }

        if ($field instanceof TextField
            && ($format['lower'] ?? false)
            && ($format['ucwords'] ?? false)
            && (($format['sanitize'] ?? null) === true)
        ) {
            $format['sanitizeFirst'] = true;
        }

        if (($format['file'] ?? false) === true && static::isResponsiveImageFormat($field, $schema, $format)) {
            if (!isset($format['resize']) && defined('RESPONSIVE_IMAGE_SIZES')) {
                $format['resize'] = RESPONSIVE_IMAGE_SIZES;
            }

            if (!array_key_exists('webp', $format) && defined('RESPONSIVE_IMAGE_WEBP')) {
                $format['webp'] = (bool) RESPONSIVE_IMAGE_WEBP;
            }
        }

        return $format;
    }

    protected static function isResponsiveImageFormat(?DataField $field, array $schema, array $format): bool
    {
        if ($field !== null && strtolower((string) ($field->type ?? '')) === 'image') {
            return true;
        }

        $extensions = array_map(
            static fn ($extension) => strtolower(trim((string) $extension)),
            (array) ($format['extensions'] ?? [])
        );

        if ($extensions !== []) {
            $imageExtensions = ['png', 'jpg', 'jpeg', 'webp'];

            foreach ($extensions as $extension) {
                if (in_array($extension, $imageExtensions, true)) {
                    return true;
                }
            }
        }

        $mimeType = $schema['mime_type'] ?? $schema['mime-type'] ?? null;

        return ($mimeType !== null)
            && str_starts_with(strtolower((string) $mimeType), 'image/');
    }

    public static function runtimeInputSchemaFromDataSchema(): array
    {
        $schema = [];

        foreach (static::dataFields() as $key => $field) {
            $format = static::prepareFormatFromField($field);

            if ($format === []) {
                continue;
            }

            $schema[$key] = [
                'input' => [
                    'format' => $format,
                ],
            ];
        }

        return $schema;
    }

    public static function legacyTableSchema(): array
    {
        $schema = [];

        foreach (static::getColumns() as $name => $column) {
            $schema[$name] = [
                'sql' => $column,
            ];
        }

        return array_replace_recursive($schema, static::runtimeInputSchemaFromDataSchema());
    }

    public static function rawTableSchema(): array
    {
        $schema = static::legacyTableSchema();
        $tableOptions = static::tableOptions();

        if ($tableOptions !== []) {
            $schema['__table'] = [
                'sql' => $tableOptions,
            ];
        }

        foreach (static::tablePseudos() as $name => $pseudoSchema) {
            if (!is_string($name) || trim($name) === '' || !is_array($pseudoSchema)) {
                continue;
            }

            $schema[$name] = [
                'sql' => $pseudoSchema,
            ];
        }

        return $schema;
    }

    public static function createTable(): void
    {
        $schema = [];

        foreach (static::rawTableSchema() as $name => $definition) {
            $schema[$name] = is_array($definition) ? (array) ($definition['sql'] ?? []) : [];
        }

        $database = new CreateTable(static::connection());
        $database->Table(static::$table, $schema);
    }

    public static function arrayValues(array $values, string $prefix = ''): array
    {
        $schema = static::dataSchema();
        $columns = static::getColumns();
        $valuesArray = [];

        foreach ($schema as $field) {
            if (!isset($field->key) || !array_key_exists($field->key, $columns)) {
                throw new Exception(
                    'La colonna '.($field->key ?? '[sconosciuta]').' non fa parte delle colonne del Database.'
                );
            }

            $key = $prefix.$field->key;

            if ($field->isRequired()) {
                $valuesArray[$key] = [
                    'class' => $field,
                    'value' => $values[$key] ?? null,
                ];
                continue;
            }

            if (array_key_exists($key, $values)) {
                $valuesArray[$key] = [
                    'class' => $field,
                    'value' => $values[$key],
                ];
            }
        }

        return $valuesArray;
    }

    public static function validate(array $values, string $prefix = ''): object
    {
        $validatedValues = (object) [
            'valid' => true,
            'response' => [],
        ];

        foreach (static::arrayValues($values, $prefix) as $key => $value) {
            $field = $value['class'];
            $validatedValues->response[$key] = $field->validate($value['value'], $values);

            if (!$validatedValues->response[$key]->isValid()) {
                $validatedValues->valid = false;
            }
        }

        return $validatedValues;
    }

    public static function prepare(array $values, string $prefix = ''): array
    {
        $preparedValues = [];

        foreach (static::arrayValues($values, $prefix) as $key => $value) {
            $preparedValues[str_replace($prefix, '', $key)] = $value['class']->format($value['value']);
        }

        return $preparedValues;
    }

    public static function all(string|array $columns = '*'): array
    {
        $rows = static::query()->Select(
            static::$table,
            static::queryCondition(),
            null,
            null,
            null,
            $columns
        )->row;

        return (array) static::decorateRows($rows);
    }

    public static function find(
        string|array|null $condition = null,
        string|int|null $limit = null,
        ?string $order = null,
        ?string $orderDirection = null,
        string|array $columns = '*'
    ): mixed {
        $rows = static::query()->Select(
            static::$table,
            static::queryCondition($condition),
            $limit,
            $order,
            $orderDirection,
            $columns
        )->row;

        return static::decorateRows($rows);
    }

    public static function findById(int|string $id): mixed
    {
        $row = static::query()->Select(
            static::$table,
            static::queryCondition(['id' => $id]),
            1
        )->row;

        return static::decorateRows($row);
    }

    /**
     * Hook opzionale: ogni Model può override per arricchire/rielaborare
     * una riga prima che venga restituita da `all()`, `find()`, `findById()`.
     *
     * Default = identity. Esempi d'uso tipici:
     *
     *   - aggiungere URL backend computati (vedi
     *     `Wonder\App\Models\Consent\ConsentEvent::decorate`);
     *   - decodificare JSON colonne in array;
     *   - calcolare campi derivati (full_name, age, ecc.);
     *   - normalizzare booleani persistiti come stringa.
     *
     * Lavora **per singola riga**. Il dispatch su collection vs riga
     * singola è gestito da `decorateRows()`.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function decorate(array $row): array
    {
        return $row;
    }

    /**
     * Applica `decorate()` al risultato di una query Select.
     *
     * Il `Wonder\Sql\Query::Select()->row` ritorna shape diverse in base
     * a `limit`: con `limit=1` o `Where id=X` è una riga assoc, altrimenti
     * un array di righe. `decorateRows()` discrimina con `isAssoc()`.
     *
     * - input vuoto / non array → passthrough (nessuna chiamata a decorate)
     * - riga assoc → ritorna `decorate($row)`
     * - lista di righe → array_map con `decorate($row)` su ogni elemento;
     *   elementi non-array (raro) vengono passthrough
     */
    protected static function decorateRows(mixed $rows): mixed
    {
        if (!is_array($rows) || $rows === []) {
            return $rows;
        }

        if (static::isAssoc($rows)) {
            return static::decorate($rows);
        }

        return array_map(
            static fn (mixed $row): mixed => is_array($row) ? static::decorate($row) : $row,
            $rows
        );
    }

    public static function create(array $values): object
    {
        $validated = static::validate($values);

        if (!$validated->valid) {
            return (object) array_merge(['success' => false], (array) $validated);
        }

        return static::query()->Insert(
            static::$table,
            static::prepare($values)
        );
    }

    public static function update(array $values, int|string $id): object
    {
        $validated = static::validate($values);

        if (!$validated->valid) {
            return (object) array_merge(['success' => false], (array) $validated);
        }

        return static::query()->Update(
            static::$table,
            static::prepare($values),
            'id',
            $id
        );
    }

    public static function delete(int|string $id): object
    {
        $deleted = (bool) static::query()->Delete(
            static::$table,
            ['id' => $id]
        );

        return (object) [
            'success' => $deleted,
            'table' => static::$table,
            'id' => $id,
        ];
    }

    public static function getAll(string|array $column = '*'): array
    {
        return static::all($column);
    }

    public static function get(
        string|array|null $condition = null,
        string|int|null $limit = null,
        ?string $order = null,
        ?string $orderDirection = null,
        string|array $column = '*'
    ): mixed {
        return static::find($condition, $limit, $order, $orderDirection, $column);
    }

    public static function getById(int|string $id): mixed
    {
        return static::findById($id);
    }

    public static function safeAll(string|array $columns = '*'): array
    {
        return static::normalizeApiResult(static::all($columns));
    }

    public static function safeFind(
        string|array|null $condition = null,
        string|int|null $limit = null,
        ?string $order = null,
        ?string $orderDirection = null,
        string|array $columns = '*'
    ): mixed {
        return static::normalizeApiResult(
            static::find($condition, $limit, $order, $orderDirection, $columns)
        );
    }

    public static function safeFindById(int|string $id): ?array
    {
        $row = static::findById($id);

        return is_array($row) && $row !== []
            ? static::normalizeApiRow($row)
            : null;
    }

    public static function createUpdate(array $values, int|string|null $id = null): object
    {
        if ($id === null || $id === '') {
            return static::create($values);
        }

        return static::update($values, $id);
    }

    protected static function queryCondition(string|array|null $condition = null): string|array|null
    {
        $defaultCondition = static::softDeleteDefaultCondition();

        if ($defaultCondition === null) {
            return $condition;
        }

        if ($condition === null) {
            return $defaultCondition;
        }

        if (is_array($condition)) {
            foreach ($condition as $key => $_value) {
                if (trim((string) $key, "` \t\n\r\0\x0B") === 'deleted') {
                    return $condition;
                }
            }

            return array_merge($defaultCondition, $condition);
        }

        $condition = trim($condition);

        if ($condition === '') {
            return $defaultCondition;
        }

        if (preg_match('/\bdeleted\b/i', $condition) === 1) {
            return $condition;
        }

        return '('.$condition.') AND '.static::arrayConditionToSql($defaultCondition);
    }

    protected static function softDeleteDefaultCondition(): ?array
    {
        if (!is_array(static::$defaultCondition) || static::$defaultCondition === []) {
            return null;
        }

        return static::supportsSoftDelete()
            ? static::$defaultCondition
            : null;
    }

    protected static function supportsSoftDelete(): bool
    {
        if (array_key_exists('deleted', static::getColumns())) {
            return true;
        }

        $tableOptions = static::tableOptions();

        return (($tableOptions['audit_columns'] ?? true) === true)
            && (($tableOptions['audit_auto_columns'] ?? true) === true);
    }

    protected static function arrayConditionToSql(array $condition): string
    {
        $filters = [];

        foreach ($condition as $column => $value) {
            $column = trim((string) $column, "` \t\n\r\0\x0B");

            if ($column === '') {
                continue;
            }

            if ($value === null) {
                $filters[] = "`{$column}` IS NULL";
                continue;
            }

            $value = addslashes((string) $value);
            $filters[] = "`{$column}` = '{$value}'";
        }

        return implode(' AND ', $filters);
    }

    protected static function normalizeApiResult(mixed $result): mixed
    {
        if (!is_array($result) || $result === []) {
            return $result;
        }

        if (static::isAssoc($result)) {
            return static::normalizeApiRow($result);
        }

        return array_map(
            static fn (mixed $row): mixed => is_array($row) ? static::normalizeApiRow($row) : $row,
            $result
        );
    }

    protected static function normalizeApiRow(array $row): array
    {
        $fields = static::dataFields();
        $columns = static::getColumns();
        $api = [];

        foreach ($row as $key => $value) {
            $field = $fields[$key] ?? null;
            $schema = ($field !== null && method_exists($field, 'getSchema'))
                ? (array) $field->getSchema()
                : [];
            $columnSchema = is_array($columns[$key] ?? null) ? $columns[$key] : [];

            if (is_string($value)) {
                $trimmed = trim($value);

                if ($trimmed === 'true' || $trimmed === 'false') {
                    $row[$key] = filter_var($trimmed, FILTER_VALIDATE_BOOLEAN);
                    continue;
                }

                $columnType = strtoupper((string) ($columnSchema['type'] ?? ''));

                if ($columnType === 'BOOL' && ($trimmed === '0' || $trimmed === '1')) {
                    $row[$key] = $trimmed === '1';
                    continue;
                }
            }

            if (($schema['json'] ?? false) === true) {
                $decoded = is_string($value) && trim($value) !== ''
                    ? json_decode($value, true)
                    : [];

                $row[$key] = is_array($decoded) ? $decoded : [];
                continue;
            }

            if (($schema['file'] ?? false) === true || in_array((string) ($field->type ?? ''), ['file', 'image'], true)) {
                $storedFiles = MediaFileManager::decodeStoredFiles($value);
                $urls = static::storedFileUrls($storedFiles, $schema);

                $row[$key] = $storedFiles;
                $api = array_merge($api, static::fileApiPayload($key, $urls));
            }
        }

        if ($api !== []) {
            $row['api'] = $api;
        }

        return $row;
    }

    protected static function storedFileUrls(array $storedFiles, array $schema): array
    {
        $urls = [];

        foreach ($storedFiles as $storedFile) {
            $url = static::storedFileUrl($storedFile, $schema);

            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return array_values($urls);
    }

    protected static function storedFileUrl(string $storedFile, array $schema): ?string
    {
        $storedFile = trim($storedFile);

        if ($storedFile === '') {
            return null;
        }

        if (filter_var($storedFile, FILTER_VALIDATE_URL) !== false) {
            return $storedFile;
        }

        $dir = trim((string) ($schema['dir'] ?? '/'));

        if (str_contains($dir, '..')) {
            return null;
        }

        if ($dir !== '' && substr($dir, -1) !== '/') {
            $dir = trim((string) dirname($dir), '.\\/');
        } else {
            $dir = trim($dir, '/');
        }

        $segments = [rtrim((new Path())->upload, '/')];
        $folder = trim(static::$folder, '/');

        if ($folder !== '') {
            $segments[] = $folder;
        }

        if ($dir !== '') {
            $segments[] = $dir;
        }

        $segments[] = ltrim($storedFile, '/');

        return implode('/', $segments);
    }

    protected static function fileApiPayload(string $key, array $urls): array
    {
        $pluralKey = preg_match('/s$/', $key) === 1
            ? static::camelCase($key).'Url'
            : static::camelCase($key).'Urls';
        $singularSourceKey = preg_replace('/s$/', '', $key) ?: $key;
        $singularKey = static::camelCase($singularSourceKey).'Url';

        return [
            $pluralKey => $urls,
            $singularKey => $urls[0] ?? null,
        ];
    }

    protected static function camelCase(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', trim($value));
        $value = ucwords($value);
        $value = str_replace(' ', '', $value);

        return lcfirst($value);
    }

    protected static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
