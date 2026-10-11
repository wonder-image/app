<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\RendersGoogleReviews;

class GoogleReviews extends Component
{
    use RendersGoogleReviews;

    public function render($class): string
    {
        return $this->renderGoogleReviews($class, [
            'root' => ['wi-google-reviews', 'd-grid', 'gap-3'],
            'summary' => 'd-flex align-items-center flex-wrap gap-2',
            'list' => 'list-unstyled d-grid gap-3 mb-0',
            'item' => 'border-top pt-3',
            'author' => 'd-flex align-items-center gap-2 fw-semibold',
            'meta' => 'd-flex align-items-center gap-2 small text-body-secondary',
            'text' => 'mb-1',
            'muted' => 'small text-body-secondary mb-0',
            'link' => 'link-body-emphasis',
        ]);
    }
}
