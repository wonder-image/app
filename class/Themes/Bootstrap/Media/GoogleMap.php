<?php

namespace Wonder\Themes\Bootstrap\Media;

use Wonder\Themes\Concerns\RendersGoogleMap;

class GoogleMap extends Media
{
    use RendersGoogleMap;

    protected function renderMedia($class): string
    {
        $map = $this->googleMapContext($class);

        return "<div {$map['attributes']}></div>"
            . $this->renderGoogleMapScript($map['map_id_json'], $map['config_json']);
    }
}
