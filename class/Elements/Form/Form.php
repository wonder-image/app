<?php

    namespace Wonder\Elements\Form;

    use Wonder\Elements\Component;
    use Wonder\Elements\Concerns\IsContainer;

    /**
     * Container `<form>` lato Elements (layer dichiarativo).
     *
     * Aggrega `Field`/Component via `IsContainer::components()`. Il
     * rendering vero finisce nei `Themes\{Wonder,Bootstrap}\Form\Form`
     * che leggono `$this->components` e `$this->schema` e producono il
     * markup.
     */
    class Form extends Component
    {

        use IsContainer;

        /**
         * Disattiva il pattern "floating label" per TUTTI i campi del
         * form, in un colpo solo, come default propagato ai children
         * al momento del rendering.
         *
         * Override per singolo campo: chiama `noFloating()` (oppure
         * `noFloating(false)`) sul campo stesso, vince sul default del
         * Form (vedi propagazione in `Themes\Wonder\Form\Form::render()`
         * e `Themes\Bootstrap\Form\Form::render()`).
         *
         * Esempio:
         *
         * ```php
         * (new Form())
         *   ->noFloating()                              // default: tutti no-floating
         *   ->components([
         *       (new InputText('name'))->label('Nome'),
         *       (new InputEmail('email'))->label('Email')->noFloating(false),  // questo SÌ floating
         *   ]);
         * ```
         */
        public function noFloating(bool $noFloating = true): self
        {

            return $this->schema('no_floating', $noFloating);

        }

    }
