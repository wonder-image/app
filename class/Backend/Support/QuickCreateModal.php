<?php

namespace Wonder\Backend\Support;

use Wonder\App\LegacyGlobals;

/**
 * Il modal della creazione rapida e lo script che lo fa funzionare.
 *
 * Lo usano il "+" attaccato a un campo (il renderer `Field` del tema
 * Bootstrap) e il bottone staccato da un campo (`QuickCreateButton`): stesso
 * markup, stessi permessi, stesso evento `wi:quick-create:created`. Tenerli
 * qui evita due copie che prima o poi divergono. I campi del modal e il suo
 * corpo li risolve `QuickCreatePanel`. Vedi docs/app/concetti/form/quick-create.md.
 */
final class QuickCreateModal
{
    /** Lo script condiviso va stampato una volta per pagina, chiunque lo chieda per primo. */
    private static bool $scriptEmitted = false;

    /**
     * L'utente backend corrente può creare la risorsa della config?
     *
     * Chi non può non vede né il "+" né il bottone: il modal risponderebbe
     * 403 al salvataggio.
     */
    public static function allowed(array $config): bool
    {
        $resource = (string) ($config['resource'] ?? '');

        if ($resource === '') {
            return false;
        }

        $user = LegacyGlobals::get('USER');
        $authority = (array) (is_object($user) ? ($user->authority ?? []) : []);

        return QuickCreateAuthorizer::userCanCreate($resource, $authority);
    }

    /** L'id del modal: `wi-qc-` e il seme ripulito, perché finisce in un selettore. */
    public static function modalId(string $seed): string
    {
        return 'wi-qc-'.preg_replace('/[^a-zA-Z0-9_-]+/', '-', $seed);
    }

    /**
     * I pezzi della creazione rapida: gli attributi di chi apre il modal, il
     * modal e lo script (vuoto se è già uscito). `null` quando l'utente non
     * può creare la risorsa.
     *
     * @param array<string,mixed> $config  la config di `quickCreate(...)` (resource, slug, fields, layout, label, button).
     * @param string              $inputId il campo in cui entra la riga nuova; vuoto per un bottone staccato.
     * @param string              $family  la famiglia del campo per l'adapter JS; `button` per un bottone staccato.
     * @return array{modal_id:string,family:string,button_label:string,trigger:array<string,string>,attributes:string,modal:string,script:string}|null
     */
    public static function parts(array $config, string $inputId, string $family, string $modalId): ?array
    {
        if (!self::allowed($config)) {
            return null;
        }

        $slug = (string) ($config['slug'] ?? '');
        $fields = QuickCreatePanel::fields($config);
        $label = QuickCreatePanel::label($config, $fields);
        $body = QuickCreatePanel::bodyHtml($config, $fields);
        $buttonLabel = QuickCreatePanel::buttonLabel($config);

        try {
            $endpoint = function_exists('__r') ? (string) __r('backend.resource.quick-create') : '';
        } catch (\Throwable) {
            $endpoint = '';
        }

        $hidden = '<input type="hidden" name="resource" value="'.self::escape($slug).'">'
            .'<input type="hidden" name="quick_label" value="'.self::escape($label).'">';

        $trigger = [
            'data-wi-quick-create' => $modalId,
            'data-wi-qc-input' => $inputId,
            'data-wi-qc-family' => $family,
            'data-wi-qc-resource' => $slug,
            'data-bs-toggle' => 'modal',
            'data-bs-target' => '#'.$modalId,
        ];

        $modal = '<div class="modal fade" id="'.self::escape($modalId).'" tabindex="-1" aria-hidden="true"'
            .' data-wi-qc-endpoint="'.self::escape($endpoint).'"'
            .' data-wi-qc-input="'.self::escape($inputId).'"'
            .' data-wi-qc-family="'.self::escape($family).'"'
            // Quale risorsa nasce da qui: serve a chi, nella stessa pagina,
            // elenca le stesse righe e deve accorgersi della nuova.
            .' data-wi-qc-resource="'.self::escape($slug).'">'
            .'<div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
            // Non un `<form>`: il modal nasce dentro il form della Resource, e
            // un form annidato il browser lo butta via in fase di parsing —
            // restava un bottone submit che salvava il record invece di
            // creare la riga collegata.
            .'<div class="wi-qc-form">'
            .'<div class="modal-header"><h5 class="modal-title">'.self::escape($buttonLabel).'</h5>'
            .'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button></div>'
            .'<div class="modal-body"><div class="wi-qc-alert"></div>'.$hidden.$body.'</div>'
            .'<div class="modal-footer">'
            .'<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>'
            .'<button type="button" class="btn btn-primary wi-qc-submit">Salva</button>'
            .'</div></div></div></div></div>';

        return [
            'modal_id' => $modalId,
            'family' => $family,
            'button_label' => $buttonLabel,
            'trigger' => $trigger,
            'attributes' => self::attributes($trigger),
            'modal' => $modal,
            'script' => self::script(),
        ];
    }

