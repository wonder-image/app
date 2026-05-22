<?php

namespace Wonder\Elements\Form\Components;

class TextareaEditor extends Textarea
{
    public function version(string $version): self
    {
        return $this->schema('version', trim($version));
    }

    public function folder(?string $folder): self
    {
        return $this->schema('folder', $folder !== null ? trim($folder) : null);
    }
}
