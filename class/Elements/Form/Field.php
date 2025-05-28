<?php
    
    namespace Wonder\Elements\Form;

    use Wonder\Elements\Form\Components\Schema;

    use Wonder\Elements\Concerns\{ CanSpanColumn, Renderer };

    abstract class Field extends Schema {

        use CanSpanColumn, Renderer;
       
    }