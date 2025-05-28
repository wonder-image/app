<?php

    namespace Wonder\Elements\Components;

    use Wonder\Elements\Components\Component;

    use Wonder\Elements\Concerns\{ HasColumns, CanSpanColumn, HasGap, Renderer };

    class Card extends Component {

        use HasColumns, CanSpanColumn, HasGap, Renderer;

    }
