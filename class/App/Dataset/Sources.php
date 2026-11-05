<?php

namespace Wonder\App\Dataset;

use Throwable;
use Wonder\App\Import\Layouts;
use Wonder\App\Logger;
use Wonder\App\Module\ConfigRepository;
use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;

/**
 * Le fonti dei tracciati, per id. Ci sono da sole le Resource con l'export
 * (id = slug, senza `downloadUsing()`) e i tracciati di caricamento
 * (`import.layouts`); i moduli aggiungono, sostituiscono o tolgono (`false`)
 * con `dataset.sources` e il sito ha l'ultima parola con `Sources::set()`.
 * Una fonte che non si costruisce va nel log e si salta.
 */
final class Sources
{
    /** @var array<string, class-string<Source>|Source|false> */
    private static array $overrides = [];

    public static function set(string $id, string|Source|false $source): void
    {
        self::$overrides[$id] = $source;
    }

    public static function reset(): void
    {
        self::$overrides = [];
    }

    /** @return array<string, Source> */
    public static function all(?array $configs = null): array
    {
        $configs ??= ConfigRepository::all();
        $sources = [];

        foreach (self::resources() as $slug => $resource) {
            $sources[$slug] = new ResourceSource($resource);
        }

        foreach (Layouts::all($configs) as $key => $layout) {
            $sources[$key] = $layout;
        }

        $declared = [];

        foreach ($configs as $config) {
            foreach ((array) ($config['dataset']['sources'] ?? []) as $id => $source) {
                if (is_string($id)) {
                    $declared[$id] = $source;
                }
            }
        }

        foreach (array_merge($declared, self::$overrides) as $id => $source) {
            if ($source === false) {
                unset($sources[$id]);
                continue;
            }

            try {
                $item = self::build($source);
            } catch (Throwable $exception) {
                Logger::log($exception, 'backend', 'dataset', 'ERROR', 'error', [
                    'id' => $id,
                    'class' => is_string($source) ? $source : get_debug_type($source),
                ], false);
                continue;
            }

            if ($item !== null) {
                $sources[$id] = $item;
            }
        }

        return $sources;
    }

    public static function find(string $id, ?array $configs = null): ?Source
    {
        return self::all($configs)[$id] ?? null;
    }

    /** Le fonti da cui `$user` può fare `$action` (`export` o `import`). */
    public static function allowed(?object $user, string $action, ?array $configs = null): array
    {
        return array_filter(self::all($configs), static fn (Source $source): bool => $source->allows($user, $action));
    }

    private static function build(mixed $source): ?Source
    {
        if ($source instanceof Source) {
            return $source;
        }

        if (!is_string($source)) {
            return null;
        }

        // Una Resource in config diventa la sua fonte di export.
        if (is_subclass_of($source, Resource::class)) {
            return new ResourceSource($source);
        }

        return is_subclass_of($source, Source::class) ? new $source() : null;
    }

    /** @return array<string, class-string<Resource>> */
    private static function resources(): array
    {
        $resources = [];

        try {
            $all = ResourceRegistry::all();
        } catch (Throwable $exception) {
            Logger::log($exception, 'backend', 'dataset', 'ERROR', 'error', [], false);

            return [];
        }

        foreach ($all as $slug => $resource) {
            try {
                $download = (array) ($resource::tableLayoutSchema()->all()['download'] ?? []);
            } catch (Throwable) {
                continue;
            }

            if (!empty($download['enabled']) && !is_callable($download['handler'] ?? null)) {
                $resources[(string) $slug] = $resource;
            }
        }

        return $resources;
    }
}
