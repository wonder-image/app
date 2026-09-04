<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasUpload;
use Wonder\Elements\Form\Components\File as FileElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Upload "classic": `form-control` più la lista di file ammessi, numero
 * massimo e peso massimo.
 *
 * La categoria di file (`image`, `pdf`, `video`, `font`, `media`, ...) si
 * imposta con `accept()`; il renderer la traduce in attributo `accept="..."`
 * e in label informativa.
 */
class InputFile extends Input
{
    use HasUpload;

    protected string $helper = 'inputFile';

    /**
     * Lo stesso Element `File` serve sia l'upload classic sia il drag&drop:
     * a distinguerli è `uploader`, che solo {@see InputFileDragDrop} espone.
     */
    protected function element(): ElementField
    {
        $format = $this->fileFormat();

        return (new FileElement($this->name))
            ->file((string) ($this->schema['file'] ?? 'image'))
            ->uploader((string) ($this->schema['uploader'] ?? 'classic'))
            ->maxFile((int) ($format['max_file'] ?? 1))
            ->maxSize((int) ($format['max_size'] ?? 5))
            ->directory($this->fileDirectory(isset($format['dir']) ? (string) $format['dir'] : null))
            ->fileValue($this->schema['value'] ?? null)
            ->sizeBefore((bool) ($format['size_before'] ?? false))
            ->minSizeImage(isset($format['min_size_image']) ? (string) $format['min_size_image'] : null);
    }

    /**
     * Vincoli di upload derivati da `prepare`: numero e peso massimi,
     * sottocartella, e — se il campo dichiara un `resize` — la dimensione
     * minima richiesta all'immagine caricata.
     *
     * @return array<string, mixed>
     */
    protected function fileFormat(): array
    {
        $format = [];
        $prepare = (array) ($this->schema['prepare'] ?? []);

        $format['max_file'] = isset($prepare['max_file'])
            ? max(1, (int) $prepare['max_file'])
            : ((bool) ($this->schema['multiple'] ?? false) ? 10 : 1);
        $format['max_size'] = isset($prepare['max_size'])
            ? max(1, (int) $prepare['max_size'])
            : 5;

        if (isset($prepare['dir']) && is_string($prepare['dir'])) {
            $format['dir'] = $prepare['dir'];
        }

        if (!isset($prepare['resize'])) {
            return $format;
        }

        $resize = $prepare['resize'];

        if (is_array($resize) && isset($resize['width'], $resize['height'])) {
            $format['min_size_image'] = $resize['width'].'x'.$resize['height'].'-';
            $format['size_before'] = true;

            return $format;
        }

        if (is_array($resize) && isset($resize[0]['width'], $resize[0]['height'])) {
            $smallest = null;

            foreach ($resize as $size) {
                if (!is_array($size) || !isset($size['width'], $size['height'])) {
                    continue;
                }

                if ($smallest === null || (int) $size['width'] < (int) $smallest['width']) {
                    $smallest = $size;
                }
            }

            if (is_array($smallest)) {
                $format['min_size_image'] = $smallest['width'].'x'.$smallest['height'].'-';
                $format['size_before'] = true;
            }

            return $format;
        }

        $firstResize = is_array($resize) ? reset($resize) : $resize;
        $format['min_size_image'] = $firstResize !== false && $firstResize !== null
            ? (string) $firstResize
            : '';
        $format['size_before'] = false;

        return $format;
    }

    /**
     * Cartella di destinazione: upload root del sito più la cartella del
     * contenuto corrente (`$GLOBALS['NAME']->folder`) e l'eventuale
     * sottocartella dichiarata in `prepare['dir']`.
     */
    protected function fileDirectory(?string $subDirectory = null): string
    {
        $name = $GLOBALS['NAME'] ?? null;
        $path = $GLOBALS['PATH'] ?? null;

        $folder = is_object($name) ? trim((string) ($name->folder ?? ''), '/') : '';
        $directory = rtrim((string) ($path->upload ?? ''), '/');

        if ($folder !== '') {
            $directory .= '/'.$folder;
        }

        // La sottocartella può arrivare in qualunque forma (`photo`, `photo/`,
        // `/photo/`): normalizziamo togliendo le slash ai bordi e uniamo con
        // un solo separatore, così una dir come `/photo/` — quella dichiarata
        // via UploadSchema::dir() nel Model — non produca più `.../teamphoto//`.
        $subDirectory = $subDirectory !== null ? trim($subDirectory, " \t\n\r\0\x0B/") : '';

        if ($subDirectory !== '') {
            $directory .= '/'.$subDirectory;
        }

        return $directory.'/';
    }
}
