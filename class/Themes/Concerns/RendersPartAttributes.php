<?php

namespace Wonder\Themes\Concerns;

/**
 * Gli attributi di una parte interna, scritti una volta sola sul suo tag.
 *
 * Le classi del tema vengono per prime, poi quelle date con i metodi della
 * parte (`Elements\Concerns\HasPartAttributes`); gli altri attributi seguono,
 * senza i nomi che il renderer scrive già a mano sullo stesso tag. Restituisce
 * la stringa senza spazio iniziale: `<div {$this->partAttributes(...)}>`.
 */
trait RendersPartAttributes
{
    use HasAttributes, MergesClassAttribute;

    /** @param string[] $written attributi che il renderer scrive da sé sul tag */
    protected function partAttributes(object $element, string $part, string $themeClasses, array $written = []): string
    {
        $schema = $element->getSchema('parts');
        $schema = is_array($schema[$part] ?? null) ? $schema[$part] : [];
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];

        foreach (['class', ...$written] as $key) {
            unset($attributes[$key]);
        }

        $class = $this->mergeClassAttribute($themeClasses, $schema['class'] ?? []);

        return $this->renderAttributes($class !== '' ? ['class' => $class] + $attributes : $attributes);
    }

    /** Solo le classi della parte, unite a quelle del tema. */
    protected function partClass(object $element, string $part, string $themeClasses): string
    {
        $schema = $element->getSchema('parts');

        return $this->mergeClassAttribute($themeClasses, $schema[$part]['class'] ?? []);
    }
}
