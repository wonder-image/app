<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

/**
 * Lista riordinabile di righe con celle input (text/number/price/select/
 * date/date-time). Markup gemello del legacy `sortableInput()` (admin):
 *
 *  - bottoni up/down/delete per riga (chiamano funzioni JS `rowOrder`,
 *    `rowRemoveModal` già caricate dall'admin)
 *  - row template invisibile clonata da `copyRow()` al click di "+"
 *  - chiamata finale `rowSetArrow()` per impostare lo stato delle frecce
 *
 * Differenze rispetto a `Repeater`:
 *
 *  - identità riga via input hidden `id[]`/`position[]` (Repeater usa
 *    `data-wi-row-key`)
 *  - JS di interazione preesistente (Repeater porta dentro le sue
 *    funzioni window.wiRepeater*)
 *  - nessuna gestione di nested/sortable opt-in: SEMPRE sortable
 */
class SortableInput extends Field
{
    public string $type = 'sortable';

    /**
     * Override del container ID. Differenza chiave rispetto agli altri
     * Field: qui l'ID NON è auto-generato perché il legacy `sortableInput`
     * lo riceve come parametro (usato anche dal JS `copyRow($ID, ...)`).
     */
    public function containerId(string $id): self
    {

        $this->id = $id;

        return $this->schema('id', $id);

    }

    /**
     * Titolo `<h5>` sopra la lista.
     */
    public function title(string $title): self
    {

        return $this->schema('title', $title);

    }

    /**
     * Definizione delle colonne. Schema array atteso:
     *
     *   [
     *     'column_name' => [
     *       'label'     => string,
     *       'type'      => 'text'|'number'|'price'|'select'|'date'|'date-time',
     *       'option'    => array,             // solo per type=select
     *       'version'   => string|null,       // solo per type=select
     *       'attribute' => string|null,
     *       'col'       => int (default 1),   // Bootstrap col-N
     *       'value'     => mixed              // default per template
     *     ],
     *     …
     *   ]
     */
    public function columns(array $columns): self
    {

        return $this->schema('columns', $columns);

    }

    /**
     * Righe già valorizzate. Ogni riga: `['id' => ?, 'position' => N, '<column>' => …]`.
     */
    public function rows(?array $rows): self
    {

        return $this->schema('rows', $rows);

    }

    protected function renderInput(): string
    {

        return '';

    }
}
