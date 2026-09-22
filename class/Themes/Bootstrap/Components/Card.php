<?php

    namespace Wonder\Themes\Bootstrap\Components;

    use Wonder\Themes\Bootstrap\Component;
    use Wonder\Themes\Bootstrap\Concerns\{ HasColumns, CanSpanColumn, HasGap };
    use Wonder\Themes\Concerns\HasAttributes;

    class Card extends Component {

        use HasColumns, CanSpanColumn, HasGap, HasAttributes;

        public function render( $class ): string
        {

            $classSpanColumn = $this->getColumnSpan($class->columnSpan);
            $classColumn = $this->getColumns($class->columns);
            $classGap = $this->getGap($class->gap);
            // Gli attributi dichiarati con `attr()` finiscono sul div esterno:
            // è da lì che un riquadro intero si nasconde da sé, con le stesse
            // regole di visibilità condizionale dei campi.
            $attributes = $this->renderAttributes($class->getSchema('attributes'));
            $attributes = $attributes === '' ? '' : ' ' . $attributes;

            # Start - Card
            $html = "<div class=\"$classSpanColumn\"$attributes>";
            $html .= "<div class=\"card border\">";
            $html .= "<div class=\"card-body $classColumn $classGap\">";

            # Componenti
            $html .= $this->renderComponents($class->components);

            # End - Card
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

            return $html;

        }

    }