<?php

namespace Wonder\Themes\Bootstrap\Components;

use InvalidArgumentException;
use Wonder\Elements\Components\LinkListCard as LinkListCardElement;
use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\RendersLinkCards;
use Wonder\Themes\Concerns\HasAttributes;
use Wonder\Themes\Concerns\MergesClassAttribute;

class LinkListCard extends Component
{
    use HasAttributes, MergesClassAttribute, RendersLinkCards;

    public function render($class): string
    {
        if (!$class instanceof LinkListCardElement) {
            throw new InvalidArgumentException('Il renderer richiede un LinkListCard.');
        }

        $schema = $class->getSchema();
        $grouped = $class->isGrouped();
        $count = count((array) ($schema['links'] ?? []));

        // overflow-hidden: le voci flush seguono gli angoli arrotondati della card.
        $html = $this->cardOpen($class, ['card', 'border', 'h-100', 'overflow-hidden']);
        $html .= '<div class="card-header bg-transparent d-flex align-items-center gap-3 py-3">';
        $html .= $this->cardIcon($schema);
        $html .= '<h3 class="h6 mb-0">'.$this->escape((string) ($schema['title'] ?? '')).'</h3>';

        if (($schema['count'] ?? true) && $count > 0) {
            $html .= '<span class="badge rounded-pill bg-body-tertiary text-body-secondary border ms-auto">'.$count.'</span>';
        }

        $html .= '</div>';
        $html .= $grouped ? '<div class="row g-0">' : '';

        foreach ($class->groups() as $group) {
            if ($grouped) {
                $title = $group['title'] !== '' ? $group['title'] : (string) ($schema['ungrouped_title'] ?? '');
                $html .= '<div class="col-12 col-md-6 col-xl-3">';
                $html .= '<div class="px-3 pt-3 pb-1 small text-uppercase fw-semibold text-body-secondary">'.$this->escape($title).'</div>';
            }

            $html .= '<div class="list-group list-group-flush">';

            foreach ($group['links'] as $link) {
                $html .= '<a href="'.$this->escape($link['href']).'" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-2">'
                    .$this->escape($link['title'])
                    .'<i class="bi bi-chevron-right small text-body-tertiary" aria-hidden="true"></i>'
                    .'</a>';
            }

            $html .= '</div>';
            $html .= $grouped ? '</div>' : '';
        }

        $html .= $grouped ? '</div>' : '';

        return $html.'</div>';
    }
}
