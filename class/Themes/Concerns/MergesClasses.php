<?php

namespace Wonder\Themes\Concerns;

trait MergesClasses
{
    /**
     * Le classi del componente prima, poi quelle date con class()/addClass().
     *
     * @param string[] $base
     * @return string[]
     */
    protected function mergeClasses(array $base, array $attributes): array
    {
        $extra = $attributes['class'] ?? [];
        $extra = is_array($extra) ? $extra : explode(' ', (string) $extra);
        $classes = array_map(static fn ($class): string => trim((string) $class), [...$base, ...$extra]);

        return array_values(array_unique(array_filter($classes, static fn (string $class): bool => $class !== '')));
    }
}
