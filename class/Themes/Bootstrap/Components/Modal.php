<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Component as ElementComponent;
use Wonder\Elements\Components\Button as ButtonElement;
use Wonder\Elements\Components\Tooltip;
use Wonder\Http\Csrf;
use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\HasGap;
use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Concerns\RendersPartAttributes;

class Modal extends Component
{
    use HasGap, RendersComponentAttributes, RendersPartAttributes;

    /** Lo script che stacca le finestre va stampato una volta per pagina. */
    private static bool $scriptEmitted = false;

    public function render($class): string
    {
        return $this->renderInner($class, ResourceFormLayoutRenderer::renderModalBody($class));
    }

    /**
     * La finestra attorno a un corpo già reso.
     *
     * Il layout dei form delle Resource rende i campi da sé, per dare a
     * ognuno la sua colonna, e chiede qui solo la cornice — come fa con
     * l'Accordion. Niente colonna attorno: la finestra non occupa posto
     * nella griglia della scheda.
     */
    public function renderInner(object $modal, string $bodyHtml): string
    {
        $schema = $modal->getSchema();
        $id = trim((string) ($schema['id'] ?? ''));

        if ($id === '') {
            $modal = clone $modal;
            $modal->id('wi-modal-'.strtolower($this->createId()));
        }

        $attributes = $this->renderComponentAttributes($modal, ['modal', 'fade']);
        $dialog = ['modal-dialog', 'modal-dialog-centered'];
        $size = trim((string) ($schema['size'] ?? ''));

        if ($size !== '') {
            $dialog[] = 'modal-'.$size;
        }

        if ((bool) ($schema['scrollable'] ?? false)) {
            $dialog[] = 'modal-dialog-scrollable';
        }

        $gap = is_array($modal->gap ?? null) ? $this->getGap($modal->gap) : '';
        $bodyClass = trim('modal-body row '.($gap !== '' ? $gap : 'g-3'));
        $footer = $this->renderFooter(method_exists($modal, 'footerComponents')
            ? $modal->footerComponents()
            : (array) ($modal->footer ?? []));
        $content = "<div {$this->partAttributes($modal, 'body', $bodyClass)}>{$bodyHtml}</div>"
            .($footer !== '' ? "<div {$this->partAttributes($modal, 'footer', 'modal-footer')}>{$footer}</div>" : '');

        // Senza form() non un `<form>`: la finestra nasce dentro il form
        // della Resource, e un form annidato il browser lo butta via in fase
        // di parsing. I campi li legge chi ha aperto la finestra.
        return "<div {$attributes} tabindex=\"-1\" aria-hidden=\"true\" data-wi-modal-detach>"
            ."<div {$this->partAttributes($modal, 'dialog', implode(' ', $dialog))}><div class=\"modal-content\">"
            ."<div {$this->partAttributes($modal, 'header', 'modal-header')}>"
            ."<h5 {$this->partAttributes($modal, 'title', 'modal-title', ['data-wi-modal-title'])} data-wi-modal-title>"
            .$this->escape($modal->getTitle()).'</h5>'
            .$this->renderHelp($modal)
            .'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>'
            .'</div>'
            .$this->wrapForm($modal, $content, (bool) ($schema['scrollable'] ?? false))
            .'</div></div></div>'
            .$this->script();
    }

    /** L'icona di aiuto accanto al titolo, fuori dall'h5 che uno script può riscrivere. */
    protected function renderHelp(object $modal): string
    {
        $help = trim((string) ($modal->getSchema('help') ?? ''));

        return $help !== '' ? Tooltip::make($help)->render('bootstrap') : '';
    }

    /**
     * Corpo e bottoni nel `<form>` di `form()`, con il token CSRF e i campi
     * nascosti; senza `form()` il contenuto resta com'è. In una finestra che
     * scorre il form fa da colonna flessibile, così il corpo scorre ancora.
     */
    protected function wrapForm(object $modal, string $content, bool $scrollable): string
    {
        $form = $modal->getSchema('form');

        if (!is_array($form)) {
            return $content;
        }

        $method = (string) ($form['method'] ?? 'post');
        $attributes = ['method' => $method, 'action' => (string) ($form['action'] ?? '')];

        if ($scrollable) {
            $attributes['class'] = 'd-flex flex-column overflow-hidden';
        }

        $hidden = '';
        foreach ((array) ($form['hidden'] ?? []) as $name => $value) {
            $hidden .= FormField::key((string) $name)->hidden()->value($value)->render('bootstrap');
        }

        return '<form '.$this->renderAttributes($attributes).'>'.Csrf::fieldFor($method).$hidden.$content.'</form>';
    }

    /**
     * I bottoni in fila, senza la colonna che il Button del tema si mette
     * attorno quando sta in una griglia.
     *
     * @param array<int, mixed> $components
     */
    private function renderFooter(array $components): string
    {
        $html = '';

        foreach ($components as $component) {
            if ($component instanceof ButtonElement) {
                $button = (clone $component)->schema('inline', true);

                if ($button->getSchema('modal_cancel')) {
                    $button->attr('data-bs-dismiss', 'modal');
                }

                $html .= $button->render('bootstrap');
                continue;
            }

            if ($component instanceof ElementComponent) {
                $html .= $component->render('bootstrap');
                continue;
            }

            if (is_string($component)) {
                $html .= $component;
            }
        }

        return $html;
    }

    /**
     * Lo script che porta le finestre in fondo al body, la prima volta che
     * si chiede; poi stringa vuota.
     *
     * Le finestre nascono dentro il form della Resource e i loro campi
     * partirebbero con il record: un `name` nella finestra e uno nella
     * scheda, e vince l'ultimo. Spostate nel body non sono più nel form.
     * È lo stesso passo della creazione rapida (`QuickCreateModal`).
     */
    private function script(): string
    {
        if (self::$scriptEmitted) {
            return '';
        }

        self::$scriptEmitted = true;

        return <<<'HTML'
<script>
(function () {
  if (window.wiModalDetachReady) return;
  window.wiModalDetachReady = true;

  function detachModals() {
    var modals = document.querySelectorAll('.modal[data-wi-modal-detach]');
    for (var i = 0; i < modals.length; i++) {
      if (modals[i].parentElement !== document.body) { document.body.appendChild(modals[i]); }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', detachModals);
  } else {
    detachModals();
  }
})();
</script>
HTML;
    }
}
