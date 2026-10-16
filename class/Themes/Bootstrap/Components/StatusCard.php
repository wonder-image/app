<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Elements\Components\AbstractValueCard as ValueCardElement;
use Wonder\Themes\Bootstrap\Concerns\RendersLinkCards;

class StatusCard extends AbstractValueCard
{
    use RendersLinkCards;

    protected function renderBody(ValueCardElement $class, array $schema): string
    {
        $tag = $this->valueTag($schema);
        $detail = trim((string) ($schema['detail'] ?? ''));
        $href = trim((string) ($schema['href'] ?? ''));

        // Il link allargato copre il card-body, che fa da contenitore.
        $html = '<div class="card-body d-flex align-items-center gap-3 position-relative">';
        $html .= $this->cardIcon($schema, 'p-3 fs-4');
        $html .= '<div class="overflow-hidden">';
        $html .= '<div class="small text-body-secondary">'.$this->title($schema).'</div>';
        $html .= "<{$tag} class=\"mb-0\">".$this->displayValue($schema)."</{$tag}>";

        if ($detail !== '') {
            $html .= '<div class="small text-body-secondary text-truncate">'.$this->escape($detail).'</div>';
        }

        $html .= '</div>';

        if ($href !== '') {
            $label = (string) ($schema['title'] ?? '').': '.$this->scalarToString($schema['value'] ?? '');
            $html .= '<a href="'.$this->escape($href).'" class="stretched-link" aria-label="'.$this->escape($label).'"></a>';
        }

        return $html.'</div>';
    }
}
