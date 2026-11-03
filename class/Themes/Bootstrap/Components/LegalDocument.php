<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\RendersLegalDocument;

class LegalDocument extends Component
{
    use RendersLegalDocument;

    public function render($class): string
    {
        return $this->renderLegalDocument($class, [
            'root' => ['wi-legal-document'],
            'title' => 'h3 mb-3',
            'content' => 'w-100',
        ]);
    }
}
