<?php

namespace Wonder\App\ResourceSchema;

final class RepeaterRelation
{
    public function __construct(
        public string $table,
        public string $parentKey,
        public string $rowKey = 'id',
        public ?string $positionKey = null,
        public bool $softDelete = true,
        public string $deletedColumn = 'deleted',
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
}
