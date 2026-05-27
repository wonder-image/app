<?php

    namespace Wonder\Plugin\Custom\Input;

    use Wonder\Elements\Form\Components\reCAPTCHA as Element;

    /**
     * API legacy `->action()->theme()->size()->generate()` per il widget
     * reCAPTCHA v2 "Non sono un robot".
     *
     * È un thin wrapper sopra `Wonder\Elements\Form\Components\reCAPTCHA`:
     * tutta la logica di rendering (markup, siteKey da Credentials,
     * hidden input) vive nell'Element + renderer del tema attivo.
     * Questa classe esiste per retro-compatibilità con i caller esistenti
     * (es. `inputRecaptcha()`).
     */
    class reCAPTCHA {

        private Element $element;

        public function __construct() {
            $this->element = new Element();
        }

        public function action($value): self {
            $this->element->action(is_string($value) ? $value : null);
            return $this;
        }

        /**
         * @param mixed $value light | dark
         */
        public function theme($value): self {
            $this->element->theme(is_string($value) ? $value : null);
            return $this;
        }

        /**
         * @param mixed $value compact | normal
         */
        public function size($value): self {
            $this->element->size(is_string($value) ? $value : null);
            return $this;
        }

        public function generate(): string {
            return $this->element->render();
        }

    }
