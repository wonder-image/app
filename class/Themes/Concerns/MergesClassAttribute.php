<?php

namespace Wonder\Themes\Concerns;

/**
 * Le classi del tema e quelle date al campo in un solo attributo `class`.
 *
 * Il browser ignora un secondo attributo `class` sullo stesso tag: il renderer
 * che scrive a mano la classe del tema (`form-check-input`, `wi-checkbox`, …)
 * unisce qui quelle del campo invece di lasciarle fra gli altri attributi. I
 * renderer dei campi passano da `AbstractFieldRenderer::fieldClass()`.
 * Accetta una stringa, un array o uno scalare, divide sugli spazi e toglie i
 * doppioni; le classi del tema vengono per prime. Le classi escono senza escape.
 */
trait MergesClassAttribute
{
    protected function mergeClassAttribute(string $themeClasses, mixed $classes): string
    {
        return implode(' ', array_unique([...$this->classTokens($themeClasses), ...$this->classTokens($classes)]));
    }

    /**
     * Le classi una per una, da una stringa, uno scalare o un array di questi.
     * Booleani e valori non scalari non sono classi e restano fuori.
     *
     * @return string[]
     */
    protected function classTokens(mixed $classes): array
    {
        $tokens = [];

        foreach (is_array($classes) ? $classes : [$classes] as $class) {
            if (is_bool($class) || !is_scalar($class)) {
                continue;
            }

            foreach (preg_split('/\s+/', trim((string) $class)) ?: [] as $token) {
                if ($token !== '') {
                    $tokens[] = $token;
                }
            }
        }

        return $tokens;
    }
}
