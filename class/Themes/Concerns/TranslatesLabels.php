<?php

namespace Wonder\Themes\Concerns;

use Wonder\Localization\TranslationProvider;

/**
 * Un'etichetta dal catalogo traduzioni, con un testo di riserva quando la
 * chiave manca o il contesto non ha ancora caricato le lingue (CLI, test).
 */
trait TranslatesLabels
{
    protected function translateLabel(string $key, string $fallback): string
    {
        try {
            $value = TranslationProvider::get($key);
        } catch (\Throwable) {
            return $fallback;
        }

        if (!is_string($value) || trim($value) === '' || $value === $key) {
            return $fallback;
        }

        return $value;
    }
}
