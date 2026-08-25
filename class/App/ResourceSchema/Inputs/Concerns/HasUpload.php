<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Vincoli di upload comuni a `InputFile` e `InputFileDragDrop`.
 *
 * `accept()` scrive la *categoria* di file in `schema['file']` (il renderer la
 * traduce in `accept="..."` e in label informativa); gli altri limiti finiscono
 * in `prepare`, da dove li rilegge sia `InputFile::fileFormat()` al render sia
 * l'upload server-side.
 */
trait HasUpload
{
    use NormalizesExtensions;
    use HasMultiple;

    /**
     * Categoria di file accettata (es. `image`, `pdf`, `video`, `font`, `media`).
     */
    public function accept(string $accept): static
    {
        $this->schema['file'] = trim($accept);

        return $this;
    }

    /**
     * Numero massimo di file caricabili. Senza valore esplicito il render usa
     * 10 se `multiple()` è attivo, altrimenti 1.
     */
    public function maxFile(int $count): static
    {
        return $this->prepare('max_file', $count);
    }

    /** Peso massimo per file, in MB (default 5). */
    public function maxSize(int $size): static
    {
        return $this->prepare('max_size', $size);
    }

    public function extensions(string|array $extensions): static
    {
        return $this->extensionsSet($extensions);
    }
}
