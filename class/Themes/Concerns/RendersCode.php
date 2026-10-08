<?php

namespace Wonder\Themes\Concerns;

use Wonder\Support\Code\Highlighter;
use Wonder\Themes\Support\PageAssets;

/**
 * Il blocco di codice, uguale nei due temi: intestazione con etichetta e
 * bottone "copia", `<pre><code>` evidenziato dal `Highlighter`. CSS e script
 * del bottone escono una sola volta per pagina da `PageAssets`; il contratto
 * dichiarativo (`data-wi-code`, `data-wi-copy`, `data-wi-code-source`) resta
 * stabile, così la lib può prendere in carico lo script senza cambiare il
 * markup.
 */
trait RendersCode
{
    use EscapesHtml, RendersComponentAttributes, TranslatesLabels;

    abstract protected function wrapColumnSpan(object $element, string $html): string;

    /** Asset una volta per pagina, poi il blocco (fuori dall'eventuale colonna). */
    protected function renderCode(object $class, array $rootClasses = []): string
    {
        return $this->codeAssets().$this->wrapColumnSpan($class, $this->renderCodeBlock($class, $rootClasses));
    }

    /**
     * @param string[] $rootClasses classi del tema sulla radice, oltre a `wi-code`
     */
    protected function renderCodeBlock(object $class, array $rootClasses = []): string
    {
        $schema = $class->getSchema();
        $code = (string) ($schema['code'] ?? '');
        $language = Highlighter::normalizeLanguage((string) ($schema['language'] ?? 'text'));
        $title = trim((string) ($schema['title'] ?? ''));
        $copy = (bool) ($schema['copy'] ?? true);
        $scheme = (string) ($schema['scheme'] ?? 'dark');
        $lineNumbers = (bool) ($schema['line_numbers'] ?? false);
        $maxHeight = trim((string) ($schema['max_height'] ?? ''));

        $classes = array_merge(['wi-code', 'wi-code-'.$scheme], $rootClasses);

        if ($lineNumbers) {
            $classes[] = 'wi-code-numbered';
        }

        $attributes = $this->renderComponentAttributes($class, $classes);
        $style = $maxHeight !== '' ? ' style="--wi-code-max-height: '.$this->escape($maxHeight).'"' : '';
        $label = $title !== '' ? $title : $language;

        $html = '<div '.$attributes.' data-wi-code data-wi-code-language="'.$this->escape($language).'"'.$style.'>';
        $html .= '<div class="wi-code-header">';
        $html .= '<span class="wi-code-title">'.$this->escape($label).'</span>';

        if ($copy) {
            $copyLabel = (string) ($schema['copy_label'] ?? $this->translateLabel('components.code.copy', 'Copia'));
            $copiedLabel = (string) ($schema['copied_label'] ?? $this->translateLabel('components.code.copied', 'Copiato'));
            $html .= '<button type="button" class="wi-code-copy" data-wi-copy data-wi-copy-done="'.$this->escape($copiedLabel).'" aria-label="'.$this->escape($copyLabel).'">'
                .'<i class="bi bi-clipboard" aria-hidden="true"></i><i class="bi bi-clipboard-check" aria-hidden="true"></i>'
                .'<span data-wi-copy-label>'.$this->escape($copyLabel).'</span></button>';
        }

        $html .= '</div>';
        $html .= '<pre class="wi-code-pre"><code class="wi-code-source language-'.$this->escape($language).'" data-wi-code-source>'
            .Highlighter::highlight($code, $language).'</code></pre>';
        $html .= '</div>';

        return $html;
    }

    protected function codeAssets(): string
    {
        return PageAssets::once('wi-code', static fn (): string => self::codeStyle().self::codeScript());
    }

