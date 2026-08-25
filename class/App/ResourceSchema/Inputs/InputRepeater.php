<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\Repeater as RepeaterElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Blocco di righe ripetibili.
 *
 * Le colonne sono `Input` (tipicamente dichiarati con `RepeaterColumn::key()`)
 * oppure array descrittori `['name' => ..., 'helper' => ..., ...]`; il
 * renderer Bootstrap gestisce entrambe le forme.
 *
 * `nested(true)` fa sì che i campi vengano postati come
 * `parent[rowKey][colonna]` invece che come `colonna[]`: serve quando le righe
 * vanno persistite come struttura annidata (per esempio con `relation()`).
 *
 * I setter `repeater*` configurano solo la UI del blocco (label del bottone
 * "aggiungi", testi della modale di conferma, riordino), e finiscono tutti in
 * `context`, da dove li rilegge il renderer.
 */
class InputRepeater extends Input
{
    protected string $helper = 'inputRepeater';

    /**
     * @param array<int, mixed> $columns Colonne del repeater (`Input` o array descrittori).
     */
    public function columns(array $columns): static
    {
        return $columns !== [] ? $this->context('columns', $columns) : $this;
    }

    public function nested(bool $nested = true): static
    {
        return $this->context('nested', $nested);
    }

    /**
     * Aggancia le righe a una tabella correlata: `Resource::repeaterRelations()`
     * legge questa `RepeaterRelation` per sincronizzare le righe al salvataggio.
     */
    public function relation(object $relation): static
    {
        return $this->context('relation', $relation);
    }

    public function repeaterAddLabel(string $label): static
    {
        return $this->context('add_label', trim($label));
    }

    public function repeaterButtonClass(string $class): static
    {
        return $this->context('add_button_class', trim($class));
    }

    public function repeaterDeleteTitle(string $title): static
    {
        return $this->context('delete_modal_title', trim($title));
    }

    public function repeaterDeleteText(string $text): static
    {
        return $this->context('delete_modal_text', trim($text));
    }

    public function repeaterDeleteCancelLabel(string $label): static
    {
        return $this->context('delete_modal_cancel_label', trim($label));
    }

    public function repeaterDeleteConfirmLabel(string $label): static
    {
        return $this->context('delete_modal_confirm_label', trim($label));
    }

    public function repeaterDeleteConfirmClass(string $class): static
    {
        return $this->context('delete_modal_confirm_class', trim($class));
    }

    public function repeaterSortable(bool $sortable = true): static
    {
        return $this->context('sortable', $sortable);
    }

    protected function element(): ElementField
    {
        $context = (array) ($this->schema['context'] ?? []);

        return (new RepeaterElement($this->name))
            ->columns(is_array($context['columns'] ?? null) ? $context['columns'] : [])
            ->context($context)
            ->value($this->schema['value'] ?? null);
    }
}
