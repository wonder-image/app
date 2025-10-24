<?php

    namespace Wonder\Themes\Wonder;
    
    use Wonder\Themes\Contracts\Renderer;
    use Wonder\Concerns\HasSchema;
    use Wonder\Themes\Concerns\HasAttributes;

    abstract class Component implements Renderer {

        use HasSchema, HasAttributes;

        public function renderComponents( $components ):string 
        {

            $html = "";
            foreach ($components as $key => $component) { $html .= $component->render(); }

            return $html;

        }

    }