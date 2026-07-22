<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Elements\Components\AbstractValueCard as ValueCardElement;

class InfoCard extends AbstractValueCard
{
    protected function renderBody(ValueCardElement $class, array $schema): string
    {
        $tag = $this->valueTag($schema);

        return '<div class="card-header">'.$this->title($schema).'</div>'
            .'<div class="card-body">'
            ."<{$tag} class=\"card-title mb-0\">".$this->displayValue($schema)."</{$tag}>"
            .'</div>';
    }
}
