<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\RendersLegalDocument;
use Wonder\Themes\Wonder\Component;

class LegalDocument extends Component
{
    use RendersLegalDocument;

    public function render($class): string
    {
        return $this->renderLegalDocument($class, [
            'root' => ['wi-legal-document', 'w-100'],
            'title' => 'title',
            'content' => 'text w-100',
        ]);
    }
}
