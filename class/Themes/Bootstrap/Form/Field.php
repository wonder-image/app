<?php

    namespace Wonder\Themes\Bootstrap\Form;

    use Wonder\Themes\Bootstrap\Component;
    use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
    use Wonder\Themes\Concerns\HasAttributes;

    abstract class Field extends Component {

        use CanSpanColumn, HasAttributes;

        public array $schema;

        public function render($class): string
        {

            $classColumn = $this->getColumnSpan($class->columnSpan);
            $this->schema = $class->schema;
            
            # Start - Column
            $html = "<div class=\"$classColumn\">";

            # Field
            $html .= '<div class="form-floating">';
            $html .= $this->renderInput();
            $html .= $this->renderLabel();
            $html .= '</div>';

            # End - Column
            $html .= '</div>';

            return $html;

        }

        abstract public function renderInput(): string;

        public function renderLabel(): string
        {

            $id = $this->schema['id'];
            $label = $this->schema['label'];

            if (isset($this->schema['attributes']['required']) && $this->schema['attributes']['required']) { $label .= "*"; }

            return "<label for=\"{$id}\">{$label}</label>";

        }
        
    }