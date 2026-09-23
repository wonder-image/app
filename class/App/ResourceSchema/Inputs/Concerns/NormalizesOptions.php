<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Normalizzazione delle opzioni `[value => label]` prima di consegnarle a un
 * Element.
 *
 * Gestisce anche la forma estesa `['name' => ..., 'filter' => [...],
 * 'child' => [...]]` usata da `checkTree()` e dalle liste filtrabili: le
 * chiavi mancanti vengono riempite con default coerenti così i renderer non
 * devono difendersi da array parziali. `image`, `icon` e `color` passano
 * così come sono: li controlla `OptionVisual` al momento di disegnarli.
 */
trait NormalizesOptions
{
    /**
     * @param array<array-key, mixed> $options
     * @return array<array-key, mixed>
     */
    protected function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $value => $label) {
            if (is_array($label)) {
                $normalized[$value] = [
                    'name' => (string) ($label['name'] ?? $value),
                    'filter' => is_array($label['filter'] ?? null) ? $label['filter'] : [],
                    'child' => is_array($label['child'] ?? null) ? $label['child'] : [],
                ];

                foreach (['image', 'icon', 'color'] as $visual) {
                    if (is_string($label[$visual] ?? null) && $label[$visual] !== '') {
                        $normalized[$value][$visual] = $label[$visual];
                    }
                }
                continue;
            }

            $normalized[$value] = $label;
        }

        return $normalized;
    }

    /** Le opzioni dichiarate sul campo, già normalizzate. */
    protected function normalizedOptions(): array
    {
        return $this->normalizeOptions((array) ($this->schema['options'] ?? []));
    }
}
