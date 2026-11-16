<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\App\Media\MediaInputs;
use Wonder\App\Media\MediaTypes;
use Wonder\Themes\Bootstrap\Form\Field;
use Wonder\Themes\Support\PageAssets;

class File extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderField($this->renderInput(), false);
    }

    public function renderInput(): string
    {
        $mode = (string) ($this->schema['mode'] ?? 'dragdrop');

        return $mode === 'classic'
            ? $this->renderClassic()
            : $this->renderDragDrop();
    }

    private function renderDragDrop(): string
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
        $value = $this->escape(is_array($rawValue) ? json_encode(array_values($rawValue), JSON_THROW_ON_ERROR) : (string) $rawValue);
        $imageSize = $this->escape((string) ($this->schema['min_size_image'] ?? ''));
        $sizeBefore = !empty($this->schema['size_before']) ? 'true' : 'false';
        $attributes = array_merge(
            ['data-wi-file-references' => 'true'],
            (array) ($this->schema['attributes'] ?? [])
        );
        // Qui la classe resta del campo: il tag non ne scrive una sua.
        unset($attributes['data-wi-check']);
        // FilePond sostituisce l'input e ne perde gli attributi: le regole di
        // visibleWhen()/hiddenWhen() vanno sul contenitore, che resta.
        $visibility = [];
        foreach ($attributes as $key => $rule) {
            if (str_starts_with((string) $key, 'data-visible-when') || str_starts_with((string) $key, 'data-hidden-when')) {
                $visibility[$key] = $rule;
                unset($attributes[$key]);
            }
        }
        $visibility = $visibility === [] ? '' : ' '.$this->renderAttributes($visibility);
        $attributes = $this->renderAttributes($attributes);
        $media = is_array($this->schema['media'] ?? null) ? $this->schema['media'] : null;
        $accept = $media !== null ? $this->acceptByExtensions((array) ($media['extensions'] ?? [])) : $this->acceptByType($file);
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

        $mediaRoot = '';
        $mediaHtml = '';
        $assets = '';

        if ($media !== null) {
            [$mediaRoot, $mediaHtml, $assets] = $this->mediaParts($media, (string) ($this->schema['name'] ?? ''), $attributes);
        }

        return <<<HTML
<div id="container-{$id}" class="w-100"{$visibility}{$mediaRoot}>
    {$labelHtml}
    <div class="w-100{$class}">
        <input id="{$id}" type="file" accept="{$accept}" name="{$nameArray}" data-max-file-size="{$maxSize}MB" data-min-size-image="{$imageSize}" data-size-before="{$sizeBefore}" data-max-files="{$maxFile}" data-wi-dir="{$directory}" data-wi-value="{$value}" data-wi-uploader="{$this->escape($uploader)}" data-wi-uploader-label="{$this->escape($acceptLabel)}" data-wi-check="true" {$multiple} {$attributes}>
    </div>{$mediaHtml}
