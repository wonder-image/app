<?php

namespace Wonder\Themes\Concerns;

trait HasAttributes
{
    use EscapesHtml;

    protected function renderAttributes(?array $attributes): string
    {
        $html = [];

        if ($attributes != null) {
            foreach ($attributes ?? [] as $key => $value) {
                if (is_bool($value)) {
                    if ($value) {
                        $html[] = $key;
                    }
                } elseif (is_array($value)) {
                    $attr = $key . '="';
                    foreach ($value as $k => $c) {
                        $attr .= $this->escape((string) $c) . ' ';
                    }
                    $attr .= '"';
                    $html[] = $attr;
                } else {
                    $html[] = $key . '="' . $this->escape((string) $value) . '"';
                }
            }
        }

        return implode(' ', $html);
    }
}
