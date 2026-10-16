<?php

namespace Wonder\Themes\Bootstrap\Components;

use InvalidArgumentException;
use Wonder\Elements\Components\LinkCard as LinkCardElement;
use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\RendersLinkCards;
use Wonder\Themes\Concerns\HasAttributes;
use Wonder\Themes\Concerns\MergesClassAttribute;

class LinkCard extends Component
{
    use HasAttributes, MergesClassAttribute, RendersLinkCards;

    public function render($class): string
    {
        if (!$class instanceof LinkCardElement) {
            throw new InvalidArgumentException('Il renderer richiede un LinkCard.');
        }

        $schema = $class->getSchema();
        $description = trim((string) ($schema['description'] ?? ''));

        $html = $this->cardOpen($class, ['card', 'border', 'h-100']);
        $html .= '<div class="card-body d-flex align-items-center gap-3 position-relative">';
        $html .= $this->cardIcon($schema);
        $html .= '<div class="overflow-hidden">';
        $html .= '<a href="'.$this->escape((string) ($schema['href'] ?? '')).'" class="stretched-link link-body-emphasis text-decoration-none fw-semibold">'
            .$this->escape((string) ($schema['title'] ?? ''))
            .'</a>';

        if ($description !== '') {
            $html .= '<div class="small text-body-secondary text-truncate">'.$this->escape($description).'</div>';
        }

        $html .= '</div>';
        $html .= '<i class="bi bi-arrow-right ms-auto text-body-tertiary" aria-hidden="true"></i>';

        return $html.'</div></div>';
    }
}