</div>{$assets}
HTML;
    }

    /**
     * Il campo che salva media della libreria: la mappa nome → id dei file
     * salvati, il nome per le scelte dalla libreria e, se serve, il bottone
     * "Scegli dalla libreria" con la finestra di `MediaPicker`.
     *
     * @param array<string, mixed> $media
     * @return array{0: string, 1: string, 2: string}
     */
    private function mediaParts(array $media, string $name, string $attributes): array
    {
        $map = (array) ($media['map'] ?? []);
        $types = array_values(array_filter((array) ($media['types'] ?? []), MediaTypes::has(...)));
        $types = $types !== [] ? $types : [MediaTypes::IMAGE];
        $root = ' data-wi-media="'.$this->escape($types[0]).'" data-wi-media-files="'.$this->escape(MediaInputs::fieldName($name, MediaInputs::PICKS_SUFFIX)).'"'
            .' data-wi-media-types="'.$this->escape(implode(',', $types)).'"';
        $only = array_values(array_filter((array) ($media['only'] ?? []), 'is_string'));

        if ($only !== []) {
            $root .= ' data-wi-media-extensions="'.$this->escape(implode(',', $only)).'"';
        }
        $html = '<input type="hidden" name="'.$this->escape(MediaInputs::fieldName($name, MediaInputs::MAP_SUFFIX)).'"'
            .' value="'.$this->escape(json_encode($map === [] ? new \stdClass : $map, JSON_THROW_ON_ERROR)).'">';
        $assets = PageAssets::once('wi-media-files', static fn (): string => self::filesScript());

        if (empty($media['library'])) {
            return [$root, $html, $assets];
        }

        $scope = trim((string) ($media['scope'] ?? ''));
        $root .= ' data-wi-media-search="'.$this->escape((string) ($media['search_url'] ?? '')).'"'
            .' data-wi-media-title="Scegli dalla libreria"';

        if ($scope !== '') {
            $root .= ' data-wi-media-scope="'.$this->escape($scope).'"'
                .' data-wi-media-scope-label="'.$this->escape(trim((string) ($media['scope_label'] ?? ''))).'"';
        }

        // Il bottone della libreria sta dentro l'area di trascinamento:
        // lo scrive lo script nell'etichetta di FilePond.
        $locked = str_contains($attributes, ' disabled') || str_contains($attributes, 'readonly');
        $root .= ' data-wi-media-drop="'.$this->escape($this->acceptLabelByType((string) ($this->schema['file'] ?? 'image'))).'"'
            .($locked ? ' data-wi-media-locked' : '');
        $assets .= PageAssets::once('wi-media-field', static fn (): string => MediaPicker::modal().MediaPicker::style().MediaPicker::script());

        return [$root, $html, $assets];
    }

    /** @param list<string> $extensions */
    private function acceptByExtensions(array $extensions): string
    {
        $accept = [];

        foreach ($extensions as $extension) {
            // I documenti per estensione: i MIME di Office cambiano da browser a browser.
            $accept[] = self::MIME[$extension] ?? '.'.$extension;
        }

        return implode(', ', array_values(array_unique($accept)));
    }

    private const MIME = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
    ];

    /**
     * Un media scelto dalla libreria entra nell'uploader come file leggero
     * (l'anteprima) con `wiMediaId`; all'invio `<campo>__wi_picks` dice, per
     * ogni file dell'uploader, l'id scelto o `null`. Il server usa l'id e
     * ignora il contenuto del file.
     */
    public static function filesScript(): string
    {
        $mime = json_encode(self::MIME, JSON_THROW_ON_ERROR);

        return <<<HTML
<script data-wi-media-files-script>
(function () {
    if (window.wiMediaFilesBound) { return; }
    window.wiMediaFilesBound = true;

    var MIME = {$mime};
    var pondOf = function (field) {
        var root = field.querySelector('.filepond--root');
        return root && window.FilePond ? window.FilePond.find(root) : null;
    };

    document.addEventListener('wi:media-pick', function (event) {
        var field = event.target instanceof Element ? event.target.closest('[data-wi-media-files]') : null;
        var item = event.detail || {};
        var pond = field ? pondOf(field) : null;
        if (!pond || !item.id || !item.file) { return; }
        var already = pond.getFiles().some(function (file) { return file.getMetadata('wiMediaId') === item.id; });
        if (already) { return; }
        var extension = String(item.file).split('.').pop().toLowerCase();
        var type = MIME[extension] || '';
        var add = function (blob) {
            var file = new File([blob], item.file, { type: type });
            if (Number(pond.maxFiles) <= 1 && pond.getFiles().length) { pond.removeFiles(); }
            pond.addFile(file, { metadata: { wiMediaId: item.id, wiOriginal: item.url || '' } });
        };
        if (!item.thumbnail) { add(new Blob([], { type: type })); return; }
        fetch(item.thumbnail, { credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.blob() : Promise.reject(new Error('media')); })
            .then(add)
            .catch(function () { add(new Blob([], { type: type })); });
    });

    // L'etichetta dell'area: tre righe con lo stesso testo, le azioni sottolineate.
    var dropLabel = function (field) {
        var box = document.createElement('span');
        box.className = 'wi-media-drop';
        var line = function (before, action) {
            var row = document.createElement('span');
            row.append(before);
            if (action) { row.append(action); }
            return row;
        };
        var choose = document.createElement('button');
        choose.type = 'button';
        choose.className = 'wi-media-drop-action';
        choose.setAttribute('data-wi-media-choose', '');
        choose.disabled = field.hasAttribute('data-wi-media-locked');
        choose.textContent = 'scegli dalla libreria';
        var browse = document.createElement('span');
        browse.className = 'filepond--label-action wi-media-drop-action';
        browse.textContent = 'cerca sul computer';
        box.append(
            line('Trascina qui ' + field.getAttribute('data-wi-media-drop')),
            line('oppure ', choose),
            line('oppure ', browse)
        );
        return box.outerHTML;
    };
    var decorate = function () {
        document.querySelectorAll('[data-wi-media-drop]').forEach(function (field) {
            var pond = pondOf(field);
            if (!pond || field.wiMediaDropReady) { return; }
            field.wiMediaDropReady = true;
            pond.labelIdle = dropLabel(field);
        });
    };
    document.addEventListener('FilePond:init', function () { window.setTimeout(decorate); });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { window.setTimeout(decorate); });
    }
    window.addEventListener('load', decorate);
    decorate();

    // formdata non risale: si ascolta in cattura.
    document.addEventListener('formdata', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) { return; }
        form.querySelectorAll('[data-wi-media-files]').forEach(function (field) {
            var pond = pondOf(field);
            if (!pond || pond.disabled || field.closest('fieldset:disabled')) { return; }
            var picks = pond.getFiles().map(function (file) { return file.getMetadata('wiMediaId') || null; });
            event.formData.set(field.getAttribute('data-wi-media-files'), JSON.stringify(picks));
        });
    }, true);
})();
</script>
HTML;
    }

    private function renderClassic(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $label = $this->escape($this->resolvedLabel());
        $file = (string) ($this->schema['file'] ?? 'image');
        $maxFile = max(1, (int) ($this->schema['max_file'] ?? 1));
        $maxSize = max(1, (int) ($this->schema['max_size'] ?? 5));
        $accept = $this->acceptByType($file);
        $extensionsAccept = $this->escape((string) ($this->schema['extensions_accept'] ?? ''));
        $galleryHtml = (string) ($this->schema['gallery_html'] ?? '');
        $attributes = (array) ($this->schema['attributes'] ?? []);
        $class = $this->fieldClass('form-control', $attributes);
        $attributesString = $this->fieldAttributes(['data-wi-check', 'style'], $attributes);
        $nameArray = $name.'[]';
        $multiple = $maxFile > 1 ? ' multiple' : '';

        return <<<HTML
<div id="container-{$id}" class="w-100 wi-container-files">
    <h6>{$label}</h6>
    <div class="w-100 mt-1">
        <input class="{$class}" style="width: 100%;" id="{$id}" type="file" accept="{$accept}" name="{$nameArray}" data-wi-max-file="{$maxFile}" data-wi-max-size="{$maxSize}" data-wi-check="true"{$multiple}{$attributesString}>
        <div class="invalid-feedback"> </div>
    </div>
    <div class="w-100 mt-1">
        <small>
            <ul>
                <li>File ammessi: <b>{$extensionsAccept}</b></li>
                <li>File massimi: <b>{$maxFile}</b></li>
                <li>Peso massimo: <b>{$maxSize}Mb</b></li>
            </ul>
        </small>
    </div>
    {$galleryHtml}
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
            'svg' => 'image/svg+xml',
            // Documenti della libreria Media: per estensione, i MIME di Office cambiano da browser a browser.
            'document' => $this->extensionList(MediaTypes::extensions(MediaTypes::DOCUMENT)),
            'media' => 'image/png, image/jpeg, image/webp, image/svg+xml, '.$this->extensionList(MediaTypes::extensions(MediaTypes::DOCUMENT)),
            'video' => 'video/mp4',
            // Video della libreria Media: mp4, webm e mov.
            'media-video' => 'video/mp4, video/webm, video/quicktime',
            // Foto e video insieme: un catalogo ha bisogno di tutti e due, e
            // un campo per tipo vorrebbe dire due caselle da spiegare.
            'gallery' => 'image/png, image/jpeg, image/webp, video/mp4',
            'jpg' => 'image/jpeg',
            'font' => 'font/ttf',
            'spreadsheet' => '.xlsx, .csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv',
            default => '',
        };
    }

    /** @param list<string> $extensions */
    private function extensionList(array $extensions): string
    {
        return implode(', ', array_map(static fn (string $extension): string => '.'.$extension, $extensions));
    }

    private function acceptLabelByType(string $file): string
    {
        return match ($file) {
            'image', 'png', 'ico', 'jpg' => 'la tua immagine',
            'svg' => 'la tua icona',
            'pdf' => 'il tuo PDF',
            'document' => 'il tuo documento',
            'media' => 'i tuoi file',
            'video', 'media-video' => 'il tuo video',
            'gallery' => 'le tue foto e i tuoi video',
            'font' => 'il tuo font',
            'spreadsheet' => 'il tuo foglio',
            default => 'il tuo file',
        };
    }
}
