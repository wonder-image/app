<?php

namespace Wonder\Themes\Concerns;

trait RendersBreadcrumb
{
    use RendersComponentAttributes;

    protected function renderBreadcrumb(object $class, bool $bootstrap): string
    {
        $items = $class->getItems();
        if ($items === []) return '';

        $nav = clone $class;
        $nav->attr('aria-label', $class->getLabel());
        $html = '<nav '.$this->renderComponentAttributes($nav).'>';
        $html .= $bootstrap ? '<ol class="breadcrumb">'
            : '<ol class="d-flex f-wrap gap-1 text-small pl-0" style="list-style: none;">';

        foreach ($items as $index => $item) {
            $name = $this->escape((string) ($item['name'] ?? ''));
            $last = $index === array_key_last($items);
            if ($last) {
                $html .= $bootstrap
                    ? '<li class="breadcrumb-item active" aria-current="page">'.$name.'</li>'
                    : '<li><span aria-current="page">'.$name.'</span></li>';
                continue;
            }
            $url = (string) ($item['url'] ?? '');
            if (!preg_match('~^https?://~i', $url) && !str_starts_with($url, '#') && function_exists('__u')) {
                $url = __u(ltrim($url, '/'));
            }
            $html .= '<li'.($bootstrap ? ' class="breadcrumb-item"' : '').'><a href="'.$this->escape($url).'">'.$name.'</a></li>';
            if (!$bootstrap) $html .= '<li aria-hidden="true"><span>/</span></li>';
        }

        return $html.'</ol></nav>';
    }
}
