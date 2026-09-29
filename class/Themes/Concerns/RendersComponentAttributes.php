<?php

namespace Wonder\Themes\Concerns;

trait RendersComponentAttributes
{
    use HasAttributes, MergesClassAttribute;

    /**
     * @param string[] $requiredClasses
     * @param string[] $reservedClasses
     */
    protected function renderComponentAttributes(
        object $class,
        array $requiredClasses = [],
        array $reservedClasses = []
    ): string {
        $attributes = $class->getSchema('attributes');
        $attributes = is_array($attributes) ? $attributes : [];
        $classes = $requiredClasses;

        foreach ($this->classTokens($attributes['class'] ?? null) as $customClass) {
            if (!in_array($customClass, $reservedClasses, true)) {
                $classes[] = $customClass;
            }
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

        return $this->renderAttributes($attributes);
    }
}
