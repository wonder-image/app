<?php

namespace Wonder\App\Dataset;

use RuntimeException;
use Wonder\App\Resource;
use Wonder\App\Visibility\RowVisibility;

/**
 * Una Resource come fonte di export: colonne da `fileColumns()` (o, senza,
 * da `tableSchema()`), righe dal suo Model con la condizione e l'ordine di
 * `querySchema()` e la visibilità per riga di chi esporta.
 */
class ResourceSource implements Exportable
{
    /** @var class-string<Resource> */
    private string $resource;

    /** @param class-string<Resource> $resource */
    public function __construct(string $resource)
    {
        if (!is_subclass_of($resource, Resource::class)) {
            throw new RuntimeException("{$resource} deve estendere ".Resource::class);
        }

        $this->resource = $resource;
    }

    /** @return class-string<Resource> */
    public function resource(): string
    {
        return $this->resource;
    }

    public function id(): string
    {
        return $this->resource::slug();
    }

    public function label(): string
    {
        return $this->resource::titlePluralLabel();
    }

    /** Le opzioni di `download()`: formati, nome file, testo, gestore. */
    public function download(): array
    {
        return (array) ($this->resource::tableLayoutSchema()->all()['download'] ?? []);
    }

    public function columns(): array
    {
        $configured = (array) ($this->download()['columns'] ?? []);
        $columns = [];
        $keys = [];

        foreach ($configured === [] ? $this->fallback() : $configured as $entry) {
            $column = $entry instanceof Column ? $entry : $this->fromArray((array) $entry);

            if ($column === null) {
                continue;
            }

            // Chiavi uniche: un tracciato salvato indica le colonne per chiave.
            $key = $column->key;
            for ($n = 2; isset($keys[$key]); $n++) {
                $key = $column->key.'_'.$n;
            }

            $keys[$key] = true;
            $columns[] = $key === $column->key ? $column : new Column($key, $column->header, $column->aliases, $column->required, $column->field, $column->value ?? $column->key, $column->type, $column->sensitive);
        }

        return $columns;
    }

    public function rows(): iterable
    {
        $query = $this->resource::querySchema();
        $model = $this->resource::modelClass();
        $condition = RowVisibility::apply($model::$table, $query['condition'] ?? null, RowVisibility::currentUser());
        $rows = $this->download()['rows'] ?? null;

        if (is_callable($rows)) {
            return $rows($condition);
        }

        $rows = $model::find($condition, null, (string) ($query['order']['column'] ?? 'creation'), (string) ($query['order']['direction'] ?? 'DESC'));

        return is_array($rows) ? (isset($rows['id']) ? [$rows] : $rows) : [];
    }

    public function allows(?object $user, string $action): bool
    {
        if ($action !== 'export' || empty($this->download()['enabled'])) {
            return false;
        }

        return $user === null || $this->resource::allows(RowVisibility::authorities($user), 'list');
    }

    /** La colonna di una voce di `downloadColumns()` (`field`, `label`, `value`). */
    private function fromArray(array $entry): ?Column
    {
        $field = trim((string) ($entry['field'] ?? ''));
        $value = $entry['value'] ?? null;
        $label = is_string($entry['label'] ?? null) ? trim($entry['label']) : '';

        if ($field === '' && !is_callable($value)) {
            return null;
        }

        if ($label === '' && $field !== '') {
            $label = (string) ($this->resource::getLabel($field) ?: self::titleize($field));
        }

        $key = $field !== '' ? $field : (self::key($label) ?: 'colonna');
        $column = Column::make($key, $label);

        // Una stringa è sempre il nome di un campo, mai una funzione globale.
        if (is_string($value)) {
            return $column->value(trim($value) !== '' && trim($value) !== $key ? $value : null);
        }

        return $column->value(is_callable($value) ? $value : null);
    }

    /** Senza colonne dichiarate, quelle di `tableSchema()` tranne i bottoni. */
    private function fallback(): array
    {
        $columns = [];

        foreach ($this->resource::tableSchema() as $column) {
            if (!is_object($column) || !property_exists($column, 'name')) {
                continue;
            }

            $arr = method_exists($column, 'toArray') ? (array) $column->toArray() : [];

            if (($arr['type'] ?? null) !== 'button') {
                $columns[] = ['field' => (string) $column->name, 'label' => null, 'value' => null];
            }
        }

        return $columns;
    }

    private static function key(string $label): string
    {
        $key = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $label), '_'));

        return $key;
    }

    private static function titleize(string $field): string
    {
        $clean = trim(str_replace(['_', '-'], ' ', $field));

        return $clean === '' ? '' : mb_convert_case($clean, MB_CASE_TITLE, 'UTF-8');
    }
}
