<?php

namespace Wonder\Data\Fields;

class File extends Field
{
    public string $type = 'file';

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->file()
            ->sanitize(false)
            ->maxFile(1)
            ->maxSize(1);
    }

    public function mimeType(string $mimeType): self
    {
        return $this->schema('mime_type', $mimeType);
    }

    public function minFile(int $minFile): self
    {
        return $this->schema('min_file', $minFile);
    }

    public function path(string $path): self
    {
        return $this->dir($path);
    }
}
