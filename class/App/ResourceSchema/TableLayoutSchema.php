<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Resource;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Dropdown;

final class TableLayoutSchema
{
    private array $schema;

    private function __construct(
        private readonly string $resourceClass,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }

        $this->schema = [
            'title' => [
                'enabled' => true,
                'text' => null,
            ],
            'results' => true,
            'button_add' => [
                'enabled' => true,
                'label' => null,
            ],
            'buttons_custom' => [],
            'filters' => [
                'search' => [
                    'enabled' => true,
                ],
                'limit' => [
                    'enabled' => true,
                ],
            ],
            'search_fields' => [],
            'custom_filters' => [],
            // Bottone "Esporta" nel header della tabella. `formats` mappa
            // codice → label visibile (es. ['csv' => 'CSV', 'xlsx' =>
            // 'Excel']). `columns` può contenere stringhe (nome colonna
            // del record) o array `['label' => '...', 'value' => string|callable]`
            // per controllare label e valore di output (callable riceve
            // l'intera riga e ritorna la cella).
            'download' => [
                'enabled' => false,
                'formats' => ['csv' => 'CSV', 'xlsx' => 'Excel'],
                'columns' => [],
                'filename' => null,
                'label' => null,
            ],
        ];
    }

    public static function for(string $resourceClass): self
    {
        return new self($resourceClass);
    }

    public function title(bool|string $enabled = true, ?string $text = null): self
    {
        if (is_string($enabled)) {
            $text = $enabled;
            $enabled = true;
        }

        $this->schema['title']['enabled'] = (bool) $enabled;

        if ($text !== null) {
            $this->schema['title']['text'] = trim($text);
        }

        return $this;
    }

    public function hideTitle(): self
    {
        return $this->title(false);
    }

    public function results(bool $enabled = true): self
    {
        $this->schema['results'] = $enabled;

        return $this;
    }

    public function buttonAdd(bool|string $enabled = true, ?string $label = null): self
    {
        if (is_string($enabled)) {
            $label = $enabled;
            $enabled = true;
        }

        $this->schema['button_add']['enabled'] = (bool) $enabled;

        if ($label !== null) {
            $this->schema['button_add']['label'] = trim($label);
        }

        return $this;
    }

    public function hideButtonAdd(): self
    {
        return $this->buttonAdd(false);
    }

    /**
     * Aggiunge un'azione custom accanto al bottone "Aggiungi".
     *
     * I componenti vengono renderizzati con il tema Bootstrap del backend.
     * Una stringa viene trattata come HTML trusted, come alias di
     * `buttonCustomHtml()`.
     */
    public function buttonCustom(Button|Dropdown|string $button): self
    {
        if (is_string($button)) {
            return $this->buttonCustomHtml($button);
        }

        $this->schema['buttons_custom'][] = $button;

        return $this;
    }

    /**
     * @param array<int, Button|Dropdown|string> $buttons
     */
    public function buttonsCustom(array $buttons): self
    {
        foreach ($buttons as $button) {
            if ($button instanceof Button || $button instanceof Dropdown || is_string($button)) {
                $this->buttonCustom($button);
            }
        }

        return $this;
    }

    /**
     * Aggiunge HTML trusted accanto al bottone "Aggiungi".
     *
     * Il contenuto non viene escapato: usare questo metodo soltanto con markup
     * costruito dal codice applicativo e valori dinamici già sanificati.
     */
    public function buttonCustomHtml(string $html): self
    {
        if (trim($html) !== '') {
            $this->schema['buttons_custom'][] = $html;
        }

        return $this;
    }

    public function clearButtonsCustom(): self
    {
        $this->schema['buttons_custom'] = [];

        return $this;
    }

    public function filterSearch(bool $enabled = true): self
    {
        $this->schema['filters']['search']['enabled'] = $enabled;

        return $this;
    }

    public function filterLimit(bool $enabled = true): self
    {
        $this->schema['filters']['limit']['enabled'] = $enabled;

        return $this;
    }

    public function filters(bool $search = true, bool $limit = true): self
    {
        return $this
            ->filterSearch($search)
            ->filterLimit($limit);
    }

    public function searchFields(array $fields): self
    {
        $clean = [];

        foreach ($fields as $field) {
            if (is_string($field)) {
                $trimmed = trim($field);
                if ($trimmed !== '') {
                    $clean[] = $trimmed;
                }
            } elseif (is_array($field) && !empty($field['table']) && !empty($field['columns'])) {
                $clean[] = $field;
            }
        }

        $this->schema['search_fields'] = array_values($clean);

        return $this;
    }

    public function filterCustom(
        string $label,
        string $column,
        array $options,
        string $input = 'select',
        bool $search = false,
        ?string $columnType = null,
        mixed $value = null
    ): self {
        $this->schema['custom_filters'][] = [
            'label' => trim($label),
            'column' => trim($column),
            'array' => $options,
            'input' => trim($input) !== '' ? trim($input) : 'select',
            'search' => $search,
            'column_type' => $columnType,
            'value' => $value,
        ];

        return $this;
    }

    public function filterRadio(string $label, string $column, array $options, bool $search = false, mixed $value = null): self
    {
        return $this->filterCustom($label, $column, $options, 'radio', $search, null, $value);
    }

    public function cleanHeader(): self
    {
        return $this
            ->hideTitle()
            ->results(false)
            ->hideButtonAdd()
            ->clearButtonsCustom();
    }

    /**
     * Abilita il bottone "Esporta" nel header della tabella.
     *
     * `$formats` può essere:
     *  - `true` (default): tutti i formati supportati (`csv` + `xlsx`)
     *  - array di codici: `['csv']` o `['csv', 'xlsx']`
     *  - array assoc codice → label visibile: `['xlsx' => 'Foglio Excel']`
     *
     * Formati supportati: `csv`, `xlsx` (vedi `app/function/arrayTo.php`).
     */
    public function download(bool|array $formats = true, ?string $label = null): self
    {
        $this->schema['download']['enabled'] = $formats !== false && $formats !== [];

        if (is_array($formats) && $formats !== []) {
            $normalized = [];
            $defaults = ['csv' => 'CSV', 'xlsx' => 'Excel'];

            foreach ($formats as $key => $value) {
                if (is_int($key) && is_string($value)) {
                    $code = strtolower(trim($value));
                    if ($code !== '') {
                        $normalized[$code] = $defaults[$code] ?? strtoupper($code);
                    }
                } elseif (is_string($key) && is_string($value)) {
                    $code = strtolower(trim($key));
                    if ($code !== '') {
                        $normalized[$code] = trim($value);
                    }
                }
            }

            if ($normalized !== []) {
                $this->schema['download']['formats'] = $normalized;
            }
        }

        if ($label !== null && trim($label) !== '') {
            $this->schema['download']['label'] = trim($label);
        }

        return $this;
    }

    public function hideDownload(): self
    {
        $this->schema['download']['enabled'] = false;

        return $this;
    }

    /**
     * Colonne da includere nell'export, in ordine.
     *
     * Ogni elemento può essere:
     *  - **stringa** — nome del campo del record (es. `'email'`). La label
     *    viene presa da `Resource::getLabel('email')` o, in fallback, dal
     *    nome campo titolato.
     *  - **array** con shape `['label' => string, 'value' => string|callable]`
     *    — utile per:
     *    * customizzare la label dell'header
     *    * computare il valore con un `callable(array $row): mixed` (es.
     *      concatenare `name + surname`, formattare una data, ecc.)
     *
     * Senza `downloadColumns()` esplicito, l'export usa tutte le colonne
     * del Model (comportamento del legacy `sqlExport()`).
     *
     * Esempio:
     *
     * ```php
     * ->downloadColumns([
     *     'creation',
     *     'email',
     *     ['label' => 'Nome completo', 'value' => fn($r) => trim(($r['name'] ?? '').' '.($r['surname'] ?? ''))],
     *     ['label' => 'Stato',          'value' => fn($r) => $r['active'] ? 'attivo' : 'inattivo'],
     * ])
     * ```
     */
    public function downloadColumns(array $columns): self
    {
        $normalized = [];

        foreach ($columns as $column) {
            if (is_string($column) && trim($column) !== '') {
                $normalized[] = [
                    'field' => trim($column),
                    'label' => null,
                    'value' => null,
                ];
                continue;
            }

            if (!is_array($column)) {
                continue;
            }

            $value = $column['value'] ?? null;

            $normalized[] = [
                'field' => isset($column['field']) ? trim((string) $column['field']) : (is_string($value) ? trim($value) : ''),
                'label' => isset($column['label']) ? trim((string) $column['label']) : null,
                'value' => is_callable($value) ? $value : (is_string($value) ? trim($value) : null),
            ];
        }

        $this->schema['download']['columns'] = $normalized;
        $this->schema['download']['enabled'] = $this->schema['download']['enabled'] || $normalized !== [];

        return $this;
    }

    public function downloadFileName(string $filename): self
    {
        $this->schema['download']['filename'] = trim($filename);

        return $this;
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

    public function toArray(): array
    {
        return $this->all();
    }
}
