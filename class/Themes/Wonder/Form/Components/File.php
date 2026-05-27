<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class File extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $label = $this->escape($this->resolvedLabel());
        $file = (string) ($this->schema['file'] ?? 'image');
        $maxFile = max(1, (int) ($this->schema['max_file'] ?? 1));
        $maxSize = max(1, (int) ($this->schema['max_size'] ?? 5)) * 1048576;
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $multiple = $maxFile > 1 ? 'multiple' : '';
        $nameArray = $name.'[]';
        $accept = $this->acceptByType($file);

        return <<<HTML
<div class="{$this->containerClass('file')}">
    <label for="{$id}" class="wi-label">{$label}</label>
    <input class="wi-input" id="{$id}" type="file" accept="{$accept}" name="{$nameArray}" data-wi-max-file="{$maxFile}" data-wi-max-size="{$maxSize}" data-wi-check="true"{$this->labelMarker()} {$multiple} {$attributes}>
    {$this->renderError()}
</div>
HTML;
    }

    private function acceptByType(string $file): string
    {
        return match ($file) {
            'image' => 'image/png, image/jpeg',
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'ico' => 'image/ico',
            'video' => 'video/mp4',
            'jpg' => 'image/jpeg',
            'font' => 'font/ttf',
            default => '',
        };
    }
}