    private static function codeStyle(): string
    {
        return <<<'HTML'
<style data-wi-code-style>
.wi-code{--wi-code-bg:#0f1117;--wi-code-header-bg:#161922;--wi-code-border:#2a2f3a;--wi-code-tx:#e6e9ef;--wi-code-muted:#8b93a7;--wi-code-keyword:#ff7ab2;--wi-code-string:#8be9fd;--wi-code-comment:#6b7280;--wi-code-variable:#c3a6ff;--wi-code-number:#ffb86c;--wi-code-class:#7dd3fc;--wi-code-function:#a6e3a1;--wi-code-constant:#ffb86c;--wi-code-property:#e6e9ef;--wi-code-tag:#ff7ab2;--wi-code-attr:#a6e3a1;--wi-code-radius:.75rem;position:relative;display:block;border:1px solid var(--wi-code-border);border-radius:var(--wi-code-radius);background:var(--wi-code-bg);color:var(--wi-code-tx);overflow:hidden;font-size:.875rem}
.wi-code-light,[data-bs-theme="light"] .wi-code-auto{--wi-code-bg:#f8f9fb;--wi-code-header-bg:#eef0f4;--wi-code-border:#d9dde6;--wi-code-tx:#1f2937;--wi-code-muted:#6b7280;--wi-code-keyword:#c2185b;--wi-code-string:#0b7285;--wi-code-comment:#9ca3af;--wi-code-variable:#6f42c1;--wi-code-number:#b45309;--wi-code-class:#1d4ed8;--wi-code-function:#15803d;--wi-code-constant:#b45309;--wi-code-property:#1f2937;--wi-code-tag:#c2185b;--wi-code-attr:#15803d}
.wi-code-header{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.5rem .5rem .5rem 1rem;background:var(--wi-code-header-bg);border-bottom:1px solid var(--wi-code-border);color:var(--wi-code-muted);font-size:.75rem;letter-spacing:.02em}
.wi-code-title{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
.wi-code-copy{display:inline-flex;align-items:center;gap:.4rem;border:1px solid transparent;border-radius:.5rem;background:transparent;color:inherit;padding:.25rem .5rem;font:inherit;line-height:1;cursor:pointer}
.wi-code-copy:hover,.wi-code-copy:focus-visible{border-color:var(--wi-code-border);color:var(--wi-code-tx);outline:none}
.wi-code-copy .bi-clipboard-check,.wi-code-copy.is-copied .bi-clipboard{display:none}
.wi-code-copy.is-copied .bi-clipboard-check{display:inline}
.wi-code-copy.is-copied{color:var(--wi-code-function)}
.wi-code-pre{margin:0;padding:1rem;overflow:auto;max-height:var(--wi-code-max-height,none);background:transparent;color:inherit;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:inherit;line-height:1.6;tab-size:4}
.wi-code-source{display:block;background:transparent;color:inherit;font:inherit;white-space:pre}
.wi-code-numbered .wi-code-source{counter-reset:wi-code-line}
.wi-code-keyword{color:var(--wi-code-keyword)}
.wi-code-string{color:var(--wi-code-string)}
.wi-code-comment{color:var(--wi-code-comment);font-style:italic}
.wi-code-variable{color:var(--wi-code-variable)}
.wi-code-number{color:var(--wi-code-number)}
.wi-code-class{color:var(--wi-code-class)}
.wi-code-function{color:var(--wi-code-function)}
.wi-code-constant{color:var(--wi-code-constant)}
.wi-code-property{color:var(--wi-code-property)}
.wi-code-tag{color:var(--wi-code-tag)}
.wi-code-attr{color:var(--wi-code-attr)}
</style>
HTML;
    }

    private static function codeScript(): string
    {
        return <<<'HTML'
<script data-wi-code-script>
(function(){
    if (window.wiCodeCopyBound) { return; }
    window.wiCodeCopyBound = true;
    var done = function (button) {
        var label = button.querySelector('[data-wi-copy-label]');
        var previous = label ? label.textContent : '';
        button.classList.add('is-copied');
        if (label) { label.textContent = button.getAttribute('data-wi-copy-done') || previous; }
        window.setTimeout(function () {
            button.classList.remove('is-copied');
            if (label) { label.textContent = previous; }
        }, 1600);
    };
    var fallback = function (text) {
        var area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (error) { ok = false; }
        document.body.removeChild(area);
        return ok;
    };
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-wi-copy]');
        if (!button) { return; }
        var root = button.closest('[data-wi-code]');
        var source = root ? root.querySelector('[data-wi-code-source]') : null;
        var text = source ? source.textContent : '';
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () { done(button); }, function () { if (fallback(text)) { done(button); } });
        } else if (fallback(text)) {
            done(button);
        }
    });
})();
</script>
HTML;
    }
}
