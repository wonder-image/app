<?php

    namespace Wonder\Themes\Bootstrap\Form;

    use Wonder\Themes\Bootstrap\Component;
    use Wonder\Themes\Bootstrap\Concerns\{ HasColumns, HasGap };

    class Form extends Component {

        use HasColumns, HasGap;

        public function render( $class ): string
        {

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

    }