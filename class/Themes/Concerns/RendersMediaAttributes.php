<?php

namespace Wonder\Themes\Concerns;

trait RendersMediaAttributes
{
    use HasAttributes;

    protected function renderMediaAttributes(
        object $class,
        array $themeClasses = [],
        array $requiredAttributes = []
    ): string {
        $attributes = $class->getSchema('attributes');
        $attributes = is_array($attributes) ? $attributes : [];

        $classes = $this->normalizeClasses($attributes['class'] ?? null);
        foreach ($themeClasses as $themeClass) {
            $classes = array_merge($classes, $this->normalizeClasses($themeClass));
        }

        if ($classes === []) {
            unset($attributes['class']);
        } else {
            $attributes['class'] = implode(' ', array_values(array_unique($classes)));
        }

        $id = $class->getSchema('id');
        if (is_string($id) && $id !== '') {
            $attributes['id'] = $id;
        }

        foreach ($requiredAttributes as $key => $value) {
            $attributes[$key] = $value;
        }

        return $this->renderAttributes($attributes);
    }

    /** @return string[] */
    private function normalizeClasses(mixed $classes): array
    {
        if (is_string($classes)) {
            $classes = [$classes];
        }

        if (!is_array($classes)) {
            return [];
        }

        $normalized = [];

        foreach ($classes as $class) {
            if (!is_scalar($class)) {
                continue;
            }

            foreach (preg_split('/\s+/', trim((string) $class)) ?: [] as $token) {
                if ($token !== '') {
                    $normalized[] = $token;
                }
            }
        }

        return $normalized;
    }
}
