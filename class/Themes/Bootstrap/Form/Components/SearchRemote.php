<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

/**
 * Tema Bootstrap (backend): ricerca remota a scelta singola.
 *
 * Il campo posta un id: la casella che si vede serve solo a cercare. Sotto
 * c'è una tendina `list-group` assoluta (non sposta la pagina) che si
 * riempie da `url?q=` dopo due lettere. L'endpoint risponde con
 * `{items: [{id, label, detail}]}` (vanno bene anche `results` o un array).
 *
 * Scegliere una voce scrive l'id nel campo nascosto, l'etichetta nella
 * casella, e manda `change` sul campo nascosto più `wi:search-select`
 * (bubbling, `detail` = la voce) dalla radice `[data-wi-remote-search]`.
 * Scrivere nella casella svuota l'id. La creazione rapida
 * (`wi:quick-create:created`) sceglie la voce appena creata.
 *
 * Lo script è uno solo per la pagina (listener delegati sul documento),
 * così funziona anche sui campi aggiunti dopo.
 */
class SearchRemote extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $url = $this->escape((string) ($this->schema['url'] ?? ''));
        $value = $this->hasValue() ? $this->escape((string) $this->schema['value']) : '';
        $label = $value !== '' ? $this->escape((string) ($this->schema['value_label'] ?? '')) : '';
        $empty = $this->escape((string) ($this->schema['empty_text'] ?? 'Nessun risultato'));
        $attributes = $this->fieldAttributes(['placeholder']);
        $class = $this->fieldClass($this->inputClass('form-control'));
        $placeholder = $this->isNoFloating()
            ? $this->escape((string) ($this->schema['placeholder'] ?? ''))
            : ' ';

        $html = '<input type="hidden" name="'.$name.'" value="'.$value.'" data-wi-remote-search-value>'
            .'<input type="text" class="'.$class.'" id="'.$id.'" value="'.$label.'" placeholder="'.$placeholder.'"'
            .' autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false"'
            .' data-wi-remote-search-input'.$attributes.'>'
            .'<div class="list-group position-absolute w-100 shadow d-none" role="listbox"'
            .' style="z-index: 1050; max-height: 20rem; overflow-y: auto" data-wi-remote-search-results></div>';

        // Col floating la radice è il `form-floating` stesso (relativo, e la
        // label resta sorella dell'input): qui serve solo il segnaposto della
        // radice. Senza floating ci vuole un contenitore relativo.
        $root = ' data-wi-remote-search data-url="'.$url.'" data-empty="'.$empty.'"';

        if ($this->isNoFloating()) {
            return '<div class="position-relative"'.$root.'>'.$html.'</div>'.self::script();
        }

        return '<span class="d-none"'.$root.'></span>'.$html.self::script();
    }

    /** Lo script condiviso: si arma una volta sola per pagina. */
    public static function script(): string
    {
        return <<<'HTML'
<script>
window.wiRemoteSearch = window.wiRemoteSearch || (function () {
    var timers = new WeakMap();

    function rootOf(node) {
        var box = node && node.closest ? node.closest('.form-floating, [data-wi-remote-search]') : null;

        if (!box) { return null; }
        if (box.hasAttribute('data-wi-remote-search')) { return box; }

        return box.querySelector('[data-wi-remote-search-input]') ? box : null;
    }

    function config(root) {
        return root.hasAttribute('data-wi-remote-search') ? root : root.querySelector('[data-wi-remote-search]');
    }

    function parts(root) {
        return {
            value: root.querySelector('[data-wi-remote-search-value]'),
            input: root.querySelector('[data-wi-remote-search-input]'),
            results: root.querySelector('[data-wi-remote-search-results]')
        };
    }

    function close(root) {
        var p = parts(root);

        p.results.classList.add('d-none');
        p.results.textContent = '';
        p.input.setAttribute('aria-expanded', 'false');
    }

    function select(root, item) {
        var p = parts(root);

        p.value.value = String(item.id);
        p.input.value = item.label || '';
        close(root);
        p.value.dispatchEvent(new Event('change', { bubbles: true }));
        root.dispatchEvent(new CustomEvent('wi:search-select', { bubbles: true, detail: item }));
    }

    function option(root, item) {
        var button = document.createElement('button');
        var text = document.createElement('div');
        var title = document.createElement('div');

        button.type = 'button';
        button.className = 'list-group-item list-group-item-action';
        button.setAttribute('role', 'option');
        text.className = 'min-w-0';
        title.className = 'text-truncate';
        title.textContent = item.label || '';
        text.appendChild(title);

        if (item.detail) {
            var detail = document.createElement('div');
            detail.className = 'small text-body-secondary text-truncate';
            detail.textContent = item.detail;
            text.appendChild(detail);
        }

        button.appendChild(text);
        button.addEventListener('click', function () { select(root, item); });

        return button;
    }

    function show(root, items) {
        var p = parts(root);
        var i;

        close(root);

        if (!items.length) {
            var none = document.createElement('div');
            none.className = 'list-group-item small text-body-secondary';
            none.textContent = config(root).getAttribute('data-empty') || '';
            p.results.appendChild(none);
        }

        for (i = 0; i < items.length; i++) {
            p.results.appendChild(option(root, items[i]));
        }

        p.results.classList.remove('d-none');
        p.input.setAttribute('aria-expanded', 'true');
    }

    function search(root) {
        var p = parts(root);
        var q = p.input.value.trim();
        var url = config(root).getAttribute('data-url') || '';

        if (q.length < 2 || !url) { close(root); return; }

        fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (response) { return response.ok ? response.json() : []; })
            .then(function (data) {
                if (p.input.value.trim() !== q) { return; }
                var items = Array.isArray(data) ? data : (data.items || data.results || []);
                show(root, items);
            })
            .catch(function () { close(root); });
    }

    document.addEventListener('input', function (event) {
        var target = event.target;

        if (!target.matches || !target.matches('[data-wi-remote-search-input]')) { return; }

        var root = rootOf(target);
        var p = parts(root);

        if (p.value.value !== '') {
            p.value.value = '';
            p.value.dispatchEvent(new Event('change', { bubbles: true }));
        }

        clearTimeout(timers.get(root));
        timers.set(root, setTimeout(function () { search(root); }, 250));
    });

    document.addEventListener('keydown', function (event) {
        var target = event.target;
        var root = rootOf(target);

        if (!root || !root.contains(target)) { return; }

        var p = parts(root);
        var buttons = Array.prototype.slice.call(p.results.querySelectorAll('button'));
        var at = buttons.indexOf(document.activeElement);

        if (event.key === 'Escape') {
            close(root);
            p.input.focus();
        } else if (event.key === 'ArrowDown' && buttons.length) {
            event.preventDefault();
            buttons[Math.min(at + 1, buttons.length - 1)].focus();
        } else if (event.key === 'ArrowUp' && at !== -1) {
            event.preventDefault();
            (at === 0 ? p.input : buttons[at - 1]).focus();
        } else if (event.key === 'Enter' && target === p.input) {
            // Invio nella casella non deve inviare il form.
            event.preventDefault();
            if (buttons.length) { buttons[0].click(); }
        }
    });

    document.addEventListener('click', function (event) {
        document.querySelectorAll('[data-wi-remote-search-results]:not(.d-none)').forEach(function (results) {
            var root = rootOf(results);

            if (root && !root.contains(event.target)) { close(root); }
        });
    });

    document.addEventListener('wi:quick-create:created', function (event) {
        var detail = event.detail || {};
        var root = detail.input ? rootOf(detail.input) : null;

        if (root && detail.id) { select(root, { id: detail.id, label: detail.label, item: detail.item || {} }); }
    });

    return { select: select, close: close };
})();
</script>
HTML;
    }
}
