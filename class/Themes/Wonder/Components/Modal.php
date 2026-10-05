<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Wonder\Component;
use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Concerns\RendersThemeComponents;

/**
 * Frontend opt-in through the existing wi-modal contract. Backend-only
 * modal definitions keep their previous empty rendering in Wonder.
 */
class Modal extends Component
{
    use RendersComponentAttributes, RendersThemeComponents;

    public function render($class): string
    {
        if (!$class->getSchema('frontend')) { return ''; }
        $id = (string) $class->getSchema('id');
        if ($id === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $id)) {
            throw new \InvalidArgumentException('Frontend modals require a selector-safe explicit id.');
        }
        $attributes = $this->renderComponentAttributes($class, ['wi-modal', 'no-interaction']);
        $body = $this->renderThemeComponents($class->components, 'wonder');
        $footer = $this->renderThemeComponents($class->footer, 'wonder');
        return '<section '.$attributes.' role="dialog" aria-modal="true" aria-hidden="true" inert aria-labelledby="'.$id.'-title">'
            .'<div class="bg wi-close-modal"></div><div class="content wi-modal-content c-w" style="max-width:760px;">'
            .'<div class="wi-modal-header"><h2 id="'.$id.'-title" class="wi-modal-title">'.$this->escape($class->getTitle()).'</h2>'
            .'<button type="button" class="wi-modal-close wi-close-modal" aria-label="'.$this->escape((string) \__t('account.actions.cancel')).'"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>'
            .'<div class="wi-modal-body no-scrollbar">'.$body.'</div>'
            .($footer !== '' ? '<div class="wi-modal-footer d-flex j-content-end gap-3">'.$footer.'</div>' : '')
            .'</div></section>';
    }
}
