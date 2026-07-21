<?php

namespace Wonder\Themes\Concerns;

trait RendersIframe
{
    use RendersMediaAttributes;

    protected function renderIframe(object $class): string
    {
        $attributes = $this->renderMediaAttributes(
            $class,
            $this->iframeThemeClasses($class),
            ['src' => $class->srcUrl()]
        );

        return '<iframe' . ($attributes !== '' ? ' ' . $attributes : '') . '></iframe>';
    }

    /** @return string[] */
    protected function iframeThemeClasses(object $class): array
    {
        return [];
    }
}
