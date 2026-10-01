<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Concerns\HasAttributes;

class DataItem extends Component
{
    use CanSpanColumn, HasAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $span = $this->getColumnSpan($class->columnSpan);
        $value = (string) ($schema['value'] ?? '');
        $raw = (bool) ($schema['html'] ?? false);
        $attributes = $this->renderAttributes($schema['attributes'] ?? null);

        if (trim($value) === '') {
            $body = '<span class="text-muted">'.$this->escape((string) ($schema['placeholder'] ?? '—')).'</span>';
        } else {
            $body = $raw ? $value : $this->escape($value);
        }

        return "<div class=\"{$span}\">"
            .'<div class="mb-3"'.($attributes !== '' ? ' '.$attributes : '').'>'
            .'<div class="small text-muted">'.$this->escape((string) ($schema['label'] ?? '')).(string) ($schema['action'] ?? '').'</div>'
            ."<div>{$body}</div>"
            .'</div></div>';
    }
}
