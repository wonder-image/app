<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Plumbing condiviso fra `HasUpload` (API pubblica dei tipi file) e lo shim
 * `@deprecated` rimasto su `FormField`.
 */
trait NormalizesExtensions
{
    /**
     * Estensioni accettate per l'upload (post-server validation).
     * Accetta sia un array (`['png', 'jpg']`) sia una stringa separata da
     * virgole/spazi/pipe (`'png,jpg'`, `'png jpg'`, `'.png|.jpg'`). Le
     * estensioni vengono normalizzate a lowercase senza punto iniziale.
     */
    protected function extensionsSet(string|array $extensions): static
    {
        if (is_string($extensions)) {
            $extensions = preg_split('/[\s,|]+/', $extensions, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $normalized = [];

        foreach ($extensions as $ext) {
            $value = ltrim(strtolower(trim((string) $ext)), '.');

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $this->prepare('extensions', array_values(array_unique($normalized)));
    }
}
