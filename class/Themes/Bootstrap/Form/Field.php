<?php

namespace Wonder\Themes\Bootstrap\Form;

use ReflectionClass;
use Wonder\Backend\Support\QuickCreateModal;
use Wonder\Themes\Form\AbstractFieldRenderer;

/**
 * Renderer base per i field del tema `Bootstrap` (backend admin).
 *
 * Gli helper condivisi con Wonder vivono in `AbstractFieldRenderer`.
 * Qui restano solo le funzioni che producono markup Bootstrap 5
 * (`form-control`, `form-floating`, `invalid-feedback`).
 */
abstract class Field extends AbstractFieldRenderer
{
    /**
     * I pezzi della creazione rapida del campo che si sta disegnando.
     *
     * Le pillole li chiedono dentro `renderInput()`, per mettere il "+" in
     * fila; `renderField()` li richiede per il modal. Calcolarli due volte
     * vorrebbe dire disegnare due volte il corpo del modal e perdere lo
     * script, che esce una volta sola: si tengono per lo schema che li ha
     * prodotti.
     *
     * @var array{schema:array<string,mixed>,parts:?array}|null
     */
    private ?array $quickCreateMemo = null;

    /**
     * Override del render del parent: prima di costruire il wrap,
     * leggiamo `isNoFloating()` per decidere se applicare il pattern
     * `form-floating`. Questo permette ai consumer di usare
     * `Field::noFloating()` (o `Form::noFloating()` propagato ai
     * children) per disattivare il floating in modo dichiarativo,
     * coerentemente con la classe `wi-nf` lato Wonder.
     */
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderField($this->renderInput(), !$this->isNoFloating());
    }

    /**
     * Hook di wrapping del tema. Bootstrap usa il pattern
     * `form-floating` di default: l'input prima, poi la label,
     * il tutto in un div container. Sotto, l'errore.
     *
     * I componenti che NON funzionano col floating (es. Checkbox,
     * File) passano `$floating = false` per ottenere un wrap minimale.
     *
     * Tutti i componenti FK passano di qui (direttamente o via `render()`),
     * quindi è il punto unico dove appendere la "creazione rapida".
     */
    protected function renderField(string $input, bool $floating = true): string
    {
        $quick = $this->quickCreateParts();

        // Su un controllo singolo (select/selectSearch/searchRemote) il "+" è
        // attaccato all'input a destra, dentro un `input-group`. Col floating il
        // label sta dentro il `form-floating`; senza (noFloating), sopra.
        if ($quick !== null && in_array($quick['family'], ['select', 'searchremote'], true)) {
            $button = '<button type="button" class="btn btn-outline-secondary" '.$quick['attributes'].'>'
                .'<i class="bi bi-plus-lg"></i> Aggiungi</button>';

            if ($floating) {
                $control = '<div class="input-group">'
                    .'<div class="form-floating">'.$input.$this->renderLabel().'</div>'
                    .$button
                    .'</div>';
            } else {
                $control = $this->renderLabel()
                    .'<div class="input-group">'.$input.$button.'</div>';
            }

            return '<div>'.$control.$this->renderError().'</div>'.$quick['modal'].$quick['script'];
        }

        if ($floating) {
            $html = '<div><div class="form-floating">'
                .$input
                .$this->renderLabel()
                .'</div>'
                .$this->renderError()
                .'</div>';
        } else {
            $html = '<div>'.$input.$this->renderError().'</div>';
        }

        // Le pillole hanno già il loro "+" in fila, stampato dal componente
        // (`inlineQuickCreateButton()`): qui resta solo il modal.
        if ($quick !== null && !empty($this->schema['pills'])) {
            return $html.$quick['modal'].$quick['script'];
        }

        // Gruppi (checkbox/checkTree/dynamicCheck): niente singolo controllo a
        // cui attaccarsi. Il "+" diventa una testata "Aggiungi <Nome>" in alto a
        // destra, sulla riga del titolo `<h6>` che il componente stampa dentro
        // il gruppo (bottone `position-absolute`, wrapper `position-relative`).
        if ($quick !== null) {
            $button = '<button type="button"'
                .' class="btn btn-sm btn-link p-0 text-decoration-none position-absolute top-0 end-0 wi-qc-header" '
                .$quick['attributes'].'>'
                .'<i class="bi bi-plus-lg"></i> '.$this->escape($quick['button_label']).'</button>';
            $html = '<div class="position-relative">'.$button.$html.'</div>'.$quick['modal'].$quick['script'];
        }

        return $html;
    }

    /**
     * Il "+" come ultima pillola della riga.
     *
     * Chi disegna le pillole lo mette in fondo, dopo le voci: la voce nuova
     * nasce accanto alle altre e il bottone resta lì, pronto per la
     * successiva. Stringa vuota quando il campo non ha creazione rapida o
     * l'utente non può creare la risorsa.
     */
    protected function inlineQuickCreateButton(): string
    {
        $quick = $this->quickCreateParts();

        if ($quick === null) {
            return '';
        }

        return '<button type="button" class="btn btn-sm btn-outline-primary wi-qc-inline" style="border-style:dashed" '
            .$quick['attributes'].'>'
            .'<i class="bi bi-plus-lg"></i> '.$this->escape($quick['button_label']).'</button>';
    }

    /**
     * Markup label tema Bootstrap: `<label>`. Skip se la label è
     * vuota (alcuni componenti come Hidden non hanno label).
     */
    protected function renderLabel(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $label = $this->resolvedLabel();

        if ($label === '') {
            return '';
        }

        return '<label for="'.$id.'">'.$this->escape($label).'</label>';
    }

    /**
     * Markup errore tema Bootstrap: div `invalid-feedback`. Quando
     * c'è errore aggiunge `d-block` per forzare la visibilità
     * (di default Bootstrap mostra `invalid-feedback` solo sui
     * sibling `.is-invalid`, ma noi vogliamo mostrarlo sempre).
     */
    protected function renderError(): string
    {
        $error = $this->errorMessage();
        $class = $error !== '' ? 'invalid-feedback d-block' : 'invalid-feedback';

        return '<div class="'.$class.'">'.$this->escape($error).'</div>';
    }

    /**
     * Aggiunge `is-invalid` alla classe base quando c'è errore di
     * validazione, in modo che Bootstrap renderizzi il bordo rosso.
     */
    protected function inputClass(string $base): string
    {
        return trim($base.($this->hasError() ? ' is-invalid' : ''));
    }

    /**
     * Pezzi della "creazione rapida" quando l'input dichiara `quickCreate(...)`
     * e l'utente backend corrente può creare la risorsa target: family,
     * attributi del trigger, modal e script. `null` per ogni altro campo — la
     * posizione del "+" la decide `renderField()`. Vedi
     * docs/app/concetti/form/quick-create.md.
     *
     * @return array{modal_id:string,family:string,button_label:string,trigger:array<string,string>,attributes:string,modal:string,script:string}|null
     */
    protected function quickCreateParts(): ?array
    {
        if ($this->quickCreateMemo !== null && $this->quickCreateMemo['schema'] === $this->schema) {
            return $this->quickCreateMemo['parts'];
        }

        $parts = $this->buildQuickCreateParts();
        $this->quickCreateMemo = ['schema' => $this->schema, 'parts' => $parts];

        return $parts;
    }

    /**
     * Il modal e lo script sono gli stessi del bottone staccato
     * (`QuickCreateButton`): li costruisce `QuickCreateModal`, qui si
     * decidono solo il campo bersaglio e la famiglia.
     *
     * @return array{modal_id:string,family:string,button_label:string,trigger:array<string,string>,attributes:string,modal:string,script:string}|null
     */
    private function buildQuickCreateParts(): ?array
    {
        $config = $this->schema['context']['quick_create'] ?? null;

        if (!is_array($config) || empty($config['resource'])) {
            return null;
        }

        $inputId = (string) ($this->schema['id'] ?? $this->schema['name'] ?? '');
        $seed = $inputId !== '' ? $inputId : uniqid('f');

        return QuickCreateModal::parts($config, $inputId, $this->quickCreateFamily(), QuickCreateModal::modalId($seed));
    }

    /** Famiglia dell'input FK (per l'adapter JS), dedotta dal renderer concreto. */
    private function quickCreateFamily(): string
    {
        $base = strtolower((new ReflectionClass($this))->getShortName());

        return match (true) {
            str_contains($base, 'dynamiccheck') => 'dynamiccheck',
            str_contains($base, 'checktree')    => 'checktree',
            str_contains($base, 'check')        => 'checkbox',
            str_contains($base, 'search')       => 'searchremote',
            default                             => 'select',
        };
    }
}
