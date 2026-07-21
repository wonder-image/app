<?php

namespace Wonder\Themes\Concerns;

trait RendersVideo
{
    use RendersMediaAttributes;

    protected function renderVideo(object $class): string
    {
        $attributes = $this->renderMediaAttributes(
            $class,
            $this->videoThemeClasses($class),
            ['poster' => $class->posterUrl()]
        );

        $sources = [];
        $webm = $class->webmUrl();

        if ($webm !== null) {
            $sources[] = $this->renderVideoSource($webm, 'video/webm');
        }

        $sources[] = $this->renderVideoSource($class->url());

        $html = '<video' . ($attributes !== '' ? ' ' . $attributes : '') . ">\n";
        $html .= '    ' . implode("\n    ", $sources) . "\n";
        $html .= "    Your browser does not support HTML video.\n";
        $html .= '</video>';

        if ($class->getSchema('filter') === true) {
            $html .= $this->renderVideoFilter($class);
        }

        return $html;
    }

    /** @return string[] */
    protected function videoThemeClasses(object $class): array
    {
        return [];
    }

    protected function renderVideoFilter(object $class): string
    {
        return '';
    }

    private function renderVideoSource(string $url, ?string $mimeType = null): string
    {
        return '<source src="' . $this->escape($url) . '" type="' . ($mimeType ?? $this->videoMimeType($url)) . '">';
    }

    private function videoMimeType(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo(is_string($path) ? $path : $url, PATHINFO_EXTENSION));

        return match ($extension) {
            'webm' => 'video/webm',
            'ogv', 'ogg' => 'video/ogg',
            'mov' => 'video/quicktime',
            'm4v' => 'video/x-m4v',
            default => 'video/mp4',
        };
    }
}
