<?php

namespace Wonder\Themes\Wonder\Form;

use Wonder\Themes\Form\AbstractFieldRenderer;

/**
 * Renderer base per i field del tema `Wonder` (frontend pubblico).
 *
 * Gli helper condivisi con Bootstrap (lettura schema, hasError,
 * resolvedLabel, …) vivono in `AbstractFieldRenderer`. Qui restano
 * solo le funzioni che producono markup `wi-*` proprio del tema.
 */
abstract class Field extends AbstractFieldRenderer
{
    /**
     * Costruisce la classe CSS dell'input con stati `compiled`/`input-error`.
     * Base default `wi-input`; i componenti specifici (es. Select, Textarea)
     * possono passare un base diverso.
     */
    protected function inputClass(string $base = 'wi-input'): string
    {
        $class = [$base];

        if ($this->hasValue()) {
            $class[] = 'compiled';
        }

        if ($this->hasError()) {
            $class[] = 'input-error';
        }

        return implode(' ', $class);
    }

    /**
     * Classe del container `wi-input-container` con stato e tipo.
     * Usata dai componenti che wrappano l'input in un `<div>`.
     *
     * Aggiunge `wi-nf` quando il field è in modalità no-floating
     * (vedi `AbstractFieldRenderer::isNoFloating()`): il CSS frontend
     * sopprime l'animazione della label e usa un layout statico.
     */
    protected function containerClass(string $type): string
    {
        $class = ['wi-input-container', $type];

        if ($this->hasValue()) {
            $class[] = 'compiled';
        }

        if ($this->hasError()) {
            $class[] = 'input-error';
        }

        if ($this->isNoFloating()) {
            $class[] = 'wi-nf';
        }

        return implode(' ', $class);
    }

    /**
     * Attributo `data-wi-label="true"` per i campi con label flottante
     * Wonder. Il JS di `wonder-image/lib` lo usa per attivare/aggiornare
     * lo stato `compiled` della label durante focus/blur/typing. Ritorna
     * stringa vuota se il campo è senza label (es. hidden) — l'attributo
     * non avrebbe senso e replicherebbe il comportamento del vecchio
     * `app/function/frontend/input.php` (le funzioni ora rimosse lo
     * emettevano solo quando esisteva una label associata).
     */
    protected function labelMarker(): string
    {
        return $this->resolvedLabel() !== '' ? ' data-wi-label="true"' : '';
    }

    /**
     * Markup label tema Wonder: `<label class="wi-label">…</label>`.
     */
    protected function renderLabel(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $label = $this->escape($this->resolvedLabel());

        return '<label for="'.$id.'" class="wi-label">'.$label.'</label>';
    }

    /**
     * Markup errore tema Wonder: span con icona warning. Ritorna uno
     * span vuoto quando non c'è errore per riservare lo spazio nel
     * layout (no jumping).
     */
    protected function renderError(): string
    {
        $error = $this->errorMessage();

        if ($error === '') {
            return "<span class='alert-error'></span>";
        }

        return "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> ".$this->escape($error).'</span>';
    }
}
