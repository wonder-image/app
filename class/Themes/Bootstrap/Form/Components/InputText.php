<?php

    namespace Wonder\Themes\Bootstrap\Form\Components;

    use Wonder\Themes\Bootstrap\Form\Field;

    class InputText extends Field
    {

        public function renderInput(): string
        {

            $id = $this->schema['id'];
            $name = $this->schema['name'];
            $type = $this->schema['type'];
            $value = $this->schema['value'] ?? '';

            $attributes = $this->renderAttributes($this->schema['attributes']);

            return "<input class=\"form-control\" type=\"{$type}\" name=\"{$name}\" id=\"{$id}\" value=\"{$value}\" {$attributes} />";
        
        }

    }