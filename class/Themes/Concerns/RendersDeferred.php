<?php

namespace Wonder\Themes\Concerns;

use Wonder\Elements\Component;
use Wonder\Elements\Components\Button;

trait RendersDeferred
{
    use RendersMediaAttributes;

    protected function renderDeferred(object $class, string $theme): string
    {
        $content = $class->getSchema('content');
        $html = $content instanceof Component ? $content->render($theme)
            : (is_object($content) ? $content->render() : $content);
        $button = $class->getSchema('deferred-button');
        $button = $button instanceof Button ? clone $button
            : Button::make(__t('components.media.load_content'));
        $fallback = (string) ($class->getSchema('fallback-url') ?? '');
        $button->type('button')->attr('data-wi-deferred-trigger', '');
        if ($fallback !== '') {
            $button->href($fallback)->blank()->rel('noopener noreferrer');
        }
        $attributes = $this->renderMediaAttributes($class, ['wi-deferred'], [
            'data-wi-deferred' => $class->getSchema('deferred-mode'),
            'data-wi-deferred-state' => 'idle',
        ]);

        return '<div '.$attributes.'><div class="wi-deferred-placeholder" data-wi-deferred-placeholder>'
            .$button->render($theme).'</div><template data-wi-deferred-template>'.$html.'</template></div>';
    }
}
