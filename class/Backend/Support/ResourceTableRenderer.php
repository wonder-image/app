<?php

namespace Wonder\Backend\Support;

use mysqli;
use RuntimeException;
use Wonder\App\Resource;

final class ResourceTableRenderer
{
    private array $tableSchema;
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

        $this->tableSchema = $this->resourceClass::tableSchema();
        $this->pageSchema = $this->resourceClass::pageSchema();
        $this->querySchema = $this->resourceClass::querySchema();
        $this->modelClass = $this->resourceClass::modelClass();
        $this->slug = $this->resourceClass::slug();
    }

    public static function render(string $resourceClass): string
    {
        return (new self($resourceClass))->toHtml();
    }

    private function toHtml(): string
    {
        $rows = $this->loadRows();
        $search = $this->searchTerm();
        $title = $this->listTitle();
        $createButton = $this->renderCreateButton();
        $searchForm = $this->renderSearchForm($search);
        $results = $this->renderResultsCount(count($rows));
        $table = empty($rows) ? $this->renderEmptyState($search) : $this->renderTable($rows);

        return <<<HTML
<div class="row g-3">
    <wi-card class="col-12">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h3 class="mb-0">{$title}</h3>
                {$results}
            </div>
            <div class="d-flex align-items-center gap-2">
                {$createButton}
            </div>
        </div>
    </wi-card>
    {$searchForm}
    <wi-card class="col-12">
        <div class="table-responsive">
            {$table}
        </div>
    </wi-card>
</div>
HTML;
    }

    private function loadRows(): array
    {
        $condition = $this->resolvedCondition();
        $defaultOrder = (array) ($this->tableSchema['default_order'] ?? []);
        $orderColumn = (string) ($defaultOrder['column'] ?? $this->querySchema['order']['column'] ?? 'id');
        $orderDirection = (string) ($defaultOrder['direction'] ?? $this->querySchema['order']['direction'] ?? 'DESC');
        $limit = $this->querySchema['limit'] ?? null;
        $rows = $this->modelClass::find($condition, $limit, $orderColumn, $orderDirection);

        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $isAssoc = array_keys($rows) !== range(0, count($rows) - 1);

        return $isAssoc ? [ $rows ] : $rows;
    }

    private function resolvedCondition(): string|array|null
    {
        $search = $this->searchTerm();
        $searchColumns = (array) ($this->tableSchema['filters']['search'] ?? []);
        $baseCondition = $this->querySchema['condition'] ?? null;

        if ($search === '' || empty($searchColumns)) {
            return $baseCondition;
        }

        $connection = $this->modelClass::connection();
        $searchSql = $this->buildSearchCondition($searchColumns, $search, $connection);
        $baseSql = $this->conditionToSql($baseCondition, $connection);

        if ($baseSql === '') {
            return $searchSql;
        }

        return '('.$baseSql.') AND ('.$searchSql.')';
    }

    private function buildSearchCondition(array $columns, string $search, mysqli $connection): string
    {
        $escapedSearch = $connection->real_escape_string($search);
        $like = "%{$escapedSearch}%";
        $parts = [];

        foreach ($columns as $column) {
            if (!is_string($column) || trim($column) === '') {
                continue;
            }

            $parts[] = $this->normalizeColumnName($column)." LIKE '".$like."'";
        }

        return empty($parts) ? '1 = 1' : implode(' OR ', $parts);
    }

    private function conditionToSql(string|array|null $condition, mysqli $connection): string
    {
        if ($condition === null) {
            return '';
        }

        if (is_string($condition)) {
            return trim($condition);
        }

        $parts = [];

        foreach ($condition as $column => $value) {
            if (!is_string($column) || trim($column) === '') {
                continue;
            }

            $columnName = $this->normalizeColumnName($column);

            if (is_array($value)) {
                $items = [];

                foreach ($value as $item) {
                    $items[] = "'".$connection->real_escape_string((string) $item)."'";
                }

                if (!empty($items)) {
                    $parts[] = $columnName.' IN ('.implode(', ', $items).')';
                }

                continue;
            }

            if ($value === null) {
                $parts[] = $columnName.' IS NULL';
                continue;
            }

            $parts[] = $columnName." = '".$connection->real_escape_string((string) $value)."'";
        }

        return implode(' AND ', $parts);
    }

    private function normalizeColumnName(string $column): string
    {
        $column = trim($column);

        if ($column === '') {
            return $column;
        }

        if (!preg_match('/^[A-Za-z0-9_.]+$/', $column)) {
            return $column;
        }

        $segments = array_map(
            static fn (string $segment): string => '`'.$segment.'`',
            array_filter(explode('.', $column), static fn (string $segment): bool => $segment !== '')
        );

        return implode('.', $segments);
    }

    private function renderCreateButton(): string
    {
        if (empty($this->pageSchema['pages']['create'])) {
            return '';
        }

        $label = htmlspecialchars('Aggiungi '.$this->resourceClass::label(), ENT_QUOTES, 'UTF-8');
        $href = htmlspecialchars(__r('backend.resource.'.$this->slug.'.create'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<a href="{$href}" class="btn btn-dark">
    <i class="bi bi-plus-lg"></i> {$label}
</a>
HTML;
    }

    private function renderSearchForm(string $search): string
    {
        $searchColumns = (array) ($this->tableSchema['filters']['search'] ?? []);

        if (empty($searchColumns)) {
            return '';
        }

        $action = htmlspecialchars(__r('backend.resource.'.$this->slug.'.list'), ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
        $reset = htmlspecialchars(__r('backend.resource.'.$this->slug.'.list'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
    <wi-card class="col-12">
        <form method="GET" action="{$action}" class="row g-2 align-items-end">
            <div class="col-12 col-md-8">
                <label class="form-label">Cerca</label>
                <input type="text" name="search" value="{$value}" class="form-control" placeholder="Cerca nella lista">
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-outline-dark w-100">Filtra</button>
            </div>
            <div class="col-12 col-md-auto">
                <a href="{$reset}" class="btn btn-link text-decoration-none w-100">Reset</a>
            </div>
        </form>
    </wi-card>
HTML;
    }

    private function renderResultsCount(int $count): string
    {
        if (empty($this->tableSchema['results'])) {
            return '';
        }

        $label = $count === 1 ? $this->resourceClass::label() : $this->resourceClass::pluralLabel();
        $text = htmlspecialchars($count.' '.$label, ENT_QUOTES, 'UTF-8');

        return '<div class="text-body-secondary small mt-1">'.$text.'</div>';
    }

    private function renderEmptyState(string $search): string
    {
        $message = $search === ''
            ? 'Nessun elemento disponibile.'
            : 'Nessun risultato per la ricerca inserita.';

        return '<div class="alert alert-light border mb-0">'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</div>';
    }

    private function renderTable(array $rows): string
    {
        $headers = [];

        foreach ((array) ($this->tableSchema['columns'] ?? []) as $key => $column) {
            if (!is_string($key) || !is_array($column)) {
                continue;
            }

            $classes = trim((string) ($column['class'] ?? '')).' '.$this->hiddenDeviceClass((string) ($column['hidden-device'] ?? ''));
            $classAttribute = trim($classes) !== '' ? ' class="'.htmlspecialchars(trim($classes), ENT_QUOTES, 'UTF-8').'"' : '';
            $label = htmlspecialchars((string) ($column['label'] ?? $key), ENT_QUOTES, 'UTF-8');

            $headers[] = "<th{$classAttribute}>{$label}</th>";
        }

        if ($this->hasActions()) {
            $headers[] = '<th class="text-end">Azioni</th>';
        }

        $body = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $body[] = $this->renderRow($row);
        }

        $thead = implode('', $headers);
        $tbody = implode('', $body);

        return <<<HTML
<table class="table align-middle mb-0">
    <thead>
        <tr>{$thead}</tr>
    </thead>
    <tbody>{$tbody}</tbody>
</table>
HTML;
    }

    private function renderRow(array $row): string
    {
        $cells = [];

        foreach ((array) ($this->tableSchema['columns'] ?? []) as $key => $column) {
            if (!is_string($key) || !is_array($column)) {
                continue;
            }

            $classes = trim((string) ($column['class'] ?? '')).' '.$this->hiddenDeviceClass((string) ($column['hidden-device'] ?? ''));
            $classAttribute = trim($classes) !== '' ? ' class="'.htmlspecialchars(trim($classes), ENT_QUOTES, 'UTF-8').'"' : '';
            $cells[] = '<td'.$classAttribute.'>'.$this->renderCellValue($key, $column, $row).'</td>';
        }

        if ($this->hasActions()) {
            $cells[] = '<td class="text-end">'.$this->renderActions($row).'</td>';
        }

        return '<tr>'.implode('', $cells).'</tr>';
    }

    private function renderCellValue(string $key, array $column, array $row): string
    {
        $value = $this->resolveCellValue($key, $column, $row);
        $type = (string) ($column['type'] ?? 'text');
        $html = match ($type) {
            'badge' => $this->renderBadgeValue($key, $value),
            'icon' => $this->renderIconValue($value),
            'image' => $this->renderImageValue($value),
            default => htmlspecialchars($this->stringValue($value), ENT_QUOTES, 'UTF-8'),
        };

        if (!empty($column['link']) && isset($row['id'])) {
            $href = $this->resolveColumnLink((string) $column['link'], (string) $row['id']);

            if ($href !== '') {
                $escapedHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
                $html = '<a href="'.$escapedHref.'" class="text-reset text-decoration-none">'.$html.'</a>';
            }
        }

        return $html;
    }

    private function resolveCellValue(string $key, array $column, array $row): mixed
    {
        if (isset($column['value']) && is_array($column['value'])) {
            $parts = [];

            foreach ($column['value'] as $field) {
                if (!is_string($field) || !array_key_exists($field, $row)) {
                    continue;
                }

                $parts[] = $row[$field];
            }

            return implode(' ', array_filter($parts, static fn ($value): bool => $value !== null && $value !== ''));
        }

        return $row[$key] ?? '';
    }

    private function renderBadgeValue(string $key, mixed $value): string
    {
        $normalized = strtolower(trim($this->stringValue($value)));

        if ($key === 'visible') {
            $visible = in_array($normalized, ['1', 'true', 'yes', 'on'], true);
            $class = $visible ? 'success' : 'danger';
            $text = $visible ? 'Visibile' : 'Nascosto';

            return '<span class="badge text-bg-'.$class.'">'.htmlspecialchars(strtoupper($text), ENT_QUOTES, 'UTF-8').'</span>';
        }

        if ($key === 'active') {
            $active = in_array($normalized, ['1', 'true', 'yes', 'on'], true);
            $class = $active ? 'success' : 'danger';
            $text = $active ? 'Abilitato' : 'Disabilitato';

            return '<span class="badge text-bg-'.$class.'">'.htmlspecialchars(strtoupper($text), ENT_QUOTES, 'UTF-8').'</span>';
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return '<span class="badge text-bg-success">TRUE</span>';
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return '<span class="badge text-bg-secondary">FALSE</span>';
        }

        $text = htmlspecialchars(strtoupper($this->stringValue($value)), ENT_QUOTES, 'UTF-8');

        return '<span class="badge text-bg-dark">'.$text.'</span>';
    }

    private function renderIconValue(mixed $value): string
    {
        $class = trim($this->stringValue($value));

        if ($class === '') {
            return '';
        }

        return '<i class="'.htmlspecialchars($class, ENT_QUOTES, 'UTF-8').'"></i>';
    }

    private function renderImageValue(mixed $value): string
    {
        $src = trim($this->stringValue($value));

        if ($src === '') {
            return '';
        }

        $escapedSrc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');

        return '<img src="'.$escapedSrc.'" alt="" class="img-fluid rounded" style="max-width: 48px; max-height: 48px;">';
    }

    private function renderActions(array $row): string
    {
        $id = isset($row['id']) ? (string) $row['id'] : '';

        if ($id === '') {
            return '';
        }

        $actions = [];

        if (!empty($this->tableSchema['actions']['view']) && !empty($this->pageSchema['pages']['view'])) {
            $actions[] = $this->renderActionButton('view', $this->route('view', $id), 'bi-eye', 'btn-outline-secondary');
        }

        if (!empty($this->tableSchema['actions']['edit']) && !empty($this->pageSchema['pages']['edit'])) {
            $actions[] = $this->renderActionButton('edit', $this->route('edit', $id), 'bi-pencil', 'btn-outline-dark');
        }

        if (!empty($this->tableSchema['actions']['delete']) && !empty($this->pageSchema['pages']['delete'])) {
            $actions[] = $this->renderDeleteAction($id);
        }

        return '<div class="d-inline-flex gap-2">'.implode('', $actions).'</div>';
    }

    private function renderActionButton(
        string $action,
        string $href,
        string $icon,
        string $class,
        string $onClick = ''
    ): string {
        $title = htmlspecialchars(ucfirst($action), ENT_QUOTES, 'UTF-8');
        $escapedHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        $onClickAttribute = $onClick !== '' ? ' onclick="'.htmlspecialchars($onClick, ENT_QUOTES, 'UTF-8').'"' : '';

        return <<<HTML
<a href="{$escapedHref}" class="btn btn-sm {$class}" title="{$title}"{$onClickAttribute}>
    <i class="bi {$icon}"></i>
</a>
HTML;
    }

    private function renderDeleteAction(string $id): string
    {
        $action = htmlspecialchars($this->route('delete', $id), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars('Delete', ENT_QUOTES, 'UTF-8');
        $confirm = htmlspecialchars(
            'return confirm('.json_encode('Sei sicuro di voler eliminare '.$this->resourceClass::getText('this').' '.$this->resourceClass::label().' ?').');',
            ENT_QUOTES,
            'UTF-8'
        );

        return <<<HTML
<form method="POST" action="{$action}" class="d-inline" onsubmit="{$confirm}">
    <button type="submit" class="btn btn-sm btn-outline-danger" title="{$title}">
        <i class="bi bi-trash"></i>
    </button>
</form>
HTML;
    }

    private function resolveColumnLink(string $link, string $id): string
    {
        return match ($link) {
            'edit' => !empty($this->pageSchema['pages']['edit']) ? $this->route('edit', $id) : '',
            'view' => !empty($this->pageSchema['pages']['view']) ? $this->route('view', $id) : '',
            default => $link,
        };
    }

    private function route(string $action, string $id): string
    {
        return __r('backend.resource.'.$this->slug.'.'.$action, ['id' => $id]);
    }

    private function hasActions(): bool
    {
        foreach ((array) ($this->tableSchema['actions'] ?? []) as $enabled) {
            if ($enabled) {
                return true;
            }
        }

        return false;
    }

    private function hiddenDeviceClass(string $device): string
    {
        return match ($device) {
            'mobile' => 'phone-none',
            'tablet' => 'tablet-none',
            'desktop' => 'pc-none',
            default => '',
        };
    }

    private function listTitle(): string
    {
        $titles = (array) ($this->pageSchema['titles'] ?? []);

        return htmlspecialchars((string) ($titles['list'] ?? ('Lista '.$this->resourceClass::pluralLabel())), ENT_QUOTES, 'UTF-8');
    }

    private function searchTerm(): string
    {
        return trim((string) ($_GET['search'] ?? ''));
    }

    private function stringValue(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return '';
    }
}
