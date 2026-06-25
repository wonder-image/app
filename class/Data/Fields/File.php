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

    public function name(string $name): static
    {
        return $this->schema('name', $name);
    }
    
    public function file(bool $enabled = true): static
    {
        return $this->schema('file', $enabled);
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

    public function extensions(array $extensions): static
    {
        return $this->schema('extensions', $extensions);
    }

    public function maxSize(int $maxSize): static
    {
        return $this->schema('max_size', $maxSize);
    }

    public function maxFile(int $maxFile): static
    {
        return $this->schema('max_file', $maxFile);
    }

    public function dir(string $dir): static
    {
        return $this->schema('dir', $dir);
    }

}
