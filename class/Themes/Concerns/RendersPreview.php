<?php

namespace Wonder\Themes\Concerns;

use Wonder\Themes\Support\PageAssets;

/**
 * La finestra di anteprima, uguale nei due temi: barra con titolo, schede
 * delle sorgenti, passaggio chiaro/scuro e link "apri", poi l'`<iframe>`.
 *
 * Lo stato (sorgente attiva, schema) vive sugli attributi `data-wi-preview-*`
 * e lo script, stampato una volta per pagina, li legge e li aggiorna: cambia
 * `src` dell'iframe, avvisa la pagina dentro con `postMessage` quando cambia
 * lo schema, ascolta `wi-preview:height` per seguire l'altezza del contenuto
 * e, se c'è un gruppo, ricorda la scelta in `localStorage`.
 */
trait RendersPreview
{
    use EscapesHtml, RendersComponentAttributes, TranslatesLabels;

    abstract protected function wrapColumnSpan(object $element, string $html): string;

    /** Asset una volta per pagina, poi il riquadro (fuori dall'eventuale colonna). */
    protected function renderPreview(object $class, array $rootClasses = []): string
    {
        return $this->previewAssets().$this->wrapColumnSpan($class, $this->renderPreviewBox($class, $rootClasses));
    }

    /**
     * @param string[] $rootClasses classi del tema sulla radice, oltre a `wi-preview`
     */
    protected function renderPreviewBox(object $class, array $rootClasses = []): string
    {
        $schema = $class->getSchema();
        $sources = is_array($schema['sources'] ?? null) ? $schema['sources'] : [];
        $title = trim((string) ($schema['title'] ?? ''));
        $scheme = (string) ($schema['scheme'] ?? 'light');
        $group = trim((string) ($schema['group'] ?? ''));
        $height = (int) ($schema['height'] ?? 0);
        $autoHeight = (bool) ($schema['auto_height'] ?? true);
        $openInNewTab = (bool) ($schema['open_in_new_tab'] ?? true);
        $active = $this->activeSource($sources, (string) ($schema['active'] ?? ''));
        $id = $this->resolveId($schema['id'] ?? null);

        $classes = array_merge(['wi-preview'], $rootClasses);
        $attributes = $this->renderComponentAttributes($class, $classes);
        $style = $height > 0 ? ' style="--wi-preview-min-height: '.$height.'px"' : '';

        $html = '<div '.$attributes.' data-wi-preview'
            .($group !== '' ? ' data-wi-preview-group="'.$this->escape($group).'"' : '')
            .($active !== null ? ' data-wi-preview-active="'.$this->escape($active).'"' : '')
            .' data-wi-preview-scheme="'.$this->escape($scheme).'"'
            .' data-wi-preview-auto-height="'.($autoHeight ? 'true' : 'false').'"'.$style.'>';

        $html .= '<div class="wi-preview-toolbar">';
        $html .= '<div class="wi-preview-title">'.$this->escape($title).'</div>';
        $html .= '<div class="wi-preview-tabs" role="tablist">';

        foreach ($sources as $key => $source) {
            $key = (string) $key;
            $available = $source['url'] !== null || $source['srcdoc'] !== null;
            $reason = trim((string) ($source['reason'] ?? ''));
            $icon = trim((string) ($source['icon'] ?? ''));
            $tabClasses = ['wi-preview-tab'];

            if ($active === $key) {
                $tabClasses[] = 'is-active';
            }

            $html .= '<button type="button" class="'.implode(' ', $tabClasses).'" role="tab"'
                .' data-wi-preview-source="'.$this->escape($key).'"'
                .' data-wi-preview-schemes="'.(!empty($source['schemes']) ? 'true' : 'false').'"'
                .' data-wi-preview-reload="'.(!empty($source['reload']) ? 'true' : 'false').'"'
                .($source['url'] !== null ? ' data-wi-preview-url="'.$this->escape((string) $source['url']).'"' : '')
                .($source['srcdoc'] !== null ? ' data-wi-preview-srcdoc="'.$this->escape((string) $source['srcdoc']).'"' : '')
                .' aria-selected="'.($active === $key ? 'true' : 'false').'"'
                .(!$available ? ' disabled aria-disabled="true"' : '')
                .($reason !== '' ? ' title="'.$this->escape($reason).'"' : (!$available ? ' title="'.$this->escape($this->translateLabel('components.preview.unavailable', 'Non disponibile')).'"' : ''))
                .'>'
                .($icon !== '' ? '<i class="'.$this->escape($icon).'" aria-hidden="true"></i> ' : '')
                .$this->escape((string) ($source['label'] ?? $key))
                .'</button>';
        }

        $html .= '</div>';
        $html .= '<div class="wi-preview-actions">';
        $html .= '<button type="button" class="wi-preview-scheme" data-wi-preview-scheme-toggle'
            .' aria-label="'.$this->escape($this->translateLabel('components.preview.scheme', 'Passa da chiaro a scuro')).'"'
            .' title="'.$this->escape($this->translateLabel('components.preview.scheme', 'Passa da chiaro a scuro')).'">'
            .'<i class="bi bi-sun-fill" aria-hidden="true"></i><i class="bi bi-moon-stars-fill" aria-hidden="true"></i></button>';

        if ($openInNewTab) {
            $html .= '<a class="wi-preview-open" data-wi-preview-open href="#" target="_blank" rel="noopener noreferrer"'
                .' aria-label="'.$this->escape($this->translateLabel('components.preview.open', 'Apri in una nuova scheda')).'"'
                .' title="'.$this->escape($this->translateLabel('components.preview.open', 'Apri in una nuova scheda')).'">'
                .'<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>';
        }

        $html .= '</div></div>';
        $html .= '<div class="wi-preview-frame">'
            .'<iframe class="wi-preview-iframe" id="'.$this->escape($id).'-frame" title="'.$this->escape($title !== '' ? $title : 'Anteprima').'" loading="lazy"></iframe>'
            .'</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, array<string, mixed>> $sources
     */
    private function activeSource(array $sources, string $requested): ?string
    {
        $available = static fn (array $source): bool => ($source['url'] ?? null) !== null || ($source['srcdoc'] ?? null) !== null;

        if ($requested !== '' && isset($sources[$requested]) && $available($sources[$requested])) {
            return $requested;
        }

        foreach ($sources as $key => $source) {
            if ($available($source)) {
                return (string) $key;
            }
        }

        return null;
    }

    protected function previewAssets(): string
    {
        return PageAssets::once('wi-preview', static fn (): string => self::previewStyle().self::previewScript());
    }

    private static function previewStyle(): string
    {
        return <<<'HTML'
<style data-wi-preview-style>
.wi-preview{--wi-preview-border:var(--bs-border-color,#dee2e6);--wi-preview-bg:var(--bs-body-bg,#fff);--wi-preview-toolbar-bg:var(--bs-tertiary-bg,#f8f9fa);--wi-preview-tx:var(--bs-body-color,#212529);--wi-preview-muted:var(--bs-secondary-color,#6c757d);--wi-preview-accent:var(--bs-primary,#0d6efd);--wi-preview-radius:.75rem;display:block;border:1px solid var(--wi-preview-border);border-radius:var(--wi-preview-radius);background:var(--wi-preview-bg);overflow:hidden}
.wi-preview-toolbar{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;padding:.5rem .75rem;background:var(--wi-preview-toolbar-bg);border-bottom:1px solid var(--wi-preview-border);color:var(--wi-preview-tx);font-size:.8125rem}
.wi-preview-title{flex:1 1 auto;min-width:0;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wi-preview-tabs{display:inline-flex;gap:.25rem;padding:.125rem;border:1px solid var(--wi-preview-border);border-radius:.5rem;background:var(--wi-preview-bg)}
.wi-preview-tab{border:0;border-radius:.375rem;background:transparent;color:var(--wi-preview-muted);padding:.25rem .625rem;font:inherit;line-height:1.25;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem}
.wi-preview-tab:hover:not(:disabled){color:var(--wi-preview-tx)}
.wi-preview-tab.is-active{background:var(--wi-preview-accent);color:#fff}
.wi-preview-tab:disabled{opacity:.45;cursor:not-allowed}
.wi-preview-actions{display:inline-flex;align-items:center;gap:.25rem}
.wi-preview-scheme,.wi-preview-open{display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border:1px solid var(--wi-preview-border);border-radius:.5rem;background:var(--wi-preview-bg);color:var(--wi-preview-tx);text-decoration:none;cursor:pointer;font-size:.875rem}
.wi-preview-scheme:hover,.wi-preview-open:hover{color:var(--wi-preview-accent)}
.wi-preview-scheme .bi-moon-stars-fill,.wi-preview[data-wi-preview-scheme="dark"] .wi-preview-scheme .bi-sun-fill{display:none}
.wi-preview[data-wi-preview-scheme="dark"] .wi-preview-scheme .bi-moon-stars-fill{display:inline}
.wi-preview-scheme[hidden]{display:none}
.wi-preview-frame{position:relative;background:var(--wi-preview-bg)}
.wi-preview-iframe{display:block;width:100%;border:0;min-height:var(--wi-preview-min-height,160px);height:var(--wi-preview-min-height,160px);background:transparent;transition:height .15s ease}
</style>
HTML;
    }

    private static function previewScript(): string
    {
        return <<<'HTML'
<script data-wi-preview-script>
(function(){
    if (window.wiPreviewBound) { return; }
    window.wiPreviewBound = true;
    var storage = {
        get: function (key) { try { return window.localStorage.getItem(key); } catch (error) { return null; } },
        set: function (key, value) { try { window.localStorage.setItem(key, value); } catch (error) {} }
    };
    var withScheme = function (url, scheme) {
        if (!url) { return url; }
        var clean = url.replace(/([?&])scheme=[^&#]*(&|$)/, function (match, sep, end) { return end === '&' ? sep : ''; }).replace(/[?&]$/, '');
        return clean + (clean.indexOf('?') === -1 ? '?' : '&') + 'scheme=' + encodeURIComponent(scheme);
    };
    var tabsOf = function (box) { return Array.prototype.slice.call(box.querySelectorAll('[data-wi-preview-source]')); };
    var activeTab = function (box) {
        var key = box.getAttribute('data-wi-preview-active');
        var tabs = tabsOf(box);
        for (var i = 0; i < tabs.length; i++) { if (tabs[i].getAttribute('data-wi-preview-source') === key && !tabs[i].disabled) { return tabs[i]; } }
        for (var j = 0; j < tabs.length; j++) { if (!tabs[j].disabled) { return tabs[j]; } }
        return null;
    };
    var load = function (box, reload) {
        var frame = box.querySelector('.wi-preview-iframe');
        var tab = activeTab(box);
        var scheme = box.getAttribute('data-wi-preview-scheme') || 'light';
        var toggle = box.querySelector('[data-wi-preview-scheme-toggle]');
        var open = box.querySelector('[data-wi-preview-open]');
        tabsOf(box).forEach(function (candidate) {
            var on = candidate === tab;
            candidate.classList.toggle('is-active', on);
            candidate.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        if (!frame || !tab) { if (toggle) { toggle.hidden = true; } if (open) { open.hidden = true; } return; }
        box.setAttribute('data-wi-preview-active', tab.getAttribute('data-wi-preview-source'));
        var schemes = tab.getAttribute('data-wi-preview-schemes') === 'true';
        if (toggle) { toggle.hidden = !schemes; }
        var url = tab.getAttribute('data-wi-preview-url');
        var srcdoc = tab.getAttribute('data-wi-preview-srcdoc');
        if (srcdoc !== null) {
            frame.removeAttribute('src');
            frame.srcdoc = srcdoc;
            if (open) { open.hidden = true; }
            return;
        }
        var target = schemes ? withScheme(url, scheme) : url;
        if (open) { open.hidden = false; open.href = target; }
        if (reload || frame.getAttribute('src') !== target) {
            frame.removeAttribute('srcdoc');
            frame.setAttribute('src', target);
        }
    };
    var persist = function (box) {
        var group = box.getAttribute('data-wi-preview-group');
        if (!group) { return; }
        storage.set('wi-preview:' + group + ':source', box.getAttribute('data-wi-preview-active') || '');
        storage.set('wi-preview:' + group + ':scheme', box.getAttribute('data-wi-preview-scheme') || 'light');
    };
    var setSource = function (box, key, persistChoice) {
        var tabs = tabsOf(box);
        for (var i = 0; i < tabs.length; i++) {
            if (tabs[i].getAttribute('data-wi-preview-source') === key && !tabs[i].disabled) {
                box.setAttribute('data-wi-preview-active', key);
                load(box, false);
                if (persistChoice) { persist(box); }
                return true;
            }
        }
        return false;
    };
    var setScheme = function (box, scheme, persistChoice) {
        box.setAttribute('data-wi-preview-scheme', scheme);
        var tab = activeTab(box);
        var frame = box.querySelector('.wi-preview-iframe');
        var open = box.querySelector('[data-wi-preview-open]');
        if (tab && tab.getAttribute('data-wi-preview-schemes') === 'true' && frame && tab.getAttribute('data-wi-preview-url')) {
            var url = withScheme(tab.getAttribute('data-wi-preview-url'), scheme);
            if (open) { open.href = url; }
            if (tab.getAttribute('data-wi-preview-reload') === 'true' || !frame.contentWindow) {
                frame.setAttribute('src', url);
            } else {
                // La pagina dentro cambia schema da sé: niente ricaricamento.
                try { frame.contentWindow.postMessage({ type: 'wi-preview:scheme', scheme: scheme }, '*'); } catch (error) {}
            }
        }
        if (persistChoice) { persist(box); }
    };
    var init = function (box) {
        if (box.__wiPreview) { return; }
        box.__wiPreview = true;
        var group = box.getAttribute('data-wi-preview-group');
        if (group) {
            var savedSource = storage.get('wi-preview:' + group + ':source');
            var savedScheme = storage.get('wi-preview:' + group + ':scheme');
            if (savedScheme === 'light' || savedScheme === 'dark') { box.setAttribute('data-wi-preview-scheme', savedScheme); }
            if (savedSource) { setSource(box, savedSource, false); }
        }
        load(box, false);
        box.addEventListener('click', function (event) {
            var tab = event.target.closest('[data-wi-preview-source]');
            if (tab && box.contains(tab) && !tab.disabled) {
                setSource(box, tab.getAttribute('data-wi-preview-source'), true);
                return;
            }
            if (event.target.closest('[data-wi-preview-scheme-toggle]')) {
                setScheme(box, box.getAttribute('data-wi-preview-scheme') === 'dark' ? 'light' : 'dark', true);
            }
        });
    };
    var boot = function (root) {
        Array.prototype.slice.call((root || document).querySelectorAll('[data-wi-preview]')).forEach(init);
    };
    document.addEventListener('click', function (event) {
        var control = event.target.closest('[data-wi-preview-switch]');
        if (!control) { return; }
        var group = control.getAttribute('data-wi-preview-switch');
        var source = control.getAttribute('data-wi-preview-set-source');
        var scheme = control.getAttribute('data-wi-preview-set-scheme');
        var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-wi-preview][data-wi-preview-group="' + group + '"]'));
        boxes.forEach(function (box) {
            if (source) { setSource(box, source, false); }
            if (scheme) { setScheme(box, scheme, false); }
        });
        if (source) { storage.set('wi-preview:' + group + ':source', source); }
        if (scheme) { storage.set('wi-preview:' + group + ':scheme', scheme); }
        document.dispatchEvent(new CustomEvent('wi-preview:switch', { detail: { group: group, source: source, scheme: scheme } }));
    });
    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || data.type !== 'wi-preview:height') { return; }
        var frames = document.querySelectorAll('[data-wi-preview] .wi-preview-iframe');
        for (var i = 0; i < frames.length; i++) {
            if (frames[i].contentWindow === event.source) {
                var box = frames[i].closest('[data-wi-preview]');
                if (box && box.getAttribute('data-wi-preview-auto-height') === 'true') {
                    var min = parseInt(window.getComputedStyle(frames[i]).minHeight, 10) || 0;
                    frames[i].style.height = Math.max(min, Math.ceil(Number(data.height) || 0)) + 'px';
                }
                return;
            }
        }
    });
    window.wiPreview = { boot: boot, setSource: setSource, setScheme: setScheme };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { boot(); }); } else { boot(); }
})();
</script>
HTML;
    }
}
