<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\Backend\Table\Table;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Dropdown;

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
        $table->addEndpointValue('schema', $this->resourceClass::prepareSchemaName());

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
        $this->applyButtonsCustom($table);
        $this->applyButtonDownload($table);
        $this->applyColumns($table);

        return $table;
    }

    private function applyButtonDownload(Table $table): void
    {
        $download = (array) ($this->tableLayoutSchema['download'] ?? []);

        if (empty($download['enabled'])) {
            return;
        }

        $formats = (array) ($download['formats'] ?? []);

        if ($formats === []) {
            return;
        }

        // Endpoint custom Resource-aware: il controller applica colonne +
        // callable dichiarati in tableLayoutSchema. Trailing slash importante
        // perché Table costruisce `{endpoint}{format}/`.
        try {
            $endpoint = __r('backend.resource.'.$this->slug.'.export', ['format' => '__FMT__']);
            $endpoint = (string) str_replace('__FMT__/', '', $endpoint);
        } catch (\Throwable) {
            $endpoint = '';
        }

        $label = isset($download['label']) && is_string($download['label']) && trim($download['label']) !== ''
            ? trim($download['label'])
            : null;

        $table->buttonDownload(true, $formats, $endpoint, $label);
    }

    private function applyLinks(Table $table): void
    {
        $links = [
            'view' => $this->routePattern('view', true),
            'modify' => $this->routePattern('edit', true),
            'download' => $this->routePattern('download', true),
            'duplicate' => $this->routePattern('duplicate', true),
            'file' => (new \Wonder\App\Path)->upload.'/'.$this->resourceClass::legacyFolder(),
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

    private function applyButtonsCustom(Table $table): void
    {
        foreach ((array) ($this->tableLayoutSchema['buttons_custom'] ?? []) as $button) {
            $html = $this->renderButtonCustom($button);

            if ($html !== '') {
                $table->addButtonCustom($html, true);
            }
        }
    }

    private function renderButtonCustom(mixed $button): string
    {
        if (is_string($button)) {
            return trim($button);
        }

        if (!$button instanceof Button && !$button instanceof Dropdown) {
            return '';
        }

        $component = clone $button;
        $component->schema('inline', true);

        if (trim((string) $component->getSchema('size')) === '') {
            $component->size('sm');
        }

        return $component->render('bootstrap');
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

        if (isset($config['badge']) && is_array($config['badge'])) {
            $format['badge'] = $config['badge'];
        }

        if (isset($config['value'])) {
            $format['value'] = $config['value'];
        }

        if (isset($config['link']) && $config['link'] !== '') {
            $format['href'] = $config['link'];
        }

        if (isset($config['formatter']) && is_string($config['formatter']) && trim($config['formatter']) !== '') {
            $format['formatter'] = trim($config['formatter']);
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
            static function ($field) {
                if (is_string($field)) {
                    return trim($field);
                }
                return (is_array($field) && !empty($field['table']) && !empty($field['columns'])) ? $field : '';
            },
            $fields
        ), static fn ($f) => $f !== ''));

        if ($fields === []) {
            return $this->defaultSearchFields();
        }

        $strings     = array_values(array_filter($fields, 'is_string'));
        $descriptors = array_values(array_filter($fields, 'is_array'));

        $resolved = self::resolveSearchFields(array_values(array_unique($strings)), $this->foreignKeyMap());

        return $this->validateSearchDescriptors(array_merge($resolved, $descriptors));
    }

    /**
     * Drop relation descriptors / columns that do not exist in the DB so a
     * malformed or hostile descriptor cannot inject unknown identifiers.
     * Plain string entries pass through unchanged.
     *
     * @param array<int,string|array<string,mixed>> $fields
     * @return array<int,string|array<string,mixed>>
     */
    private function validateSearchDescriptors(array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            if (is_string($field)) {
                $out[] = $field;
                continue;
            }

            if (!is_array($field) || empty($field['table'])) {
                continue;
            }

            $table = (string) $field['table'];

            $validCols = array_values(array_filter(
                (array) ($field['columns'] ?? []),
                static fn ($col) => is_string($col) && $col !== '' && sqlColumnExists($table, $col)
            ));

            if ($validCols === [] || !sqlColumnExists($table, (string) ($field['foreign_key'] ?? 'id'))) {
                continue;
            }

            $field['columns'] = $validCols;
            $out[] = $field;
        }

        return $out;
    }

    /**
     * Resolve dotted `foreign_table.column` search entries into relation
     * descriptors using a foreign-key map. Plain entries pass through.
     *
     * @param array<int,string> $fields
     * @param array<string,array{local_key:string,foreign_key:string}> $foreignMap
     * @return array<int,string|array{table:string,local_key:string,foreign_key:string,columns:array<int,string>}>
     */
    public static function resolveSearchFields(array $fields, array $foreignMap): array
    {
        $plain     = [];
        $relations = []; // foreign_table => descriptor
        $order     = []; // preserves first-seen order of output entries

        foreach ($fields as $field) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            if (strpos($field, '.') === false) {
                $plain[] = $field;
                $order[] = ['plain', count($plain) - 1];
                continue;
            }

            [$table, $column] = explode('.', $field, 2);
            $table  = trim($table);
            $column = trim($column);

            if ($table === '' || $column === '' || !isset($foreignMap[$table])) {
                continue; // unresolved relation -> drop
            }

            if (!isset($relations[$table])) {
                $relations[$table] = [
                    'table'       => $table,
                    'local_key'   => $foreignMap[$table]['local_key'],
                    'foreign_key' => $foreignMap[$table]['foreign_key'],
                    'columns'     => [],
                ];
                $order[] = ['relation', $table];
            }

            if (!in_array($column, $relations[$table]['columns'], true)) {
                $relations[$table]['columns'][] = $column;
            }
        }

        $out = [];
        foreach ($order as [$kind, $ref]) {
            $out[] = $kind === 'plain' ? $plain[$ref] : $relations[$ref];
        }

        return $out;
    }

    /**
     * Map foreign_table => {local_key, foreign_key} from the Model tableSchema.
     *
     * @return array<string,array{local_key:string,foreign_key:string}>
     */
    private function foreignKeyMap(): array
    {
        $map = [];

        foreach ($this->modelClass::tableSchema() as $column) {
            if (!is_object($column) || !method_exists($column, 'getSchema')) {
                continue;
            }

            $foreignTable = $column->getSchema('foreign_table');
            if (!is_string($foreignTable) || trim($foreignTable) === '') {
                continue;
            }

            $map[trim($foreignTable)] = [
                'local_key'   => (string) $column->name,
                'foreign_key' => (string) ($column->getSchema('foreign_key') ?: 'id'),
            ];
        }

        return $map;
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
