<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Model;
use Wonder\App\Resource;

final class RepeaterRelation
{
    public function __construct(
        public string $table,
        public string $parentKey,
        public string $rowKey = 'id',
        public ?string $positionKey = null,
        public bool $softDelete = true,
        public string $deletedColumn = 'deleted',
        public ?string $schemaName = null,
        public ?string $prepareTable = null,
        public ?string $folder = null,
        public ?string $modelClass = null,
        public ?string $resourceClass = null,
    ) {
    }

    public static function make(string $table, string $parentKey, string $rowKey = 'id'): self
    {
        return new self($table, $parentKey, $rowKey);
    }

    public function positionKey(?string $positionKey): self
    {
        $this->positionKey = $positionKey;

        return $this;
    }

    public function softDelete(bool $softDelete = true, string $deletedColumn = 'deleted'): self
    {
        $this->softDelete = $softDelete;
        $this->deletedColumn = trim($deletedColumn) !== '' ? trim($deletedColumn) : 'deleted';

        return $this;
    }

    public function schema(string $schemaName, ?string $prepareTable = null, ?string $folder = null): self
    {
        $this->schemaName = trim($schemaName);
        $this->prepareTable = $prepareTable !== null ? trim($prepareTable) : $this->prepareTable;
        $this->folder = $folder !== null ? trim($folder, '/') : $this->folder;

        return $this;
    }

    public function folder(?string $folder): self
    {
        $this->folder = $folder !== null ? trim($folder, '/') : null;

        return $this;
    }

    public function model(string $modelClass): self
    {
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            throw new RuntimeException("{$modelClass} deve estendere ".Model::class.'.');
        }

        $this->modelClass = $modelClass;
        $this->prepareTable = $modelClass::$table;
        $this->schemaName = $modelClass::$table;
        $this->folder = trim((string) ($modelClass::$folder ?? ''), '/');

        return $this;
    }

    public function resource(string $resourceClass): self
    {
        if (!class_exists($resourceClass) || !is_subclass_of($resourceClass, Resource::class)) {
            throw new RuntimeException("{$resourceClass} deve estendere ".Resource::class.'.');
        }

        $this->resourceClass = $resourceClass;
        $this->modelClass = $resourceClass::modelClass();
        $this->prepareTable = $resourceClass::modelTable();
        $this->schemaName = $resourceClass::prepareSchemaName();
        $this->folder = trim($resourceClass::legacyFolder(), '/');

        return $this;
    }
}
