<?php

    namespace Wonder\Themes\Bootstrap;
    
    use Wonder\Themes\Contracts\Renderer;

    abstract class Component implements Renderer {

        public function renderComponents( $components ):string 
        {

            $html = "";
            foreach ($components as $key => $component) { $html .= $component->render(); }

            return $html;

        }

}