<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\App\ResourceSchema\FormField;
use Wonder\Elements\Components\Button as ButtonElement;
use Wonder\Http\Csrf;
use Wonder\Themes\Wonder\Component;
use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Concerns\RendersPartAttributes;
use Wonder\Themes\Concerns\RendersThemeComponents;

/**
 * Frontend opt-in through the existing wi-modal contract. Backend-only
 * modal definitions keep their previous empty rendering in Wonder.
 */
class Modal extends Component
{
    use RendersComponentAttributes, RendersPartAttributes, RendersThemeComponents;

    public function render($class): string
    {
        if (!$class->getSchema('frontend')) { return ''; }
        $id = (string) $class->getSchema('id');
        if ($id === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $id)) {
            throw new \InvalidArgumentException('Frontend modals require a selector-safe explicit id.');
        }
        // Una modal che il server manda già aperta (`wi-show`) esce cliccabile e
        // visibile: senza `no-interaction`, `aria-hidden` e `inert`, che lo script
        // toglierebbe solo all'apertura. Lo script poi la collega come se l'avesse
        // aperta lui, così si può chiudere.
        $customAttributes = $class->getSchema('attributes');
        $open = is_array($customAttributes) && in_array('wi-show', $this->classTokens($customAttributes['class'] ?? null), true);
        $attributes = $this->renderComponentAttributes($class, $open ? ['wi-modal'] : ['wi-modal', 'no-interaction']);
        $closed = $open ? '' : ' aria-hidden="true" inert';
        $body = $this->renderThemeComponents($class->components, 'wonder');
        $footer = $this->renderFooter($class->footerComponents());
        $content = '<div '.$this->partAttributes($class, 'body', 'wi-modal-body no-scrollbar').'>'.$body.'</div>'
            .($footer !== '' ? '<div '.$this->partAttributes($class, 'footer', 'wi-modal-footer d-flex j-content-end gap-3').'>'.$footer.'</div>' : '');
        return '<section '.$attributes.' role="dialog" aria-modal="true"'.$closed.' aria-labelledby="'.$id.'-title">'
            .'<div class="bg wi-close-modal"></div><div '.$this->partAttributes($class, 'dialog', 'content wi-modal-content c-w', ['style']).' style="max-width:760px;">'
            .'<div '.$this->partAttributes($class, 'header', 'wi-modal-header').'><h2 id="'.$id.'-title" '.$this->partAttributes($class, 'title', 'wi-modal-title', ['id']).'>'
            .$this->escape($class->getTitle()).$this->renderHelp($class).'</h2>'
            .'<button type="button" class="wi-modal-close wi-close-modal" aria-label="'.$this->escape((string) \__t('account.actions.cancel')).'"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>'
            .$this->wrapForm($class, $content)
            .'</div></section>';
    }

    /**
     * L'icona con il tooltip della lib. Il tooltip scrive `data-wi-title` con
     * innerHTML: il testo è escapato due volte, così resta testo.
     */
    protected function renderHelp(object $modal): string
    {
        $help = trim((string) ($modal->getSchema('help') ?? ''));

        if ($help === '') {
            return '';
        }

        return ' <span class="wi-modal-help" data-wi-toggle="tooltip" data-wi-title="'.$this->escape($this->escape($help)).'"'
            .' tabindex="0" aria-label="'.$this->escape($help).'"><i class="bi bi-info-circle" aria-hidden="true"></i></span>';
    }

    /** Corpo e bottoni nel `<form>` di `form()`, con token CSRF e campi nascosti. */
    protected function wrapForm(object $modal, string $content): string
    {
        $form = $modal->getSchema('form');

        if (!is_array($form)) {
            return $content;
        }

        $method = (string) ($form['method'] ?? 'post');
        $hidden = '';

        foreach ((array) ($form['hidden'] ?? []) as $name => $value) {
            $hidden .= FormField::key((string) $name)->hidden()->value($value)->render('wonder');
        }

        return '<form '.$this->renderAttributes([
            'class' => 'wi-modal-form',
            'method' => $method,
            'action' => (string) ($form['action'] ?? ''),
        ]).'>'.Csrf::fieldFor($method).$hidden.$content.'</form>';
    }

    /**
     * Annulla come nei dialoghi della lib (`btn-dark-o`): il `secondary` del
     * sito può essere chiaro quanto lo sfondo della finestra.
     *
     * @param array<int, mixed> $components
     */
    protected function renderFooter(array $components): string
    {
        foreach ($components as $index => $component) {
            if ($component instanceof ButtonElement && $component->getSchema('modal_cancel')) {
                $components[$index] = (clone $component)->variant('dark')->addClass('wi-close-modal');
            }
        }

        return $this->renderThemeComponents($components, 'wonder');
    }
}
