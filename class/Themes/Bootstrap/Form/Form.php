<?php

    namespace Wonder\Themes\Bootstrap\Form;

    use Wonder\Themes\Bootstrap\Component;
    use Wonder\Themes\Bootstrap\Concerns\{ HasColumns, HasGap };

    class Form extends Component {

        use HasColumns, HasGap;

        public function render( $class ): string
        {

            $this->propagateNoFloating($class);

            $classColumn = $this->getColumns($class->columns);
            $classGap = $this->getGap($class->gap);

            # Start - Form
            $html = "<form action=\"\" method=\"post\" enctype=\"multipart/form-data\" onsubmit=\"loadingSpinner()\" class=\"$classColumn $classGap\">";

            # Field
            $html .= $this->renderComponents($class->components);

            # End - Form
            $html .= '</form>';

            return $html;

        }

        /**
         * Propaga la flag `no_floating` dal Form ai child Component
         * prima del rendering: vedi `Themes\Wonder\Form\Form` per la
         * semantica completa. Override per singolo campo possibile
         * (il child con `schema[no_floating]` già impostato non viene
         * sovrascritto).
         */
        private function propagateNoFloating(object $form): void
        {

            if (!array_key_exists('no_floating', $form->schema ?? [])) {
                return;
            }

            $value = (bool) $form->schema['no_floating'];

            foreach ($form->components ?? [] as $component) {
                if (!isset($component->schema) || !is_array($component->schema)) {
                    continue;
                }

                if (array_key_exists('no_floating', $component->schema)) {
                    continue;
                }

                $component->schema['no_floating'] = $value;
            }

        }

    }