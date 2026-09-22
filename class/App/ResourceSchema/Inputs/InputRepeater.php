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

    /**
     * Le colonne per cui si può raggruppare, nell'ordine del selettore.
     *
     * Dichiararle non raggruppa niente: alla nascita il repeater è piatto, e
     * il raggruppamento lo sceglie chi guarda.
     */
    public function repeaterGroupBy(string ...$columnKeys): static
    {
        $keys = [];

        foreach ($columnKeys as $key) {
            $key = trim($key);

            if ($key !== '' && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys === [] ? $this : $this->context('group_by', $keys);
    }

    /**
     * La casella sulla testata del gruppo: scrive il suo valore in quella
     * colonna di ogni riga del gruppo.
     *
     * È un comando, non un dato: non viene postata e non esiste nel
     * salvataggio.
     */
    public function repeaterGroupCommand(string $columnKey, string $label = ''): static
    {
        $columnKey = trim($columnKey);

        return $columnKey === ''
            ? $this
            : $this->context('group_command', ['column' => $columnKey, 'label' => trim($label)]);
    }

    /**
     * Raggruppa sempre per questa colonna: niente selettore, e i gruppi ci
     * sono anche quando la riga è una sola.
     *
     * `repeaterGroupBy()` offre una comodità a chi guarda; questo dichiara che
     * il raggruppamento **è parte del significato** — le taglie di un colore,
     * le righe di un documento — e che senza non si capisce cosa si sta
     * leggendo. Per questo la tendina non si stampa: non c'è niente da
     * scegliere.
     *
     * Con il raggruppamento fisso il riordino a mano si spegne: le frecce
     * spostano una riga dentro un ordine che i gruppi hanno già deciso.
     */
    public function repeaterGroupFixed(string $columnKey): static
    {
        $columnKey = trim($columnKey);

        return $columnKey === ''
            ? $this
            : $this->context('group_fixed', $columnKey)->context('group_by', [$columnKey]);
    }

    /**
     * Il bottone che aggiunge una riga a mano.
     *
     * Si toglie quando le righe non le scrive chi guarda ma qualcos'altro —
     * una spunta altrove nella pagina, un calcolo — e una riga vuota aggiunta
     * a mano sarebbe solo una riga da cancellare.
     */
    public function repeaterAddButton(bool $visible = true): static
    {
        return $this->context('add_button', $visible);
    }

    /**
     * Senza righe da mostrare, il repeater ne stampa una vuota: con questo
     * non la stampa.
     *
     * La riga finta è comoda in un form che si compila a mano, ma quando le
     * righe arrivano da altrove viene postata comunque, e a valle diventa un
     * record vuoto da riconoscere e scartare.
     */
    public function repeaterStartEmpty(bool $empty = true): static
    {
        return $this->context('start_empty', $empty);
    }

    /**
     * Le colonne che stanno dietro «compila le informazioni avanzate».
     *
     * Una riga chiede tutto quello che si può sapere, ma quasi nessuno lo
     * sa al momento in cui la riga nasce: il codice a barre, lo stato, la
     * foto arrivano dopo. Queste colonne escono dalla riga e vanno in un
     * blocco che si apre da un bottone, a tutta larghezza sotto le altre.
     * La riga resta corta e le caselle tornano larghe quanto devono.
     *
     * Il blocco nasce sempre chiuso, anche su una riga che ha già i suoi
     * codici: molti di quei valori li propone il pannello, e una griglia in
     * cui ogni riga si apre da sola è la griglia lunga da cui si scappava.
     * I valori non si perdono — sono nascosti, non tolti — e il bottone dice
     * cosa c'è sotto.
     */
    public function repeaterAdvanced(string ...$columnKeys): static
    {
        $keys = [];

        foreach ($columnKeys as $key) {
            $key = trim($key);

            if ($key !== '' && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys === [] ? $this : $this->context('advanced', $keys);
    }

    /** Le parole del bottone che apre le colonne avanzate. */
    public function repeaterAdvancedLabel(string $label): static
    {
        return $this->context('advanced_label', trim($label));
    }

    public function repeaterGroupCollapsed(bool $collapsed = true): static
    {
        return $this->context('group_collapsed', $collapsed);
    }

    /** Le parole del conteggio in testata: "4 versioni", "1 versione". */
    public function repeaterGroupCountLabel(string $singular, string $plural): static
    {
        return $this->context('group_count_label', [
            'singular' => trim($singular),
            'plural' => trim($plural),
        ]);
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
