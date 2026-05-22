<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class File extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderField($this->renderInput(), false);
    }

    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $label = trim((string) ($this->schema['label'] ?? ''));
        $file = (string) ($this->schema['file'] ?? 'image');
        $uploader = (string) ($this->schema['uploader'] ?? 'classic');
        $maxFile = max(1, (int) ($this->schema['max_file'] ?? 1));
        $maxSize = max(1, (int) ($this->schema['max_size'] ?? 5));
        $directory = $this->escape((string) ($this->schema['directory'] ?? ''));
        $rawValue = $this->schema['file_value'] ?? '';
        $value = is_array($rawValue) ? '' : $this->escape((string) $rawValue);
        $imageSize = $this->escape((string) ($this->schema['min_size_image'] ?? ''));
        $sizeBefore = !empty($this->schema['size_before']) ? 'true' : 'false';
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $accept = $this->acceptByType($file);
        $acceptLabel = $this->acceptLabelByType($file);
        $multiple = $maxFile > 1 ? 'multiple' : '';
        $class = $maxFile > 1 ? ' filepond--multiple' : '';
        $nameArray = $name.'[]';

        if (!empty($this->schema['attributes']['required'])) {
            $label .= '*';
        }

        $labelHtml = $label !== '' ? '<h6>'.$this->escape($label).'</h6>' : '';

        if ($labelHtml !== '') {
            $class .= ' mt-1';
        }

        return <<<HTML
<div id="container-{$id}" class="w-100">
    {$labelHtml}
    <div class="w-100{$class}">
        <input id="{$id}" type="file" accept="{$accept}" name="{$nameArray}" data-max-file-size="{$maxSize}MB" data-min-size-image="{$imageSize}" data-size-before="{$sizeBefore}" data-max-files="{$maxFile}" data-wi-dir="{$directory}" data-wi-value="{$value}" data-wi-uploader="{$this->escape($uploader)}" data-wi-uploader-label="{$this->escape($acceptLabel)}" data-wi-check="true" {$multiple} {$attributes}>
    </div>
</div>
HTML;
    }

    private function acceptByType(string $file): string
    {
        return match ($file) {
            'image' => 'image/png, image/jpeg',
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'ico' => 'image/ico, image/x-icon',
            'media' => 'image/png, image/jpeg, image/webp, application/pdf',
            'video' => 'video/mp4',
            'jpg' => 'image/jpeg',
            'font' => 'font/ttf',
            default => '',
        };
    }

    private function acceptLabelByType(string $file): string
    {
        return match ($file) {
            'image', 'png', 'ico', 'jpg' => 'la tua immagine',
            'pdf' => 'il tuo PDF',
            'media' => 'i tuoi file',
            'video' => 'il tuo video',
            'font' => 'il tuo font',
            default => 'il tuo file',
        };
    }
}
