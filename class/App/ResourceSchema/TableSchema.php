<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Resource;
use Wonder\Elements\Table\Column;
use Wonder\Elements\Table\Columns\ColumnBadge;
use Wonder\Elements\Table\Columns\ColumnButton;
use Wonder\Elements\Table\Columns\ColumnIcon;
use Wonder\Elements\Table\Columns\ColumnImage;
use Wonder\Elements\Table\Columns\ColumnText;

final class TableSchema
{
    private array $schema;

    private function __construct(
        private readonly string $resourceClass,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }

        $querySchema = $this->resourceClass::querySchema();
        $order = is_array($querySchema['order'] ?? null) ? $querySchema['order'] : [];

        $this->schema = [
            'title' => true,
            'results' => true,
            'query' => $querySchema,
            'default_order' => [
                'column' => $order['column'] ?? 'creation',
                'direction' => $order['direction'] ?? 'DESC',
            ],
            'columns' => [],
            'filters' => [
                'search' => [],
                'limit' => true,
            ],
        ];
    }

    public static function for(string $resourceClass): self
    {
        return new self($resourceClass);
    }

    public static function text(string $name): ColumnText
    {
        return new ColumnText($name);
    }

    public static function badge(string $name): ColumnBadge
    {
        return new ColumnBadge($name);
    }

    public static function icon(string $name): ColumnIcon
    {
        return new ColumnIcon($name);
    }

    public static function image(string $name): ColumnImage
    {
        return new ColumnImage($name);
    }

    public static function button(string $name = 'menu'): ColumnButton
    {
        return new ColumnButton($name);
    }

    public function title(bool $enabled = true): self
    {
        $this->schema['title'] = $enabled;

        return $this;
    }

    public function results(bool $enabled = true): self
    {
        $this->schema['results'] = $enabled;

        return $this;
    }

    public function order(string $column, string $direction = 'DESC'): self
    {
        $this->schema['default_order'] = [
            'column' => trim($column),
            'direction' => strtoupper(trim($direction)),
        ];

        return $this;
    }

    public function where(string|array|null $condition): self
    {
        $this->schema['query']['condition'] = $condition;

        return $this;
    }

    public function limit(string|int|null $limit): self
    {
        $this->schema['query']['limit'] = $limit;

        return $this;
    }

    public function column(Column|string $column, array $config = []): self
    {
        if ($column instanceof Column) {
            $key = $column->name;
            $config = array_merge($column->toArray(), $config);
        } else {
            $key = trim($column);
        }

        if (!isset($config['label']) || !is_string($config['label']) || trim($config['label']) === '') {
            $label = $this->resourceClass::getLabel($key);

            if (is_string($label) && $label !== '') {
                $config['label'] = $label;
            }
        }

        $this->schema['columns'][$key] = $config;

        return $this;
    }

    public function columns(array $columns): self
    {
        foreach ($columns as $key => $config) {
            if ($config instanceof Column) {
                $this->column($config);
                continue;
            }

            if (is_string($key) && is_array($config)) {
                $this->column($key, $config);
            }
        }

        return $this;
    }

    public function action(string $action, bool $enabled = true): self
    {
        $action = trim($action);

        if ($action === '') {
            return $this;
        }

        $config = $this->buttonColumnConfig();

        if ($enabled) {
            $config['actions'][$action] = true;
        } else {
            unset($config['actions'][$action]);
        }

        $this->schema['columns']['menu'] = $config;

        return $this;
    }

    public function buttons(array $actions, string $size = 'little'): self
    {
        $config = $this->buttonColumnConfig();
        $config['size'] = $size;

        foreach ($actions as $action) {
            if (!is_string($action)) {
                continue;
            }

            $config['actions'][trim($action)] = true;
        }

        $this->schema['columns']['menu'] = $config;

        return $this;
    }

    public function searchable(array $columns): self
    {
        $this->schema['filters']['search'] = array_values(array_unique(array_filter($columns, 'is_string')));

        return $this;
    }

    public function filterLimit(bool $enabled = true): self
    {
        $this->schema['filters']['limit'] = $enabled;

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

    private function buttonColumnConfig(): array
    {
        $config = (array) ($this->schema['columns']['menu'] ?? []);

        if (($config['type'] ?? null) !== 'button') {
            $config = array_merge(
                self::button()->size('little')->toArray(),
                $config
            );
        }

        $config['label'] = '';
        $config['actions'] = (array) ($config['actions'] ?? []);

        return $config;
    }
}
