<?php

namespace Wonder\App\ResourceSchema\Inputs;

use InvalidArgumentException;
use Wonder\App\Media\MediaFile;
use Wonder\App\Media\MediaTypes;
use Wonder\App\Media\Variants;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasMediaLibrary;
use Wonder\Elements\Form\Components\File as FileElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Upload drag&drop (Filepond). Stessi vincoli di {@see InputFile}, più la
 * strategia di upload lato client: `uploader('classic')` o il nome di un
 * uploader registrato.
 *
 * Con `mediaLibrary()` (o `mediaTypes()`, `mediaSlug()`, `mediaAlt()`) il
 * campo salva media della libreria: ogni file caricato diventa un media,
 * la colonna tiene l'id (o la lista JSON degli id con più file) e il
 * ridimensionatore lo prepara come gli altri media.
 *
 * ```php
 * FormField::key('image_id')->fileDragDrop('image')
 *     ->mediaLibrary()        // anche "Scegli dalla libreria"
 *     ->mediaSlug('{slug}')
 *     ->mediaAlt('{name}');
 * ```
 */
class InputFileDragDrop extends InputFile
{
    use HasMediaLibrary;

    protected string $helper = 'inputFileDragDrop';

    public function uploader(string $uploader = 'classic'): static
    {
        $this->schema['uploader'] = trim($uploader);

        return $this;
    }

    /**
     * Salva media della libreria. `$search` aggiunge "Scegli dalla
     * libreria", con la ricerca tra i media già caricati.
     */
    public function mediaLibrary(bool $search = true): static
    {
        $this->schema['media']['enabled'] = true;
        $this->schema['media']['library'] = $search;

        return $this;
    }

    /**
     * I tipi di media ammessi, di `MediaTypes`; senza, vengono da
     * `accept()`. Un file caricato diventa del primo tipo che lo accetta.
     */
    public function mediaTypes(string ...$types): static
    {
        foreach ($types as $type) {
            if (!MediaTypes::has($type)) {
                throw new InvalidArgumentException("Tipo di media sconosciuto: {$type}");
            }
        }

        $this->schema['media']['types'] = array_values(array_unique($types));
        $this->schema['media']['enabled'] = true;

        return $this;
    }

    /**
     * I tipi di media di una categoria di `accept()`.
     *
     * @return list<string>
     */
    public static function typesForAccept(string $accept): array
    {
        return match (strtolower(trim($accept))) {
            'svg' => [MediaTypes::ICON],
            'document', 'pdf' => [MediaTypes::DOCUMENT],
            'video', 'media-video' => [MediaTypes::VIDEO],
            'gallery' => [MediaTypes::IMAGE, MediaTypes::VIDEO],
            'media' => MediaTypes::all(),
            default => [MediaTypes::IMAGE],
        };
    }

    /**
     * @return array{input: string, type: string, types: list<string>, multiple: bool, library: bool, slug: string, alt: string, scope: string, scope_label: string}
     */
    public function mediaConfig(): array
    {
        $media = $this->mediaSchema();
        $types = (array) ($media['types'] ?? []);
        $types = $types !== [] ? array_values($types) : self::typesForAccept((string) ($this->schema['file'] ?? 'image'));

        return [
            'input' => 'files',
            'type' => $types[0],
            'types' => $types,
            'multiple' => (int) ($this->fileFormat()['max_file'] ?? 1) > 1,
            'library' => (bool) ($media['library'] ?? false),
            'slug' => (string) ($media['slug'] ?? ''),
            'alt' => (string) ($media['alt'] ?? ''),
            'scope' => (string) ($media['scope'] ?? ''),
            'scope_label' => (string) ($media['scope_label'] ?? ''),
        ];
    }

    protected function element(): ElementField
    {
        if (!$this->isMedia()) {
            return parent::element();
        }

        $config = $this->mediaConfig();
        $format = $this->fileFormat();
        $media = array_values(MediaFile::many(self::mediaIds($this->schema['value'] ?? null)));
        $folders = array_unique(array_map(static fn (MediaFile $file): string => MediaTypes::folder($file->type), $media));
        $root = rtrim(defined('APP_URL') ? (string) APP_URL : '', '/').'/assets/upload/';
        // Una cartella sola: nomi nudi e anteprime dalla misura piccola.
        $single = count($folders) <= 1;
        $directory = $single && $media !== [] ? MediaFile::directoryUrl($media[0]->type) : $root;
        $names = [];
        $map = [];

        foreach ($media as $file) {
            $name = $single ? $file->file : MediaTypes::folder($file->type).'/'.$file->file;
            $names[] = $name;
            $map[$name] = $file->id;
        }

        $poster = '';
        $widths = defined('RESPONSIVE_IMAGE_SIZES') && is_array(RESPONSIVE_IMAGE_SIZES)
            ? array_filter(array_map('intval', RESPONSIVE_IMAGE_SIZES), static fn (int $width): bool => $width > 0)
            : [];

        if ($single && $media !== [] && $widths !== [] && $media[0]->isImage()) {
            $poster = Variants::suffix($directory, $names, (string) min($widths));
        }

        $accept = [];

        foreach ($config['types'] as $type) {
            array_push($accept, ...MediaTypes::extensions($type));
        }

        return (new FileElement($this->name))
            ->file((string) ($this->schema['file'] ?? 'image'))
            ->uploader((string) ($this->schema['uploader'] ?? 'classic'))
            ->maxFile((int) ($format['max_file'] ?? 1))
            ->maxSize(max(array_map(MediaTypes::maxSize(...), $config['types'])))
            ->directory($directory)
            ->fileValue($names)
            ->sizeBefore(false)
            ->minSizeImage($poster)
            ->media([
                'extensions' => array_values(array_unique($accept)),
                'map' => $map,
                'library' => $config['library'],
                'types' => $config['types'],
                'scope' => $config['scope'],
                'scope_label' => $config['scope_label'],
                'search_url' => rtrim(defined('APP_URL') ? (string) APP_URL : '', '/').'/api/backend/media/search/',
            ]);
    }

    /**
     * Gli id salvati in una colonna media: un id o una lista JSON.
     *
     * @return list<int>
     */
    public static function mediaIds(mixed $value): array
    {
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $value = json_decode(trim($value), true);
        }

        $ids = [];

        foreach (is_array($value) ? $value : [$value] as $id) {
            if (is_int($id) || (is_string($id) && preg_match('/^\d+$/D', trim($id)) === 1)) {
                $id = (int) $id;

                if ($id > 0 && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }
}
