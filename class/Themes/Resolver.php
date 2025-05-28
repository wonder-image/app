<?php

    namespace Wonder\Themes;

    use Wonder\App\Theme;
    use Wonder\Themes\Contracts\Renderer;

    class Resolver
    {

        public static function renderer($class):Renderer
        {
            
            $theme = ucfirst(Theme::get());
            $className = str_replace('Wonder\\Elements\\', '', $class);

            $class = "Wonder\\Themes\\{$theme}\\{$className}";

            if (!class_exists($class)) {
                throw new \Exception("Classe {$class} non trovata.");
            }

            return new $class();

        }
        
    }