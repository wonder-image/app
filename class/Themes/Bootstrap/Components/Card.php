<?php

    namespace Wonder\Themes\Bootstrap\Components;

    use Wonder\Themes\Bootstrap\Component;
    use Wonder\Themes\Bootstrap\Concerns\{ HasColumns, CanSpanColumn, HasGap };

    class Card extends Component {

        use HasColumns, CanSpanColumn, HasGap;

        public function render( $class ): string
        {

            $classSpanColumn = $this->getColumnSpan($class->columnSpan);
            $classColumn = $this->getColumns($class->columns);
            $classGap = $this->getGap($class->gap);
            
            # Start - Card
            $html = "<div class=\"$classSpanColumn\">";
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