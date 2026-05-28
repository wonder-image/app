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
        $script = $this->renderDataTransferScript($id);

        return <<<HTML
<div class="{$this->containerClass('file')}">
    <label for="{$id}" class="wi-label">{$label}</label>
    <input class="wi-input" id="{$id}" type="file" accept="{$accept}" name="{$nameArray}" data-wi-max-file="{$maxFile}" data-wi-max-size="{$maxSize}" data-wi-check="true"{$this->labelMarker()} {$multiple} {$attributes}>
    {$this->renderError()}
    {$script}
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

    /**
     * Ricostruisce `input.files` lato client per i file già caricati,
     * come faceva la vecchia `inputFile()` di `app/function/frontend/input.php`.
     * Il client (wonder-image/lib) usa `input.files` per rendere lo stato
     * `compiled` del controllo e per il check "max-file".
     *
     * Il `value` arriva da `hydrate()` (class/App/Support/FormFieldElementFactory.php:149)
     * via `->value($field->get('value'))`. Può essere:
     *   - string JSON: '["/upload/.../a.jpg","/upload/.../b.jpg"]'
     *   - string singolo path: '/upload/.../a.jpg'
     *   - array di path già decodificato
     * Tutto il resto viene ignorato (no script emit).
     */
    private function renderDataTransferScript(string $id): string
    {
        $value = $this->schema['value'] ?? ($this->schema['file_value'] ?? null);

        if ($value === null || $value === '' || $value === [] || $value === '[]') {
            return '';
        }

        $files = $this->normalizeFileValue($value);

        if ($files === []) {
            return '';
        }

        $lines = ['var dataTransfer = new DataTransfer();', ''];

        foreach ($files as $path) {
            $fileName = $this->escapeJs(basename($path));
            $fileType = $this->escapeJs($this->resolveMimeType($path));

            $lines[] = "dataTransfer.items.add(new File([], '{$fileName}', { type: '{$fileType}' }));";
        }

        $lines[] = "document.querySelector('input[type=\"file\"]#{$id}').files = dataTransfer.files;";

        $body = implode("\n", $lines);

        return "<script>{$body}</script>";
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function normalizeFileValue($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn($v) => is_string($v) ? trim($v) : '', $value)));
        }

        if (!is_string($value)) {
            return [];
        }

        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return array_values(array_filter(array_map(static fn($v) => is_string($v) ? trim($v) : '', $decoded)));
        }

        return [$value];
    }

    /**
     * `mime_content_type()` richiede che il file esista realmente. Quando
     * il path è remoto o assente, ricado sull'estensione e poi su
     * `application/octet-stream` come fallback.
     */
    private function resolveMimeType(string $path): string
    {
        if ($path !== '' && @is_file($path) && function_exists('mime_content_type')) {
            $type = @mime_content_type($path);

            if (is_string($type) && $type !== '') {
                return $type;
            }
        }

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            default => 'application/octet-stream',
        };
    }

    private function escapeJs(string $value): string
    {
        return str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", '\\n', ''], $value);
    }
}
