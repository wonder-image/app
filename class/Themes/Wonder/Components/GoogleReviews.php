<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\RendersGoogleReviews;
use Wonder\Themes\Wonder\Component;

class GoogleReviews extends Component
{
    use RendersGoogleReviews;

    public function render($class): string
    {
        return $this->renderGoogleReviews($class, [
            'root' => ['wi-google-reviews', 'd-grid', 'gap-3'],
            'summary' => 'wi-google-reviews__row d-flex flex-wrap gap-2',
            'list' => 'wi-google-reviews__list d-grid gap-3 m-0',
            'item' => 'wi-google-reviews__item',
            'author' => 'wi-google-reviews__row d-flex gap-2',
            'meta' => 'wi-google-reviews__row d-flex gap-2 text-small',
            'text' => 'mb-1',
            'muted' => 'wi-google-reviews__muted text-small m-0',
            'link' => 'wi-google-reviews__link',
        ]);
    }
}
