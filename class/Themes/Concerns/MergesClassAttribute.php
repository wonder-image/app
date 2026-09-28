<?php

namespace Wonder\Themes\Concerns;

/**
 * Le classi del tema e quelle date al campo in un solo attributo `class`.
 *
 * Il browser ignora un secondo attributo `class` sullo stesso tag: il renderer
 * che scrive a mano la classe del tema (`form-check-input`, `wi-checkbox`, …)
 * toglie `class` dagli attributi del campo e la passa qui. Accetta una
 * stringa, un array o uno scalare, divide sugli spazi e toglie i doppioni; le
 * classi del tema vengono per prime. Le classi escono senza escape.
 */
trait MergesClassAttribute
{
    protected function mergeClassAttribute(string $themeClasses, mixed $classes): string
    {
        $tokens = [];

        foreach ([$themeClasses, ...(is_array($classes) ? array_values($classes) : [$classes])] as $class) {
            if (is_bool($class) || !is_scalar($class)) {
                continue;
            }

            foreach (preg_split('/\s+/', trim((string) $class)) ?: [] as $token) {
                if ($token !== '') {
                    $tokens[] = $token;
                }
            }
        }

        return implode(' ', array_unique($tokens));
    }
}
