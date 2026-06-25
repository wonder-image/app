<?php

namespace Wonder\Themes\Bootstrap\Form;

use Wonder\Themes\Form\AbstractFieldRenderer;

/**
 * Renderer base per i field del tema `Bootstrap` (backend admin).
 *
 * Gli helper condivisi con Wonder vivono in `AbstractFieldRenderer`.
 * Qui restano solo le funzioni che producono markup Bootstrap 5
 * (`form-control`, `form-floating`, `invalid-feedback`).
 */
abstract class Field extends AbstractFieldRenderer
{
    /**
     * Override del render del parent: prima di costruire il wrap,
     * leggiamo `isNoFloating()` per decidere se applicare il pattern
     * `form-floating`. Questo permette ai consumer di usare
     * `Field::noFloating()` (o `Form::noFloating()` propagato ai
     * children) per disattivare il floating in modo dichiarativo,
     * coerentemente con la classe `wi-nf` lato Wonder.
     */
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderField($this->renderInput(), !$this->isNoFloating());
    }

    /**
     * Hook di wrapping del tema. Bootstrap usa il pattern
     * `form-floating` di default: l'input prima, poi la label,
     * il tutto in un div container. Sotto, l'errore.
     *
     * I componenti che NON funzionano col floating (es. Checkbox,
     * File) passano `$floating = false` per ottenere un wrap minimale.
     */
    protected function renderField(string $input, bool $floating = true): string
    {
        if ($floating) {
            $html = '<div><div class="form-floating">';
            $html .= $input;
            $html .= $this->renderLabel();
            $html .= '</div>';
            $html .= $this->renderError();
            $html .= '</div>';

            return $html;
        }

        return '<div>'.$input.$this->renderError().'</div>';
    }

    /**
     * Markup label tema Bootstrap: `<label>`. Skip se la label è
     * vuota (alcuni componenti come Hidden non hanno label).
     */
    protected function renderLabel(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $label = $this->resolvedLabel();

        if ($label === '') {
            return '';
        }

        return '<label for="'.$id.'">'.$this->escape($label).'</label>';
    }

    /**
     * Markup errore tema Bootstrap: div `invalid-feedback`. Quando
     * c'è errore aggiunge `d-block` per forzare la visibilità
     * (di default Bootstrap mostra `invalid-feedback` solo sui
     * sibling `.is-invalid`, ma noi vogliamo mostrarlo sempre).
     */
    protected function renderError(): string
    {
        $error = $this->errorMessage();
        $class = $error !== '' ? 'invalid-feedback d-block' : 'invalid-feedback';

        return '<div class="'.$class.'">'.$this->escape($error).'</div>';
    }

    /**
     * Aggiunge `is-invalid` alla classe base quando c'è errore di
     * validazione, in modo che Bootstrap renderizzi il bordo rosso.
     */
    protected function inputClass(string $base): string
    {
        return trim($base.($this->hasError() ? ' is-invalid' : ''));
    }
}
