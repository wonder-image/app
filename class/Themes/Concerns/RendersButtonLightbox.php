<?php

namespace Wonder\Themes\Concerns;

use InvalidArgumentException;
use Wonder\App\Dependencies;

trait RendersButtonLightbox
{
    protected function renderLightboxButton($class): string
    {
        $schema = $class->getSchema();
        if (($schema['form_method'] ?? '') === 'post'
            || in_array($schema['type'] ?? '', ['submit', 'reset'], true)) {
            throw new InvalidArgumentException('Lightbox requires a navigation button, not a form action.');
        }
        $button = clone $class;
        $items = $schema['lightbox'];
        unset($button->schema['lightbox']);
        if ($schema['disabled'] ?? false) {
            return $this->render($button);
        }
        Dependencies::fancyapps();
        $group = 'wi-button-'.bin2hex(random_bytes(8));
        $first = array_shift($items);
        $button->href($first['src'])->attr('data-fancybox', $group)->attr('data-type', $first['type']);
        $html = $this->render($button);
        foreach ($items as $item) {
            $attributes = $this->renderAttributes([
                'href' => $item['src'], 'data-fancybox' => $group,
                'data-type' => $item['type'], 'hidden' => true,
                'tabindex' => '-1', 'aria-hidden' => 'true',
            ]);
            $html .= '<a '.$attributes.'></a>';
        }
        $selector = json_encode('[data-fancybox="'.$group.'"]', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return $html.'<script>(function(){let bound=false;const bind=function(){'
            .'if(!bound&&typeof Fancybox!=="undefined"){Fancybox.bind('.$selector.',{Html:{autoSize:false}});bound=true;}'
            .'};bind();document.addEventListener("DOMContentLoaded",bind,{once:true});'
            .'window.addEventListener("loaded",bind,{once:true});window.addEventListener("load",bind,{once:true});})();</script>';
    }
}
