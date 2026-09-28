<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Elements\Component as ElementComponent;
use Wonder\Elements\Components\Button as ButtonElement;
use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\HasGap;
use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Concerns\RendersThemeComponents;

class Modal extends Component
{
    use HasGap, RendersComponentAttributes, RendersThemeComponents;

    /** Lo script che stacca le finestre va stampato una volta per pagina. */
    private static bool $scriptEmitted = false;

    public function render($class): string
    {
        return $this->renderInner($class, $this->renderThemeComponents((array) $class->components, 'bootstrap'));
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
        $footer = $this->renderFooter((array) ($modal->footer ?? []));

        // Non un `<form>`: la finestra nasce dentro il form della Resource, e
        // un form annidato il browser lo butta via in fase di parsing. I
        // campi li legge chi ha aperto la finestra.
        return "<div {$attributes} tabindex=\"-1\" aria-hidden=\"true\" data-wi-modal-detach>"
            .'<div class="'.implode(' ', $dialog).'"><div class="modal-content">'
            .'<div class="modal-header">'
            .'<h5 class="modal-title" data-wi-modal-title>'.$this->escape($modal->getTitle()).'</h5>'
            .'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>'
            .'</div>'
            ."<div class=\"{$bodyClass}\">{$bodyHtml}</div>"
            .($footer !== '' ? "<div class=\"modal-footer\">{$footer}</div>" : '')
            .'</div></div></div>'
            .$this->script();
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
                $html .= (clone $component)->schema('inline', true)->render('bootstrap');
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
