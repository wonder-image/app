<?php

namespace Wonder\Themes\Wonder\Concerns;

/**
 * Le utility responsive della griglia del frontend, quelle della lib: per un
 * prefisso (`col`, `gap`) scrive `prefix-N` per il desktop, `prefix-t-N` per
 * il tablet e `prefix-p-N` per il telefono, solo quando i valori cambiano.
 * I valori arrivano mobile-first dall'Element (`default`, `sm`..`2xl`).
 */
trait ResponsiveGridClasses
{
    /**
     * @param array<string, int|null> $values
     * @return string[]
     */
    protected function responsiveClasses(string $prefix, array $values): array
    {
        $phone = $this->lastResponsiveValue($values, ['default'], 1);
        $tablet = $this->lastResponsiveValue($values, ['default', 'sm', 'md'], $phone);
        $desktop = $this->lastResponsiveValue(
            $values,
            ['default', 'sm', 'md', 'lg', 'xl', '2xl'],
            $tablet
        );

        $classes = [$prefix.'-'.$desktop];

        if ($tablet !== $desktop) {
            $classes[] = $prefix.'-t-'.$tablet;
        }

        if ($phone !== $tablet) {
            $classes[] = $prefix.'-p-'.$phone;
        }

        return $classes;
    }

    /**
     * @param array<string, int|null> $values
     * @param string[] $breakpoints
     */
    private function lastResponsiveValue(array $values, array $breakpoints, int $fallback): int
    {
        $value = $fallback;

        foreach ($breakpoints as $breakpoint) {
            if (isset($values[$breakpoint])) {
                $value = (int) $values[$breakpoint];
            }
        }

        return $value;
    }
}
