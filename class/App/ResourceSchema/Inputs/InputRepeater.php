<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\Repeater as RepeaterElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Blocco di righe ripetibili.
 *
 * Le colonne sono `Input`, tipicamente dichiarati con `RepeaterColumn::key()`.
 * Gli array descrittori `['name' => ..., 'helper' => ..., ...]` sono deprecati:
 * il renderer Bootstrap li converte ancora in `RepeaterColumn`.
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
     * @param array<int, mixed> $columns Colonne del repeater (`Input`; gli array descrittori sono deprecati).
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

    /**
     * Le righe si riordinano: una maniglia a sinistra si trascina, e con il
     * fuoco sopra le frecce su e giù spostano la riga di un posto.
     */
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
     * Un campo file sulla testata del gruppo: i file stanno al gruppo, non
     * alle sue righe — le foto di un colore valgono per tutte le sue taglie.
     *
     * `$field` è un campo file (di solito `fileDragDrop()`) il cui valore è la
     * mappa `chiave del gruppo → nomi dei file`. La chiave si legge da
     * `$keyColumn` nella prima riga del gruppo, perché la colonna che
     * raggruppa porta un'etichetta, non un identificativo; un gruppo con la
     * chiave vuota non ha il campo. Si posta come `campo[chiave][]` con il
     * manifesto `campo[chiave__wi_files]`, e lo rilegge
     * `Support\Repeater::groupFilesFromRequest()`.
     *
     * Vale con `repeaterGroupFixed()`: con una colonna scelta da chi guarda
     * un gruppo potrebbe mescolare righe di chiavi diverse.
     */
    public function repeaterGroupFiles(Input $field, string $keyColumn, string $label = 'Foto'): static
    {
        $keyColumn = trim($keyColumn);

        return $keyColumn === ''
            ? $this
            : $this->context('group_files', ['field' => $field, 'key_column' => $keyColumn, 'label' => trim($label)]);
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
     * Con il raggruppamento fisso il riordino a mano si spegne: la maniglia
     * sposterebbe una riga dentro un ordine che i gruppi hanno già deciso.
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
     * Il cestino che toglie una riga.
     *
     * Si toglie quando levare la riga dal form non cancella niente: le righe
     * arrivano da altrove e chi salva le aggiorna soltanto. Senza cestino
     * non si stampa né nelle righe né nel modello.
     */
    public function repeaterDeleteButton(bool $visible = true): static
    {
        return $this->context('delete_button', $visible);
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
     * Il numero minimo di righe: quando ne restano tante, il bottone che
     * elimina si spegne. Serve dove un record senza righe non ha senso (una
     * scheda senza nessun contatto). È un aiuto a schermo: la regola vera la
     * controlla chi salva.
     */
    public function repeaterMinRows(int $rows): static
    {
        return $this->context('min_rows', max(0, $rows));
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

    /**
     * Le righe diventano schede che si chiudono su un riassunto.
     *
     * Una riga salvata si legge in una riga sola (il nome, poi il resto in
     * piccolo) e si apre con un clic per modificarla. Le colonne del titolo
     * si uniscono con uno spazio, quelle del sottotitolo con «·»; di una
     * select si legge l'etichetta scelta. Una riga nuova, o senza titolo,
     * nasce aperta, come quelle che tornano da un salvataggio rifiutato.
     *
     * I campi di una riga chiusa sono nascosti, non spenti: vengono postati
     * come sempre.
     *
     * @param string|list<string> $title
     * @param string|list<string> $subtitle
     */
    public function repeaterCollapsible(string|array $title, string|array $subtitle = []): static
    {
        $keys = static fn (string|array $columns): array => array_values(array_unique(array_filter(
            array_map(static fn ($key): string => trim((string) $key), (array) $columns),
            static fn (string $key): bool => $key !== ''
        )));

        $title = $keys($title);

        return $title === [] ? $this : $this->context('collapsible', [
            'title' => $title,
            'subtitle' => $keys($subtitle),
        ]);
    }

    /** Il titolo di una scheda che non ha ancora niente da riassumere. */
    public function repeaterCollapsibleEmptyLabel(string $label): static
    {
        return $this->context('collapsible_empty', trim($label));
    }

    /**
     * Eliminare una riga la spegne invece di toglierla.
     *
     * La riga resta a schermo, sbiadita, con un bottone per ripensarci: i
     * suoi campi stanno in un `fieldset` che si disabilita, e un fieldset
     * spento non viene postato — che per il server vuol dire «questa riga
     * non c'è più», la stessa cosa che diceva la riga tolta dal DOM.
     *
     * Serve dove cancellare è una decisione che si rimpiange: una riga
     * appena eliminata per sbaglio, senza questo, si ricostruisce a mano.
     */
    public function repeaterUndoDelete(bool $undo = true): static
    {
        return $this->context('undo_delete', $undo);
    }

    /** Le parole del bottone che rimette la riga, e della frase accanto. */
    public function repeaterUndoLabel(string $label, string $text = ''): static
    {
        return $this->context('undo_label', trim($label))
            ->context('undo_text', trim($text));
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
