<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\Backend\Table\Table;

final class ResourceTableRenderer
{
    private array $tableSchema;
    private array $tableLayoutSchema;
    private array $pageSchema;
    private array $querySchema;
    private string $modelClass;
    private string $slug;

    private function __construct(
        private readonly string $resourceClass,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }

        $this->tableSchema = $this->normalizeColumns($this->resourceClass::tableSchema());
        $this->tableLayoutSchema = $this->resourceClass::tableLayoutSchema()->all();
        $this->pageSchema = $this->resourceClass::pageSchema()->all();
        $this->querySchema = $this->resourceClass::querySchema();
        $this->modelClass = $this->resourceClass::modelClass();
        $this->slug = $this->resourceClass::slug();
    }

    public static function make(string $resourceClass): Table
    {
        return (new self($resourceClass))->toTable();
    }

    public static function render(string $resourceClass): string
    {
        return self::make($resourceClass)->generate();
    }

    private function toTable(): Table
    {
        $table = new Table($this->resourceClass::modelTable(), $this->resourceClass::connection());

        $table->labels($this->resourceClass::labelSchema());
        $table->text(
            (string) ($this->resourceClass::getText('label') ?: 'elemento'),
            (string) ($this->resourceClass::getText('plural_label') ?: 'elementi'),
            (string) ($this->resourceClass::getText('last') ?: 'ultimi'),
            (string) ($this->resourceClass::getText('all') ?: 'tutti'),
            (string) ($this->resourceClass::getText('article') ?: 'gli'),
            (string) ($this->resourceClass::getText('full') ?: 'pieno'),
            (string) ($this->resourceClass::getText('empty') ?: 'vuoto'),
            (string) ($this->resourceClass::getText('this') ?: 'questo'),
        );

        $title = (array) ($this->tableLayoutSchema['title'] ?? []);
        $table->title((bool) ($title['enabled'] ?? true));
        $table->titleNResult((bool) ($this->tableLayoutSchema['results'] ?? true));

        if (is_string($title['text'] ?? null) && trim((string) $title['text']) !== '') {
            $table->titleValue = (string) $title['text'];
        }

        $this->applyLinks($table);
        $this->applyQuery($table);
        $this->applyFilters($table);
        $this->applyButtonAdd($table);
        $this->applyColumns($table);

        return $table;
    }

    private function applyLinks(Table $table): void
    {
        $links = [
            'view' => $this->routePattern('view', true),
            'modify' => $this->routePattern('edit', true),
            'download' => $this->routePattern('download', true),
            'duplicate' => $this->routePattern('duplicate', true),
        ];

        foreach ($links as $key => $link) {
            if ($link !== '') {
                $table->addLink($key, $link);
            }
        }
    }

    private function applyQuery(Table $table): void
    {
        $condition = $this->querySchema['condition'] ?? null;

        if ($condition !== null) {
            $table->query($condition);
        }

        $defaultOrder = (array) ($this->tableSchema['default_order'] ?? []);
        $orderColumn = (string) ($defaultOrder['column'] ?? $this->querySchema['order']['column'] ?? 'creation');
        $orderDirection = (string) ($defaultOrder['direction'] ?? $this->querySchema['order']['direction'] ?? 'DESC');

        $table->queryOrder($orderColumn, $orderDirection);

        $limitEnabled = (bool) (($this->tableSchema['filters']['limit'] ?? true) === true);
        if ($limitEnabled && isset($this->querySchema['limit']) && is_numeric($this->querySchema['limit'])) {
            $table->length((int) $this->querySchema['limit']);
        }
    }

    private function applyFilters(Table $table): void
    {
        $searchFields = $this->tableLayoutSearchFields();
        $searchEnabled = (bool) ($this->tableLayoutSchema['filters']['search']['enabled'] ?? true);
        if ($searchEnabled && $searchFields !== []) {
            $table->filterSearch(true, $searchFields);
        }

        $table->filterLimit((bool) ($this->tableLayoutSchema['filters']['limit']['enabled'] ?? true));

        foreach ((array) ($this->tableLayoutSchema['custom_filters'] ?? []) as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $label = trim((string) ($filter['label'] ?? ''));
            $column = trim((string) ($filter['column'] ?? ''));
            $options = (array) ($filter['array'] ?? []);

            if ($label === '' || $column === '' || $options === []) {
                continue;
            }

            $table->addFilter(
                $label,
                $column,
                $options,
                (string) ($filter['input'] ?? 'select'),
                (bool) ($filter['search'] ?? false),
                $filter['column_type'] ?? null,
                $filter['value'] ?? null
            );
        }
    }

    private function applyButtonAdd(Table $table): void
    {
        $buttonAdd = (array) ($this->tableLayoutSchema['button_add'] ?? []);

        if (empty($this->pageSchema['pages']['create']) || empty($buttonAdd['enabled'])) {
            return;
        }

        $table->buttonAdd(
            __r('backend.resource.'.$this->slug.'.create'),
            (string) ($buttonAdd['label'] ?? ('Aggiungi '.$this->resourceClass::label()))
        );
    }

    private function applyColumns(Table $table): void
    {
        foreach ($this->tableSchema as $key => $config) {
            if (($config['type'] ?? null) === 'button') {
                $size = (string) ($config['size'] ?? 'little');
                $actions = $this->resolvedActions($config);

                if ($actions !== []) {
                    $table->addColumn('', 'menu', false, '', '', $size, $actions);
                }

                continue;
            }

            $label = (string) ($config['label'] ?? $this->resourceClass::getLabel((string) $key) ?? $key);
            $orderable = (bool) ($config['sortable'] ?? false);
            $hiddenDevice = $config['hidden-device'] ?? null;
            $size = $config['size'] ?? null;
            $format = $this->legacyColumnFormat($config);

            $table->addColumn($label, (string) $key, $orderable, '', $hiddenDevice, $size, $format);
        }
    }

    private function normalizeColumns(array $columns): array
    {
        $normalized = [];

        foreach ($columns as $column) {
            if (!is_object($column) || !property_exists($column, 'name')) {
                continue;
            }

            $normalized[(string) $column->name] = method_exists($column, 'toArray')
                ? (array) $column->toArray()
                : [];
        }

        return $normalized;
    }

    private function legacyColumnFormat(array $config): array
    {
        $format = [
            'format' => (string) ($config['type'] ?? 'text'),
        ];

        if (isset($config['function']) && is_array($config['function'])) {
            $format['function'] = $config['function'];
        }

        if (isset($config['value'])) {
            $format['value'] = $config['value'];
        }

        if (isset($config['link']) && $config['link'] !== '') {
            $format['href'] = $config['link'];
        }

        return $format;
    }

    private function defaultSearchFields(): array
    {
        $fields = [];

        foreach ($this->tableSchema as $key => $config) {
            if (($config['type'] ?? 'text') === 'button') {
                continue;
            }

            $fields[] = (string) $key;
        }

        return array_values(array_unique(array_filter($fields, 'is_string')));
    }

    private function tableLayoutSearchFields(): array
    {
        $fields = (array) ($this->tableLayoutSchema['search_fields'] ?? []);

        if ($fields === []) {
            return $this->defaultSearchFields();
        }

        $fields = array_values(array_filter(array_map(
            static fn ($field) => is_string($field) ? trim($field) : '',
            $fields
        )));

        return $fields === [] ? $this->defaultSearchFields() : array_values(array_unique($fields));
    }

    private function resolvedActions(array $buttonColumn): array
    {
        $actions = (array) ($buttonColumn['actions'] ?? []);
        $resolved = [];

        foreach ($actions as $action => $enabled) {
            if (!$enabled) {
                continue;
            }

            $resolved[$action === 'edit' ? 'modify' : $action] = true;
        }

        return $resolved;
    }

    private function routePattern(string $action, bool $withId = false): string
    {
        if (!$withId) {
            return __r('backend.resource.'.$this->slug.'.'.$action);
        }

        $token = '__ROW_ID__';
        $url = __r('backend.resource.'.$this->slug.'.'.$action, ['id' => $token]);

        return str_replace($token, '{rowId}', $url);
    }
}
