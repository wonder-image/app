<?php

namespace Wonder\App\ResourceSchema\Inputs;

/**
 * Upload drag&drop (Filepond). Stessi vincoli di {@see InputFile}, più la
 * strategia di upload lato client: `uploader('classic')` o il nome di un
 * uploader registrato.
 */
class InputFileDragDrop extends InputFile
{
    protected string $helper = 'inputFileDragDrop';

    public function uploader(string $uploader = 'classic'): static
    {
        $this->schema['uploader'] = trim($uploader);

        return $this;
    }
}
