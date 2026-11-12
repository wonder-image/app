<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\App\Media\MediaInputs;
use Wonder\App\Media\MediaTypes;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Modal;
use Wonder\Themes\Bootstrap\Form\Field;
use Wonder\Themes\Support\PageAssets;

/**
 * Il campo media del backend: l'id in un input nascosto, l'anteprima del
 * media scelto e tre azioni (scegli dalla libreria, carica un file, togli).
 *
 * Il file nuovo parte con il form (`<campo>__wi_media_upload`): il media
 * lo crea il server quando si salva il record. La finestra della libreria
 * è una sola per pagina, un `Modal` condiviso da tutti i campi; CSS e
 * script escono una volta da `PageAssets` e leggono solo il contratto
 * `data-wi-media*`, che la lib può prendere in carico senza cambiare il
 * markup.
 */
class MediaPicker extends Field
{
    public const MODAL_ID = 'wi-media-picker';

    private const ICONS = [
        MediaTypes::IMAGE => 'bi-image',
        MediaTypes::ICON => 'bi-grid-1x2',
        MediaTypes::DOCUMENT => 'bi-file-earmark-text',
        MediaTypes::VIDEO => 'bi-film',
    ];

    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderField($this->renderInput(), false).$this->assets();
    }

    public function renderInput(): string
    {
        $type = (string) ($this->schema['media_type'] ?? MediaTypes::IMAGE);
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = trim((string) ($this->schema['value'] ?? ''));
        $media = is_array($this->schema['media'] ?? null) ? $this->schema['media'] : null;
        $attributes = (array) ($this->schema['attributes'] ?? []);
        $locked = !empty($attributes['disabled']) || !empty($attributes['readonly']);
        $required = !empty($attributes['required']);
        $label = $this->resolvedLabel();
        $icon = self::ICONS[$type] ?? 'bi-file-earmark';

        // Un id senza media: quello scelto non è più in libreria.
        $missing = $value !== '' && $media === null;
        $thumbnail = $media !== null ? trim((string) ($media['thumbnail'] ?? '')) : '';
        $title = $media !== null
            ? (string) ($media['name'] ?? '')
            : ($missing ? 'Media non trovato' : 'Nessun file scelto');
        $note = $missing ? 'Il media scelto non è più in libreria: scegline un altro o toglilo.' : '';

        $root = ['wi-media-field'];
        $root[] = $value === '' ? 'is-empty' : '';
        $root[] = $this->hasError() ? 'is-invalid' : '';

        $accept = $this->escape(implode(',', array_map(
            static fn (string $extension): string => '.'.$extension,
            MediaTypes::extensions($type)
        )));
        $formats = $this->escape(implode(', ', MediaTypes::extensions($type)).'. Peso massimo '.MediaTypes::maxSize($type).' MB.');
        $search = $this->escape((string) ($this->schema['search_url'] ?? ''));
        $disabled = $locked ? ' disabled' : '';

        $labelHtml = $label !== ''
            ? '<label class="h6 form-label" for="'.$id.'-upload">'.$this->escape($label).'</label>'
            : '';
        $image = '<img class="wi-media-thumbnail" src="'.$this->escape($thumbnail).'" alt="" data-wi-media-thumbnail'
            .($thumbnail === '' ? ' hidden' : '').'>';
        $glyph = '<i class="bi '.$icon.' wi-media-glyph" aria-hidden="true" data-wi-media-glyph'
            .($thumbnail !== '' ? ' hidden' : '').'></i>';
        $remove = '<button type="button" class="btn btn-sm btn-outline-danger" data-wi-media-remove'
            .($value === '' ? ' hidden' : '').$disabled.'><i class="bi bi-x-lg" aria-hidden="true"></i> Togli</button>';

        return $labelHtml
            .'<div class="'.$this->escape(trim(implode(' ', array_filter($root)))).'" data-wi-media="'.$this->escape($type).'"'
            .' data-wi-media-search="'.$search.'" data-wi-media-title="'.$this->escape($this->pickerTitle($type)).'"'
            .($required ? ' data-wi-media-required="true"' : '').'>'
            .'<input type="hidden" class="'.$this->fieldClass('wi-media-value').'" id="'.$id.'" name="'.$name.'"'
            .' value="'.$this->escape($value).'" data-wi-media-value'.$this->fieldAttributes($this->hiddenWritten()).'>'
            .'<div class="wi-media-preview" data-wi-media-preview>'
            .'<div class="wi-media-frame">'.$image.$glyph.'</div>'
            .'<div class="wi-media-text">'
            .'<div class="wi-media-name" data-wi-media-name data-wi-media-empty-text="Nessun file scelto">'.$this->escape($title).'</div>'
            .'<div class="wi-media-note" data-wi-media-note data-wi-media-new-text="File nuovo: entra in libreria quando salvi."'
            .' data-wi-media-required-text="Scegli un file dalla libreria o caricane uno.">'.$this->escape($note).'</div>'
            .'</div>'
            .'</div>'
            .'<div class="wi-media-actions">'
            .'<button type="button" class="btn btn-sm btn-outline-secondary" data-wi-media-choose'.$disabled.'>'
            .'<i class="bi bi-images" aria-hidden="true"></i> Scegli dalla libreria</button>'
            .'<label class="btn btn-sm btn-outline-secondary mb-0'.($locked ? ' disabled' : '').'" for="'.$id.'-upload">'
            .'<i class="bi bi-upload" aria-hidden="true"></i> Carica un file</label>'
            .'<input type="file" class="visually-hidden" id="'.$id.'-upload" name="'.$name.MediaInputs::UPLOAD_SUFFIX.'"'
            .' accept="'.$accept.'" data-wi-media-upload'.$disabled.'>'
            .$remove
            .'</div>'
            .'<div class="form-text">File ammessi: '.$formats.'</div>'
            .'</div>';
    }

    /** Titolo della finestra della libreria per il tipo del campo. */
    private function pickerTitle(string $type): string
    {
        return match ($type) {
            MediaTypes::ICON => 'Scegli un\'icona',
            MediaTypes::DOCUMENT => 'Scegli un documento',
            MediaTypes::VIDEO => 'Scegli un video',
            default => 'Scegli un\'immagine',
        };
    }

    /**
     * Le chiavi che l'input nascosto scrive già, più quelle che su un
     * input nascosto non hanno senso: l'obbligo lo porta la radice del
     * campo (`data-wi-media-required`).
     *
     * @return string[]
     */
    private function hiddenWritten(): array
    {
        return ['type', 'id', 'name', 'value', 'data-wi-media-value', 'required', 'readonly', 'placeholder'];
    }

    /** Finestra, stile e script: una volta per pagina, dal primo campo che li chiede. */
    private function assets(): string
    {
        return PageAssets::once('wi-media-field', static fn (): string => self::modal().self::style().self::script());
    }

    /**
     * La finestra della libreria, senza `<form>`: lo script di `Modal` la
     * porta fuori dal form del record.
     */
    public static function modal(): string
    {
        $body = '<div class="col-12" data-wi-media-picker>'
            .'<input type="search" class="form-control" placeholder="Cerca per nome" aria-label="Cerca nella libreria"'
            .' autocomplete="off" data-wi-media-term>'
            .'<div class="wi-media-grid" data-wi-media-results></div>'
            .'<p class="wi-media-message" data-wi-media-message data-wi-media-empty-text="Nessun media trovato."'
            .' data-wi-media-loading-text="Caricamento in corso" data-wi-media-error-text="La libreria non risponde: riprova." hidden></p>'
            .'<div class="wi-media-pager" data-wi-media-pager hidden>'
            .'<button type="button" class="btn btn-sm btn-outline-secondary" data-wi-media-page="previous">Precedenti</button>'
            .'<span class="wi-media-count" data-wi-media-count data-wi-media-count-text="Pagina {page} di {pages}"></span>'
            .'<button type="button" class="btn btn-sm btn-outline-secondary" data-wi-media-page="next">Successivi</button>'
            .'</div>'
            .'</div>';

        return Modal::make('Scegli dalla libreria')
            ->id(self::MODAL_ID)
            ->size('lg')
            ->scrollable()
            ->columns(12)
            ->components([$body])
            ->footer([
                Button::make('Annulla')->variant('secondary')->attr('data-bs-dismiss', 'modal'),
            ])
            ->render('bootstrap');
    }

    public static function style(): string
    {
        return <<<'HTML'
<style data-wi-media-style>
.wi-media-field{display:flex;flex-direction:column;gap:.5rem}
.wi-media-preview{display:flex;align-items:center;gap:.75rem;padding:.5rem;border:1px solid var(--bs-border-color);border-radius:var(--bs-border-radius);background:var(--bs-body-bg);min-width:0}
.wi-media-field.is-invalid .wi-media-preview{border-color:var(--bs-form-invalid-border-color)}
.wi-media-frame{flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:4.5rem;height:4.5rem;border-radius:var(--bs-border-radius-sm);background:var(--bs-tertiary-bg);overflow:hidden}
.wi-media-thumbnail{width:100%;height:100%;object-fit:contain}
.wi-media-glyph{font-size:1.75rem;color:var(--bs-secondary-color)}
.wi-media-text{flex:1 1 auto;min-width:0}
.wi-media-name{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.wi-media-field.is-empty .wi-media-name{font-weight:400;color:var(--bs-secondary-color)}
.wi-media-note{font-size:.875em;color:var(--bs-secondary-color)}
.wi-media-note:empty{display:none}
.wi-media-field.is-invalid .wi-media-note{color:var(--bs-form-invalid-color)}
.wi-media-actions{display:flex;flex-wrap:wrap;gap:.5rem}
.wi-media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(8.5rem,1fr));gap:.75rem;margin-top:1rem}
.wi-media-grid:empty{display:none}
.wi-media-item{display:flex;flex-direction:column;gap:.375rem;padding:.5rem;border:1px solid var(--bs-border-color);border-radius:var(--bs-border-radius);background:var(--bs-body-bg);color:inherit;text-align:left;min-width:0}
.wi-media-item:hover,.wi-media-item:focus-visible{border-color:var(--bs-primary);outline:none;box-shadow:0 0 0 .2rem rgba(var(--bs-primary-rgb),.2)}
.wi-media-item .wi-media-frame{width:100%;height:6.5rem}
.wi-media-item-name{font-size:.875em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.wi-media-message{margin:1rem 0 0;color:var(--bs-secondary-color)}
.wi-media-pager{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-top:1rem}
.wi-media-count{font-size:.875em;color:var(--bs-secondary-color)}
</style>
HTML;
    }

    public static function script(): string
    {
        return <<<'HTML'
<script data-wi-media-script>
(function () {
    if (window.wiMediaFieldBound) { return; }
    window.wiMediaFieldBound = true;

    var MODAL_ID = 'wi-media-picker';
    var GLYPHS = { image: 'bi-image', icon: 'bi-grid-1x2', document: 'bi-file-earmark-text', video: 'bi-film' };
    var active = null;
    var state = { page: 1, pages: 1, request: 0, timer: null };

    var find = function (root, selector) { return root ? root.querySelector(selector) : null; };
    var modal = function () { return document.getElementById(MODAL_ID); };
    var token = function () {
        var meta = document.querySelector('meta[name="wi-csrf"]');
        return meta ? meta.content : '';
    };
    var changed = function (field) {
        var value = find(field, '[data-wi-media-value]');
        if (value) { value.dispatchEvent(new Event('change', { bubbles: true })); }
    };
    var releasePreview = function (field) {
        if (field.wiMediaObjectUrl) {
            URL.revokeObjectURL(field.wiMediaObjectUrl);
            field.wiMediaObjectUrl = null;
        }
    };

    // Anteprima del campo: `media` è {name, thumbnail, note} oppure null (vuoto).
    var show = function (field, media) {
        var image = find(field, '[data-wi-media-thumbnail]');
        var glyph = find(field, '[data-wi-media-glyph]');
        var name = find(field, '[data-wi-media-name]');
        var note = find(field, '[data-wi-media-note]');
        var remove = find(field, '[data-wi-media-remove]');
        var thumbnail = media && media.thumbnail ? media.thumbnail : '';

        if (image) {
            if (thumbnail) { image.src = thumbnail; } else { image.removeAttribute('src'); }
            image.hidden = !thumbnail;
        }
        if (glyph) { glyph.hidden = !!thumbnail; }
        if (name) { name.textContent = media ? media.name : (name.getAttribute('data-wi-media-empty-text') || ''); }
        if (note) { note.textContent = media && media.note ? media.note : ''; }
        if (remove) { remove.hidden = !media; }
        field.classList.toggle('is-empty', !media);
        field.classList.remove('is-invalid');
    };

    var choose = function (field, item) {
        var value = find(field, '[data-wi-media-value]');
        var upload = find(field, '[data-wi-media-upload]');
        releasePreview(field);
        if (upload) { upload.value = ''; }
        if (value) { value.value = String(item.id); }
        show(field, { name: item.name, thumbnail: item.thumbnail });
        changed(field);
    };

    var clear = function (field) {
        var value = find(field, '[data-wi-media-value]');
        var upload = find(field, '[data-wi-media-upload]');
        releasePreview(field);
        if (upload) { upload.value = ''; }
        if (value) { value.value = ''; }
        show(field, null);
        changed(field);
    };

    var uploaded = function (field, input) {
        var file = input.files && input.files[0];
        var note = find(field, '[data-wi-media-note]');
        releasePreview(field);
        if (!file) { return; }
        var thumbnail = '';
        if (/^image\//.test(file.type)) {
            field.wiMediaObjectUrl = URL.createObjectURL(file);
            thumbnail = field.wiMediaObjectUrl;
        }
        show(field, {
            name: file.name,
            thumbnail: thumbnail,
            note: note ? (note.getAttribute('data-wi-media-new-text') || '') : ''
        });
        changed(field);
    };

    var message = function (box, key) {
        var text = find(box, '[data-wi-media-message]');
        if (!text) { return; }
        text.textContent = key ? (text.getAttribute('data-wi-media-' + key + '-text') || '') : '';
        text.hidden = !key;
    };

    var card = function (item) {
        var button = document.createElement('button');
        var frame = document.createElement('span');
        var label = document.createElement('span');
        button.type = 'button';
        button.className = 'wi-media-item';
        button.setAttribute('data-wi-media-pick', String(item.id));
        button.title = item.alt ? item.name + ' · ' + item.alt : item.name;
        frame.className = 'wi-media-frame';
        if (item.thumbnail) {
            var image = document.createElement('img');
            image.className = 'wi-media-thumbnail';
            image.loading = 'lazy';
            image.alt = '';
            image.src = item.thumbnail;
            frame.appendChild(image);
        } else {
            var glyph = document.createElement('i');
            glyph.className = 'bi ' + (GLYPHS[item.type] || 'bi-file-earmark') + ' wi-media-glyph';
            glyph.setAttribute('aria-hidden', 'true');
            frame.appendChild(glyph);
        }
        label.className = 'wi-media-item-name';
        label.textContent = item.name;
        button.appendChild(frame);
        button.appendChild(label);
        button.wiMediaItem = item;
        return button;
    };

    var load = function (page) {
        var box = modal();
        if (!box || !active) { return; }
        var results = find(box, '[data-wi-media-results]');
        var pager = find(box, '[data-wi-media-pager]');
        var count = find(box, '[data-wi-media-count]');
        var term = find(box, '[data-wi-media-term]');
        var body = new FormData();
        var request = ++state.request;

        body.append('type', active.getAttribute('data-wi-media') || '');
        body.append('term', term ? term.value : '');
        body.append('page', String(page));
        message(box, 'loading');

        fetch(active.getAttribute('data-wi-media-search') || '', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-WI-CSRF': token() },
            body: body
        }).then(function (response) {
            return response.ok ? response.json() : Promise.reject(new Error('media'));
        }).then(function (data) {
            if (request !== state.request) { return; }
            var payload = data && data.response ? data.response : {};
            var items = Array.isArray(payload.items) ? payload.items : [];
            state.page = payload.page || 1;
            state.pages = payload.pages || 1;
            results.textContent = '';
            items.forEach(function (item) { results.appendChild(card(item)); });
            message(box, items.length ? '' : 'empty');
            if (pager) { pager.hidden = state.pages <= 1; }
            if (count) {
                count.textContent = (count.getAttribute('data-wi-media-count-text') || '')
                    .replace('{page}', String(state.page)).replace('{pages}', String(state.pages));
            }
            var previous = find(box, '[data-wi-media-page="previous"]');
            var next = find(box, '[data-wi-media-page="next"]');
            if (previous) { previous.disabled = state.page <= 1; }
            if (next) { next.disabled = state.page >= state.pages; }
        }).catch(function () {
            if (request !== state.request) { return; }
            results.textContent = '';
            if (pager) { pager.hidden = true; }
            message(box, 'error');
        });
    };

    var open = function (field) {
        var box = modal();
        if (!box || !window.bootstrap || !window.bootstrap.Modal) { return; }
        var title = find(box, '[data-wi-modal-title]');
        var term = find(box, '[data-wi-media-term]');
        active = field;
        if (title) { title.textContent = field.getAttribute('data-wi-media-title') || title.textContent; }
        if (term) { term.value = ''; }
        window.bootstrap.Modal.getOrCreateInstance(box).show();
        load(1);
    };

    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) { return; }

        var chooser = target.closest('[data-wi-media-choose]');
        if (chooser) { open(chooser.closest('[data-wi-media]')); return; }

        var remover = target.closest('[data-wi-media-remove]');
        if (remover) { clear(remover.closest('[data-wi-media]')); return; }

        var pick = target.closest('[data-wi-media-pick]');
        if (pick && active && pick.wiMediaItem) {
            choose(active, pick.wiMediaItem);
            var box = modal();
            if (box && window.bootstrap && window.bootstrap.Modal) { window.bootstrap.Modal.getOrCreateInstance(box).hide(); }
            return;
        }

        var pager = target.closest('[data-wi-media-page]');
        if (pager) { load(state.page + (pager.getAttribute('data-wi-media-page') === 'next' ? 1 : -1)); }
    });

    document.addEventListener('change', function (event) {
        var input = event.target instanceof Element ? event.target.closest('[data-wi-media-upload]') : null;
        if (input) { uploaded(input.closest('[data-wi-media]'), input); }
    });

    document.addEventListener('input', function (event) {
        var term = event.target instanceof Element ? event.target.closest('[data-wi-media-term]') : null;
        if (!term) { return; }
        window.clearTimeout(state.timer);
        state.timer = window.setTimeout(function () { load(1); }, 250);
    });

    // Un campo obbligatorio senza media ferma l'invio: l'input nascosto
    // non ha la validazione del browser.
    document.addEventListener('submit', function (event) {
        var form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form) { return; }
        var first = null;
        form.querySelectorAll('[data-wi-media][data-wi-media-required]').forEach(function (field) {
            var value = find(field, '[data-wi-media-value]');
            var upload = find(field, '[data-wi-media-upload]');
            var note = find(field, '[data-wi-media-note]');
            if ((value && value.disabled) || (value && value.value !== '') || (upload && upload.files && upload.files.length)) { return; }
            field.classList.add('is-invalid');
            if (note) { note.textContent = note.getAttribute('data-wi-media-required-text') || ''; }
            first = first || field;
        });
        if (first) {
            event.preventDefault();
            event.stopPropagation();
            first.scrollIntoView({ block: 'center' });
        }
    }, true);
})();
</script>
HTML;
    }
}