    /**
     * Lo script condiviso, la prima volta che si chiede; poi stringa vuota.
     *
     * Il flag è di processo: il primo fra il "+" di un campo e un bottone
     * staccato lo stampa, gli altri no. Nel browser c'è anche la guardia
     * `window.wiQuickCreateReady`, per le pagine che lo ricevono due volte.
     */
    public static function script(): string
    {
        if (self::$scriptEmitted) {
            return '';
        }

        self::$scriptEmitted = true;

        return self::scriptSource();
    }

    /**
     * Lo script per intero, anche se è già uscito: per chi lo prova o lo
     * serve da sé.
     *
     * Invia il modal al proxy backend e, sulla risposta {id, label, item},
     * inserisce e seleziona la nuova opzione con un adapter per famiglia.
     * L'adapter `select` è completo; checkbox/checktree/searchremote/
     * dynamiccheck sono best-effort e li rifinisce la lib. In ogni caso
     * emette `wi:quick-create:created` con
     * `{input, id, label, family, resource, item, trigger}`.
     */
    public static function scriptSource(): string
    {
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

  /*
   * L'elemento in cui infilare l'opzione nuova.
   *
   * Un `select` porta l'id del campo; un gruppo di caselle, sul tema
   * Bootstrap, lo mette sul contenitore con il prefisso `container-`, e
   * cercare solo l'id nudo non trova niente.
   */
  function targetInput(inputId) {
    if (!inputId) return null;

    return document.getElementById(inputId)
      || document.getElementById('container-' + inputId)
      || document.querySelector('[name="' + inputId + '"], [name="' + inputId + '[]"]');
  }

  /*
   * Il nome da dare alla casella nuova: quello di una casella che c'è già,
   * altrimenti quello del campo nascosto del gruppo. Senza nome la spunta
   * non verrebbe postata e il valore appena creato si perderebbe al primo
   * salvataggio.
   */
  function checkName(group) {
    var gemella = group.querySelector('input[type="checkbox"][name]') || group.querySelector('input[type="hidden"][name]');
    return gemella ? gemella.getAttribute('name') : '';
  }

  /*
   * Una pillola in più, uguale alle altre: casella `btn-check` e label
   * collegata per `for`. Va prima del "+", che resta l'ultimo della fila.
   */
  function appendPill(group, id, label) {
    var name = checkName(group);
    var fila = group.querySelector('.d-flex') || group;
    var gemella = group.querySelector('input.btn-check');
    var input = gemella ? gemella.cloneNode(false) : document.createElement('input');

    if (!gemella) {
      input.className = 'btn-check';
      input.type = 'checkbox';
      input.setAttribute('autocomplete', 'off');
      input.setAttribute('data-wi-check', 'true');
    }

    input.setAttribute('name', name);
    input.value = String(id);
    input.id = (input.type || 'checkbox') + '-' + name + '-' + id;
    input.checked = true;

    var testo = document.createElement('label');
    testo.className = 'btn btn-sm btn-outline-secondary wi-check-label user-select-none';
    testo.htmlFor = input.id;
    testo.textContent = label;

    var piu = fila.querySelector('.wi-qc-inline');
    fila.insertBefore(input, piu);
    fila.insertBefore(testo, piu);

    return input;
  }

  function appendCheck(group, id, label) {
    var pillole = group.classList.contains('wi-check-pills') ? group : group.querySelector('.wi-check-pills');
    var input;

    if (pillole) {
      input = appendPill(pillole, id, label);
    } else {
      input = document.createElement('input');
      input.className = 'form-check-input';
      input.type = 'checkbox';
      input.setAttribute('name', checkName(group));
      input.value = String(id);
      input.checked = true;

      var testo = document.createElement('label');
      testo.className = 'form-check-label';
      testo.textContent = label;

      var wrap = document.createElement('div');
      wrap.className = 'form-check';
      wrap.appendChild(input);
      wrap.appendChild(document.createTextNode(' '));
      wrap.appendChild(testo);

      // In fondo all'elenco, non dopo la prima casella: l'opzione nuova è
      // l'ultima arrivata e lì la si cerca.
      var caselle = group.querySelectorAll('.form-check');
      var ultima = caselle.length ? caselle[caselle.length - 1] : null;
      (ultima && ultima.parentElement ? ultima.parentElement : group).appendChild(wrap);
    }

    // Una spunta nuova è una spunta come le altre: chi ascolta i cambi del
    // gruppo (una griglia che nasce dalle spunte, un contatore) deve saperlo.
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  /*
   * Inserimento "baseline", che funziona senza wonder-image/lib:
   *   - <select> semplice: aggiunge e seleziona l'opzione;
   *   - gruppo di checkbox semplice: appende una casella spuntata.
   * I widget potenziati dalla lib (select2, card dinamica, jstree) NON vengono
   * toccati qui: si emette sempre `wi:quick-create:created` e li gestisce
   * l'adapter della lib (src/build/backend/js/form/quickCreate.js), che sa
   * ridisegnarli. Nell'albero crea il nodo con l'API di jstree: la casella
   * da postare la scrive la lib in `[data-wi-tree-values]`, fuori dai nodi.
   *
   * Il bottone staccato da un campo (`QuickCreateButton`) non ha un input:
   * l'evento parte lo stesso, con `input: null`, e la riga nuova la mette
   * in pagina chi lo ascolta, leggendola da `item`. `trigger` è il bottone
   * che ha aperto il modal: con due bottoni della stessa risorsa nella
   * pagina, dice quale dei due.
   */
  function optionInto(input, family, id, label, resource, item, trigger) {
    if (input) {
      if (input.tagName === 'SELECT' && !input.matches('[data-wi-select-search]')) {
        appendOption(input, id, label);
      } else if (family === 'checkbox') {
        appendCheck(input.closest('[data-wi-qc-group]') || input, id, label);
      }
    }
    document.dispatchEvent(new CustomEvent('wi:quick-create:created', { detail: { input: input || null, id: id, label: label, family: family, resource: resource || '', item: item || {}, trigger: trigger || null } }));
  }

  /*
   * Rimette i campi del modal com'erano all'apertura della pagina: `reset()`
   * era del form che non c'è più. Non li svuota: un campo con un valore
   * proposto (un tipo, uno stato) deve riproporlo alla creazione dopo.
   *
   * Gli alberi restano come sono, con le caselle che postano
   * (`[data-wi-tree-values]`): jstree non rilegge le caselle, e rimetterle a
   * posto sotto un albero che mostra altro posterebbe un valore diverso da
   * quello che si vede. Tenere la scelta serve anche: si creano di fila più
   * figli dello stesso genitore.
   */
  function resetFields(container) {
    var fields = container.querySelectorAll('input:not([type="hidden"]), select, textarea');

    for (var i = 0; i < fields.length; i++) {
      var field = fields[i];
      if (field.closest('[data-wi-tree], [data-wi-tree-values]')) continue;

      if (field.type === 'checkbox' || field.type === 'radio') { field.checked = field.defaultChecked; }
      else if (field.tagName === 'SELECT') {
        for (var o = 0; o < field.options.length; o++) { field.options[o].selected = field.options[o].defaultSelected; }
      } else if (field.type !== 'file') { field.value = field.defaultValue; }
      else { field.value = ''; }
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

  /*
   * Campi non validi del modal. Il modal non è un <form>, quindi la validazione
   * HTML5 non parte da sola: la interroghiamo per campo con `checkValidity()`.
   * È il primo argine alla riga vuota (il secondo, decisivo, è lato server).
   */
  function invalidFields(container) {
    var out = [];
    var fields = container.querySelectorAll('input, select, textarea');

    for (var i = 0; i < fields.length; i++) {
      var field = fields[i];
      if (field.disabled || field.type === 'hidden') { continue; }
      if (typeof field.checkValidity === 'function' && !field.checkValidity()) { out.push(field); }
    }

    return out;
  }

  // Toglie il rosso mentre l'utente corregge.
  document.addEventListener('input', function (ev) {
    var t = ev.target;
    if (t && t.classList && t.classList.contains('is-invalid') && t.closest && t.closest('.wi-qc-form')) {
      t.classList.remove('is-invalid');
    }
  });

  document.addEventListener('click', function (ev) {
    var button = ev.target.closest ? ev.target.closest('.wi-qc-submit') : null;
    if (!button) return;
    ev.preventDefault();
    var form = button.closest('.wi-qc-form');
    var modal = button.closest('.modal');
    if (!form || !modal) return;
    var endpoint = modal.getAttribute('data-wi-qc-endpoint');
    if (!endpoint) { showError(modal, 'Endpoint non configurato.'); return; }

    var invalid = invalidFields(form);
    if (invalid.length) {
      for (var n = 0; n < invalid.length; n++) { invalid[n].classList.add('is-invalid'); }
      showError(modal, 'Compila i campi obbligatori.');
      if (invalid[0].reportValidity) { invalid[0].reportValidity(); } else if (invalid[0].focus) { invalid[0].focus(); }
      return;
    }

    fetch(endpoint, {
      method: 'POST',
      body: valuesOf(form),
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success) {
          var trigger = modal.id ? document.querySelector('[data-wi-quick-create="' + modal.id + '"]') : null;
          optionInto(targetInput(modal.getAttribute('data-wi-qc-input')), modal.getAttribute('data-wi-qc-family'), res.id, res.label, modal.getAttribute('data-wi-qc-resource'), res.item, trigger);
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

    /** @param array<string,string> $attributes */
    private static function attributes(array $attributes): string
    {
        $html = [];

        foreach ($attributes as $key => $value) {
            $html[] = $key.'="'.self::escape($value).'"';
        }

        return implode(' ', $html);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
