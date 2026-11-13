<?php

namespace Wonder\Backend\Support;

use Closure;
use RuntimeException;
use Throwable;
use Wonder\App\Import\Layouts;
use Wonder\App\Import\Profile;
use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\App\Resources\Config\ImportResource;
use Wonder\App\Visibility\RowVisibility;
use Wonder\Backend\Table\BulkActions;
use Wonder\Backend\Table\Reorder;
use Wonder\Backend\Table\Table;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Dropdown;
use Wonder\Sql\Query;

final class ResourceTableRenderer
{
    private array $tableSchema;
    private array $tableLayoutSchema;
    private array $pageSchema;
    private array $querySchema;
    private string $modelClass;
    private string $slug;

    /**
     * @param TableLayoutSchema|null $layout header su misura: serve alle
     *        tabelle incorporate in una scheda, che dell'intestazione della
     *        Resource vogliono cambiare un pezzo senza toccare il suo elenco.
     */
    private function __construct(
        private readonly string $resourceClass,
        array $columns = [],
        ?TableLayoutSchema $layout = null,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }

        $this->tableSchema = self::pickColumns(
            $this->normalizeColumns($this->resourceClass::tableSchema()),
            $columns,
        );
        $this->tableLayoutSchema = ($layout ?? $this->resourceClass::tableLayoutSchema())->all();
        $this->pageSchema = $this->resourceClass::pageSchema()->all();
        $this->querySchema = $this->resourceClass::querySchema();
        $this->modelClass = $this->resourceClass::modelClass();
        $this->slug = $this->resourceClass::slug();
    }

    /**
     * @param array<int, string> $columns colonne da montare, nell'ordine chiesto;
     *                                    vuoto monta tutto lo schema
     */
    public static function make(string $resourceClass, array $columns = [], ?TableLayoutSchema $layout = null): Table
    {
        return (new self($resourceClass, $columns, $layout))->toTable();
    }

    public static function render(string $resourceClass, array $columns = [], ?TableLayoutSchema $layout = null): string
    {
        return self::make($resourceClass, $columns, $layout)->generate();
    }

    /**
     * Restringe lo schema alle colonne chieste, nell'ordine chiesto. Serve alle
     * tabelle incorporate in una scheda, che dello schema vogliono solo un pezzo
     * ma con le etichette e i formattatori della Resource che li possiede.
     *
     * @param array<string, array> $schema
     * @param array<int, string>   $columns
     *
     * @return array<string, array>
     */
    public static function pickColumns(array $schema, array $columns): array
    {
        if ($columns === []) {
            return $schema;
        }

        $picked = [];

        foreach ($columns as $name) {
            $name = (string) $name;

            if (isset($schema[$name])) {
                $picked[$name] = $schema[$name];
            }
        }

        return $picked;
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
        $this->applyButtonDocs($table);
        $this->applyButtonData($table);
        $this->applyReorder($table);
        $this->applyColumns($table);

        return $table;
    }

    /**
     * Un solo bottone «File» con la sua finestra (schede Esporta e Importa),
     * nel posto del vecchio bottone di export.
     */
    private function applyButtonData(Table $table): void
    {
        $built = ResourceFileModal::build(
            $this->slug,
            $this->exportData(),
            $this->importData(),
            ResourceDownloadController::lastChoice($this->slug),
        );

        if ($built === null) {
            return;
        }

        $table->buttonDataHtml($built['button']->render('bootstrap').$built['modal']->render('bootstrap'));
    }

    private function exportData(): ?array
    {
        $download = (array) ($this->tableLayoutSchema['download'] ?? []);
        $formats = (array) ($download['formats'] ?? []);

        if (empty($download['enabled']) || $formats === []) {
            return null;
        }

        try {
            $action = (string) __r('backend.resource.'.$this->slug.'.export.file');
        } catch (Throwable) {
            return null;
        }

        if ($action === '') {
            return null;
        }

        $layouts = ResourceDownloadController::layouts($download, $this->slug);
        $formats = array_map('strval', $formats);

        return [
            'action' => $action,
            'label' => is_string($download['label'] ?? null) ? trim($download['label']) : '',
            'formats' => $formats,
            'profiles' => $layouts['source'] === '' ? [] : Profile::options($layouts['source']),
            'layout_formats' => $layouts['formats'],
            'feeds' => self::feedsFor((array) ($this->tableLayoutSchema['feeds'] ?? []), $formats, $action),
        ];
    }

    /**
     * I feed di `feeds()` con un formato concesso da `download()`, ciascuno
     * con l'indirizzo di «Scarica ora».
     *
     * @param array<int, mixed> $feeds
     * @param array<string, string> $formats
     *
     * @return list<array{format: string, label: string, url: string, updated_at: ?int, every: ?string, download: string}>
     */
    public static function feedsFor(array $feeds, array $formats, string $action): array
    {
        $out = [];

        foreach ($feeds as $feed) {
            $format = is_array($feed) ? (string) ($feed['format'] ?? '') : '';

            if ($format === '' || !array_key_exists($format, $formats)) {
                continue;
            }

            $out[] = [
                'format' => $format,
                'label' => (string) ($feed['label'] ?? $formats[$format]),
                'url' => (string) ($feed['url'] ?? ''),
                'updated_at' => isset($feed['updated_at']) ? (int) $feed['updated_at'] : null,
                'every' => isset($feed['every']) ? (string) $feed['every'] : null,
                'download' => $action.(str_contains($action, '?') ? '&' : '?').'format='.rawurlencode($format),
            ];
        }

        return $out;
    }

    private function importData(): ?array
    {
        $import = $this->tableLayoutSchema['import'] ?? null;
        $key = is_array($import) ? (string) ($import['layout'] ?? '') : '';
        $user = RowVisibility::currentUser();

        if ($key === '' || $user === null || $this->resourceClass::isReadonly()) {
            return null;
        }

        try {
            $layout = Layouts::find($key);
        } catch (Throwable) {
            return null;
        }

        if ($layout === null || !$layout->permits($user)) {
            return null;
        }

        $default = Profile::defaultId($key);

        return [
            'action' => ImportResource::url(),
            'layout' => $key,
            'profiles' => Profile::options($key),
            'default' => $default > 0 ? $default : null,
            'sample' => ImportResource::sampleUrl($key),
        ];
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
        $select = self::selectList($this->resourceClass::modelTable(), (string) ($this->tableLayoutSchema['select'] ?? ''));

        if ($select !== '') {
            $table->select($select);
        }

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
        $searchFields = self::withoutAliases(
            $this->tableLayoutSearchFields(),
            self::selectAliases((string) ($this->tableLayoutSchema['select'] ?? ''))
        );
        $searchEnabled = (bool) ($this->tableLayoutSchema['filters']['search']['enabled'] ?? true);
        if ($searchEnabled && $searchFields !== []) {
            $table->filterSearch(true, $searchFields);
        }

        $table->filterLimit((bool) ($this->tableLayoutSchema['filters']['limit']['enabled'] ?? true));

        foreach ((array) ($this->tableLayoutSchema['custom_filters'] ?? []) as $filter) {
            $arguments = self::filterArguments($filter);

            if ($arguments !== null) {
                $table->addFilter(...$arguments);
            }
        }
    }

    /**
     * Argomenti di `Table::addFilter()` per un filtro di
     * `tableLayoutSchema()`; null se mancano l'etichetta, la colonna o le
     * opzioni.
     *
     * @return array<int, mixed>|null
     */
    public static function filterArguments(mixed $filter): ?array
    {
        if (!is_array($filter)) {
            return null;
        }

        $label = trim((string) ($filter['label'] ?? ''));
        $column = trim((string) ($filter['column'] ?? ''));
        $options = (array) ($filter['array'] ?? []);

        if ($label === '' || $column === '' || $options === []) {
            return null;
        }

        $where = $filter['where'] ?? null;

        return [
            $label,
            $column,
            $options,
            (string) ($filter['input'] ?? 'select'),
            (bool) ($filter['search'] ?? false),
            $filter['column_type'] ?? null,
            $filter['value'] ?? null,
            $where instanceof Closure ? $where : null,
        ];
    }

    /**
     * Lista SELECT della tabella: `tabella`.* più le colonne calcolate di
     * `tableLayoutSchema()->select()`. Vuota se non ce ne sono: resta il
     * `SELECT *` di sempre.
     */
    public static function selectList(string $table, string $computed): string
    {
        $computed = trim($computed);

        return $computed === '' ? '' : Query::escapeIdentifier($table).'.*, '.$computed;
    }

    /**
     * Alias dichiarati con AS nelle colonne calcolate.
     *
     * @return array<int, string>
     */
    public static function selectAliases(string $select): array
    {
        preg_match_all('/\bAS\s+`?([A-Za-z0-9_]+)`?/i', $select, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Toglie dai campi di ricerca gli alias delle colonne calcolate, che il
     * WHERE non vede. I descrittori di relazione restano.
     *
     * @param array<int, string|array<string, mixed>> $fields
     * @param array<int, string> $aliases
     * @return array<int, string|array<string, mixed>>
     */
    public static function withoutAliases(array $fields, array $aliases): array
    {
        $aliases = array_map('strtolower', $aliases);

        return array_values(array_filter(
            $fields,
            static fn ($field) => !is_string($field) || !in_array(strtolower($field), $aliases, true)
        ));
    }

    private function applyButtonAdd(Table $table): void
    {
        $buttonAdd = (array) ($this->tableLayoutSchema['button_add'] ?? []);

        if ($this->resourceClass::isReadonly() || empty($this->pageSchema['pages']['create']) || empty($buttonAdd['enabled'])) {
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

    private function applyButtonDocs(Table $table): void
    {
        if (($this->tableLayoutSchema['docs']['enabled'] ?? true) === false) {
            return;
        }

        $url = $this->resourceClass::pageSchema()->docsUrl('list');

        if ($url === '') {
            return;
        }

        $button = Button::to($url, DocsAction::label())
            ->variant('info')
            ->size('sm')
            ->blank();

        $table->addButtonCustom($this->renderButtonCustom($button), true);
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

    /**
     * Con `reorderable()` la prima colonna è la maniglia e dopo la tabella
     * arriva lo script che salva lo spostamento. In sola lettura niente.
     */
    private function applyReorder(Table $table): void
    {
        $config = Reorder::config($this->tableLayoutSchema['reorder'] ?? null);

        if ($config === null || $this->resourceClass::isReadonly()) {
            return;
        }

        // L'area `api` non prefissa i nomi: la rotta è `backend.reorder`.
        try {
            $endpoint = (string) __r('backend.reorder');
        } catch (\Throwable) {
            return;
        }

        // Senza endpoint la POST andrebbe all'URL della pagina (404).
        if ($endpoint === '') {
            return;
        }

        $table->addColumn('', Reorder::COLUMN, false, '', '', 'little', ['reorder' => $config]);
        $table->appendScript(Reorder::script($table->tableId(), $endpoint, $this->slug));
    }

    /**
     * Con `bulkActions()` la barra sopra la tabella; true se la colonna
     * delle checkbox va montata. In sola lettura niente.
     */
    private function applyBulkActions(Table $table): bool
    {
        $actions = BulkActions::normalize($this->tableLayoutSchema['bulk_actions'] ?? []);

        if ($actions === [] || $this->resourceClass::isReadonly()) {
            return false;
        }

        try {
            $endpoint = (string) __r('backend.bulk');
        } catch (\Throwable) {
            return false;
        }

        if ($endpoint === '') {
            return false;
        }

        $table->beforeTable(BulkActions::bar($table->tableId(), $endpoint, $this->slug, $actions));

        return true;
    }

    private function applyColumns(Table $table): void
    {
        $bulk = $this->applyBulkActions($table);
        $bulkAt = BulkActions::position(array_keys($this->tableSchema));
        $index = 0;

        foreach ($this->tableSchema as $key => $config) {
            if ($bulk && $index++ === $bulkAt) {
                $table->addColumn(BulkActions::header(), BulkActions::COLUMN, false, '', '', 'little');
                $bulk = false;
            }

            if (($config['type'] ?? null) === 'button') {
                $size = (string) ($config['size'] ?? 'little');
                $actions = $this->resolvedActions($config);

                if ($actions !== []) {
                    $table->addColumn('', 'menu', false, '', $config['hidden-device'] ?? '', $size, $actions);
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

        // Schema con la sola colonna dei segnali: le checkbox vanno in fondo.
        if ($bulk) {
            $table->addColumn(BulkActions::header(), BulkActions::COLUMN, false, '', '', 'little');
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

        if (isset($config['formatter'])) {
            if ($config['formatter'] instanceof \Closure) {
                // Chiave derivata: sul giro AJAX viaggia solo la stringa, mai la
                // closure (che è risolta server-side da ColumnFormatterRegistry).
                $format['formatter'] = $this->slug.'.'.((string) ($config['name'] ?? ''));
            } elseif (is_string($config['formatter']) && trim($config['formatter']) !== '') {
                $format['formatter'] = trim($config['formatter']);
            }
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
                return (is_array($field) && !empty($field['table']) && (!empty($field['columns']) || !empty($field['relations']))) ? $field : '';
            },
            $fields
        ), static fn ($f) => $f !== ''));

        if ($fields === []) {
            return $this->defaultSearchFields();
        }

        $strings     = array_values(array_filter($fields, 'is_string'));
        $descriptors = array_values(array_filter($fields, 'is_array'));

        $resolved = self::resolveSearchFields(array_values(array_unique($strings)), $this->foreignKeyMap());

        return self::validSearchDescriptors(
            array_merge($resolved, $descriptors),
            $this->resourceClass::modelTable(),
            static fn (string $table, string $column): bool => (bool) sqlColumnExists($table, $column)
        );
    }

    /**
     * Drop relation descriptors / columns that do not exist in the DB so a
     * malformed or hostile descriptor cannot inject unknown identifiers.
     * Plain string entries pass through unchanged. Nested `relations` are
     * checked the same way, each against its parent table.
     *
     * @param array<int,string|array<string,mixed>> $fields
     * @param callable(string, string): bool $columnExists
     * @return array<int,string|array<string,mixed>>
     */
    public static function validSearchDescriptors(array $fields, string $table, callable $columnExists): array
    {
        $out = [];

        foreach ($fields as $field) {
            if (is_string($field)) {
                $out[] = $field;
                continue;
            }

            $relation = self::validRelation($field, $table, $columnExists);

            if ($relation !== null) {
                $out[] = $relation;
            }
        }

        return $out;
    }

    /**
     * One relation descriptor, cleaned: `local_key` must exist in the parent
     * table, `foreign_key` (default `id`) and the columns in the related
     * table. Null when nothing searchable is left.
     *
     * @param callable(string, string): bool $columnExists
     * @return array<string,mixed>|null
     */
    private static function validRelation(mixed $field, string $parentTable, callable $columnExists): ?array
    {
        if (!is_array($field) || !is_string($field['table'] ?? null) || $field['table'] === '') {
            return null;
        }

        $table = $field['table'];
        $localKey = is_string($field['local_key'] ?? null) ? $field['local_key'] : '';
        $foreignKey = is_string($field['foreign_key'] ?? null) && $field['foreign_key'] !== '' ? $field['foreign_key'] : 'id';

        if ($localKey === '' || !$columnExists($parentTable, $localKey) || !$columnExists($table, $foreignKey)) {
            return null;
        }

        $columns = array_values(array_filter(
            (array) ($field['columns'] ?? []),
            static fn ($column) => is_string($column) && $column !== '' && $columnExists($table, $column)
        ));

        $relations = [];

        foreach ((array) ($field['relations'] ?? []) as $child) {
            $child = self::validRelation($child, $table, $columnExists);

            if ($child !== null) {
                $relations[] = $child;
            }
        }

        if ($columns === [] && $relations === []) {
            return null;
        }

        $field['foreign_key'] = $foreignKey;
        $field['columns'] = $columns;

        if ($relations !== []) {
            $field['relations'] = $relations;
        } else {
            unset($field['relations']);
        }

        return $field;
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
        return self::resolveActions(
            (array) ($buttonColumn['actions'] ?? []),
            $this->resourceClass::isReadonly()
        );
    }

    /**
     * Voci del menu azioni della riga pronte per `Table::addColumn()`:
     * `edit` diventa `modify`, le voci ad array passano intere, in sola
     * lettura `delete` e `duplicate` spariscono.
     *
     * @param array<string, mixed> $actions
     * @return array<string, true|array<string, mixed>>
     */
    public static function resolveActions(array $actions, bool $readonly): array
    {
        $resolved = [];

        foreach ($actions as $action => $enabled) {
            if (!$enabled || ($readonly && in_array($action, ['delete', 'duplicate'], true))) {
                continue;
            }

            $resolved[$action === 'edit' ? 'modify' : $action] = is_array($enabled) ? $enabled : true;
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
