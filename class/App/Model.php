<?php

namespace Wonder\App;

use Exception;
use mysqli;
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

    protected static string|array|null $defaultCondition = null;

    public static Connection $connection;

    abstract public static function tableSchema(): array;
    abstract public static function dataSchema(): array;

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

    public static function createTable(): void
    {
        $database = new CreateTable(static::connection());
        $database->Table(static::$table, static::getColumns());
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
        return static::query()->Select(
            static::$table,
            static::$defaultCondition,
            null,
            null,
            null,
            $columns
        )->row;
    }

    public static function find(
        string|array|null $condition = null,
        string|int|null $limit = null,
        ?string $order = null,
        ?string $orderDirection = null,
        string|array $columns = '*'
    ): mixed {
        return static::query()->Select(
            static::$table,
            $condition ?? static::$defaultCondition,
            $limit,
            $order,
            $orderDirection,
            $columns
        )->row;
    }

    public static function findById(int|string $id): mixed
    {
        return static::query()->Select(
            static::$table,
            ['id' => $id],
            1
        )->row;
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

    public static function createUpdate(array $values, int|string|null $id = null): object
    {
        if ($id === null || $id === '') {
            return static::create($values);
        }

        return static::update($values, $id);
    }
}
