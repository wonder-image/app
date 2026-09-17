<?php

namespace Wonder\App\Module;

use RuntimeException;

/**
 * Ordina i moduli per dipendenze: le dipendenze prima dei moduli che le
 * richiedono. Tra moduli indipendenti conserva l'ordine di ingresso.
 */
final class ModuleDependencySorter
{
    /**
     * @param array<string, string[]> $dependencies slug => slug delle dipendenze
     * @return string[]
     */
    public static function sort(array $dependencies): array
    {
        $ordered = [];
        $state = [];

        $visit = function (string $slug, array $path) use (&$visit, &$ordered, &$state, $dependencies): void {
            $current = $state[$slug] ?? 0;

            if ($current === 2) {
                return;
            }

            if ($current === 1) {
                throw new RuntimeException(
                    'Dipendenze circolari tra i moduli: '.implode(' → ', [...$path, $slug])
                );
            }

            $state[$slug] = 1;

            foreach ((array) ($dependencies[$slug] ?? []) as $dependency) {
                $dependency = (string) $dependency;

                if (array_key_exists($dependency, $dependencies)) {
                    $visit($dependency, [...$path, $slug]);
                }
            }

            $state[$slug] = 2;
            $ordered[] = $slug;
        };

        foreach (array_keys($dependencies) as $slug) {
            $visit((string) $slug, []);
        }

        return $ordered;
    }

    /**
     * @param array<array-key, Manifest> $manifests
     * @return Manifest[]
     */
    public static function sortManifests(array $manifests): array
    {
        $dependencies = [];
        $bySlug = [];

        foreach ($manifests as $manifest) {
            $bySlug[$manifest->slug()] = $manifest;
            $dependencies[$manifest->slug()] = $manifest->dependencySlugs();
        }

        return array_map(
            static fn (string $slug): Manifest => $bySlug[$slug],
            self::sort($dependencies)
        );
    }
}
