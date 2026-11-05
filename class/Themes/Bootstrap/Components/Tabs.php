<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Tabs as TabsElement;
use Wonder\Themes\Bootstrap\Component;

class Tabs extends Component
{
    public function render($class): string
    {
        return ResourceFormLayoutRenderer::renderTabs($class);
    }

    /**
     * Barra e pannelli attorno a contenuti già resi (uno per scheda, nello
     * stesso ordine di `tabs()`): li rende il layout, per dare ai campi la
     * loro colonna.
     *
     * @param array<int, string> $panels
     */
    public function renderInner(object $tabs, array $panels, string $row = 'row g-3'): string
    {
        $id = (string) $tabs->getSchema('id');
        $items = $tabs->tabs();

        if (count($items) === 1) {
            return '<div class="'.$row.'">'.($panels[0] ?? '').'</div>';
        }

        $active = $this->activeIndex($tabs, $items);
        $nav = '';
        $content = '';

        foreach ($items as $i => $item) {
            $target = $id.'-'.TabsElement::clean((string) $item['key']);
            $on = $i === $active;

            $nav .= '<li class="nav-item" role="presentation"><button class="nav-link'.($on ? ' active' : '').'"'
                .' type="button" data-bs-toggle="tab" data-bs-target="#'.$target.'" role="tab"'
                .' aria-controls="'.$target.'" aria-selected="'.($on ? 'true' : 'false').'">'
                .$this->escape((string) $item['label']).'</button></li>';

            $content .= '<div class="tab-pane fade'.($on ? ' show active' : '').'" id="'.$target.'" role="tabpanel">'
                .'<div class="'.$row.' pt-3">'.($panels[$i] ?? '').'</div></div>';
        }

        return '<ul class="nav nav-tabs" role="tablist">'.$nav.'</ul><div class="tab-content">'.$content.'</div>';
    }

    private function activeIndex(object $tabs, array $items): int
    {
        $wanted = $tabs->getSchema('active');

        foreach ($items as $i => $item) {
            if ($wanted !== null && $item['key'] === $wanted) {
                return $i;
            }
        }

        return 0;
    }
}
