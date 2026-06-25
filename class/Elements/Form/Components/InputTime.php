<?php
    
    namespace Wonder\Elements\Form\Components;

    class InputTime extends InputText {

        public string $type = 'time';

        public function step(int $seconds): self
        {

            return $this->attr('step', max(1, $seconds));

        }

    }
