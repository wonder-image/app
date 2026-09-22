<?php

namespace Wonder\Themes\Bootstrap\Form;

use ReflectionClass;
use Wonder\App\LegacyGlobals;
use Wonder\Backend\Support\QuickCreateAuthorizer;
use Wonder\Backend\Support\QuickCreatePanel;
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
    /** Lo script condiviso della creazione rapida va emesso una volta per pagina. */
    private static bool $quickCreateScriptEmitted = false;

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
        $fields = QuickCreatePanel::fields($config);
        $label = QuickCreatePanel::label($config, $fields);
        $body = QuickCreatePanel::bodyHtml($config, $fields);

        try {
            $endpoint = function_exists('__r') ? (string) __r('backend.resource.quick-create') : '';
        } catch (\Throwable) {
            $endpoint = '';
        }

        $hidden = '<input type="hidden" name="resource" value="'.$this->escape($slug).'">'
            .'<input type="hidden" name="quick_label" value="'.$this->escape($label).'">';

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
            // Non un `<form>`: il modal nasce dentro il form della Resource, e
            // un form annidato il browser lo butta via in fase di parsing —
            // restava un bottone submit che salvava il record invece di
            // creare la riga collegata.
            .'<div class="wi-qc-form">'
            .'<div class="modal-header"><h5 class="modal-title">Aggiungi</h5>'
            .'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button></div>'
            .'<div class="modal-body"><div class="wi-qc-alert"></div>'.$hidden.$body.'</div>'
            .'<div class="modal-footer">'
            .'<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>'
            .'<button type="button" class="btn btn-primary wi-qc-submit">Salva</button>'
            .'</div></div></div></div></div>';

        return $trigger.$modal.self::quickCreateScript();
    }

    /**
     * Script condiviso (emesso una volta): invia il modal al proxy backend e,
     * su {id,label}, inserisce+seleziona la nuova opzione con un adapter per
     * famiglia. L'adapter `select` è completo; checkbox/checktree/searchremote/
     * dynamiccheck sono best-effort e vanno rifiniti contro i widget di
     * `wonder-image/lib` in un sito.
     */
    private static function quickCreateScript(): string
    {
        if (self::$quickCreateScriptEmitted) {
            return '';
        }

        self::$quickCreateScriptEmitted = true;

        return <<<'HTML'
<script>
(function () {
  if (window.wiQuickCreateReady) return;
  window.wiQuickCreateReady = true;

  function appendOption(select, id, label) {
    select.appendChild(new Option(label, id, true, true));
    select.value = String(id);
    select.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function optionInto(input, family, id, label) {
    if (!input) {
      document.dispatchEvent(new CustomEvent('wi:quick-create:created', { detail: { id: id, label: label, family: family } }));
      return;
    }
    if ((family === 'select' || family === 'searchremote' || family === 'dynamiccheck') && input.tagName === 'SELECT') {
      appendOption(input, id, label);
      return;
    }
    if (family === 'checktree' && window.jQuery && window.jQuery.fn && window.jQuery.fn.jstree) {
      try {
        var tree = window.jQuery(input).closest('[id]').jstree(true);
        if (tree) { var node = tree.create_node('#', { text: label, li_attr: { 'data-id': id } }); tree.check_node(node); return; }
      } catch (e) {}
    }
    if (family === 'checkbox' || family === 'checktree') {
      var group = input.closest('[data-wi-qc-group]') || input.parentElement || input;
      var wrap = document.createElement('div');
      wrap.className = 'form-check';
      wrap.innerHTML = '<input class="form-check-input" type="checkbox" checked value="' + id + '"> <label class="form-check-label">' + label + '</label>';
      group.appendChild(wrap);
      return;
    }
    // Widget non gestito direttamente: hook per un listener del widget.
    document.dispatchEvent(new CustomEvent('wi:quick-create:created', { detail: { input: input, id: id, label: label, family: family } }));
  }

  /** Svuota i campi del modal: `reset()` era del form che non c'è più. */
  function resetFields(container) {
    var fields = container.querySelectorAll('input:not([type="hidden"]), select, textarea');

    for (var i = 0; i < fields.length; i++) {
      if (fields[i].type === 'checkbox' || fields[i].type === 'radio') { fields[i].checked = false; }
      else { fields[i].value = ''; }
    }
  }

  function showError(modal, msg) {
    var box = modal.querySelector('.wi-qc-alert');
    if (box) { box.innerHTML = '<div class="alert alert-danger py-2 mb-2">' + (msg || 'Errore') + '</div>'; }
    else if (window.alertToast) { window.alertToast('custom', 'error', 'Errore', msg || 'Errore'); }
  }

  /*
   * I modal escono dal form della Resource.
   *
   * Nascono dentro, perché li stampa il renderer del campo, e i loro input
   * verrebbero postati insieme al record: un campo `name` nel modal e uno
   * nella scheda, e vince l'ultimo — il prodotto si ritrovava col nome del
   * marchio che stavi creando. Spostarli in fondo al body li toglie di mezzo.
   */
  function detachModals() {
    var modals = document.querySelectorAll('.modal[data-wi-qc-endpoint]');
    for (var i = 0; i < modals.length; i++) {
      if (modals[i].parentElement !== document.body) { document.body.appendChild(modals[i]); }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', detachModals);
  } else {
    detachModals();
  }

  /** I campi del modal, che non sono più in un form da serializzare. */
  function valuesOf(container) {
    var data = new FormData();
    var fields = container.querySelectorAll('[name]');

    for (var i = 0; i < fields.length; i++) {
      var field = fields[i];
      if (field.disabled) continue;
      if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) continue;

      if (field.type === 'file') {
        for (var f = 0; f < field.files.length; f++) { data.append(field.name, field.files[f]); }
        continue;
      }

      if (field.multiple && field.selectedOptions) {
        for (var o = 0; o < field.selectedOptions.length; o++) { data.append(field.name, field.selectedOptions[o].value); }
        continue;
      }

      data.append(field.name, field.value);
    }

    return data;
  }

  document.addEventListener('click', function (ev) {
    var button = ev.target.closest ? ev.target.closest('.wi-qc-submit') : null;
    if (!button) return;
    ev.preventDefault();
    var form = button.closest('.wi-qc-form');
    var modal = button.closest('.modal');
    if (!form || !modal) return;
    var endpoint = modal.getAttribute('data-wi-qc-endpoint');
    if (!endpoint) { showError(modal, 'Endpoint non configurato.'); return; }

    fetch(endpoint, {
      method: 'POST',
      body: valuesOf(form),
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success) {
          var inputId = modal.getAttribute('data-wi-qc-input');
          optionInto(inputId ? document.getElementById(inputId) : null, modal.getAttribute('data-wi-qc-family'), res.id, res.label);
          resetFields(form);
          var box = modal.querySelector('.wi-qc-alert'); if (box) box.innerHTML = '';
          if (window.bootstrap && window.bootstrap.Modal) {
            var m = window.bootstrap.Modal.getInstance(modal) || new window.bootstrap.Modal(modal);
            m.hide();
          }
        } else {
          showError(modal, res && res.error);
        }
      })
      .catch(function () { showError(modal, 'Errore di rete.'); });
  });
})();
</script>
HTML;
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
