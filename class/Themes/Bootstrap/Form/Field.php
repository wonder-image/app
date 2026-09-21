<?php

namespace Wonder\Themes\Bootstrap\Form;

use ReflectionClass;
use Wonder\App\LegacyGlobals;
use Wonder\Backend\Support\QuickCreateAuthorizer;
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

        return $html.$this->renderQuickCreate();
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
     * "Creazione rapida": se l'input dichiara `quickCreate(...)` e l'utente
     * backend corrente può creare la risorsa target, emette un "+" e un modal
     * Bootstrap con il sottoinsieme di campi (resi dal form della risorsa
     * target). Stringa vuota per ogni altro campo. Vedi
     * docs/app/concetti/form/quick-create.md.
     */
    protected function renderQuickCreate(): string
    {
        $config = $this->schema['context']['quick_create'] ?? null;

        if (!is_array($config) || empty($config['resource'])) {
            return '';
        }

        $user = LegacyGlobals::get('USER');
        $authority = (array) (is_object($user) ? ($user->authority ?? []) : []);

        if (!QuickCreateAuthorizer::userCanCreate($config['resource'], $authority)) {
            return '';
        }

        $inputId = (string) ($this->schema['id'] ?? $this->schema['name'] ?? '');
        $seed = $inputId !== '' ? $inputId : uniqid('f');
        $modalId = 'wi-qc-'.preg_replace('/[^a-zA-Z0-9_-]+/', '-', $seed);
        $family = $this->quickCreateFamily();
        $slug = (string) $config['slug'];
        $fields = array_values(array_filter((array) $config['fields'], 'is_string'));
        $label = (string) ($config['label'] ?? '');

        try {
            $endpoint = function_exists('__r') ? (string) __r('backend.resource.quick-create') : '';
        } catch (\Throwable) {
            $endpoint = '';
        }

        $hidden = '<input type="hidden" name="resource" value="'.$this->escape($slug).'">'
            .'<input type="hidden" name="quick_label" value="'.$this->escape($label).'">';
        $body = '';

        foreach ($fields as $key) {
            $hidden .= '<input type="hidden" name="quick_fields[]" value="'.$this->escape($key).'">';
            $body .= $config['resource']::getInput($key)->render('bootstrap');
        }

        $trigger = '<button type="button" class="btn btn-outline-secondary btn-sm mt-1"'
            .' data-wi-quick-create="'.$this->escape($modalId).'"'
            .' data-wi-qc-input="'.$this->escape($inputId).'"'
            .' data-wi-qc-family="'.$this->escape($family).'"'
            .' data-bs-toggle="modal" data-bs-target="#'.$this->escape($modalId).'">'
            .'<i class="bi bi-plus-lg"></i> Aggiungi</button>';

        $modal = '<div class="modal fade" id="'.$this->escape($modalId).'" tabindex="-1" aria-hidden="true"'
            .' data-wi-qc-endpoint="'.$this->escape($endpoint).'"'
            .' data-wi-qc-input="'.$this->escape($inputId).'"'
            .' data-wi-qc-family="'.$this->escape($family).'">'
            .'<div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
            .'<form class="wi-qc-form" onsubmit="return false">'
            .'<div class="modal-header"><h5 class="modal-title">Aggiungi</h5>'
            .'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button></div>'
            .'<div class="modal-body"><div class="wi-qc-alert"></div>'.$hidden.$body.'</div>'
            .'<div class="modal-footer">'
            .'<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>'
            .'<button type="submit" class="btn btn-primary">Salva</button>'
            .'</div></form></div></div></div>';

        return $trigger.$modal;
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
