<?php

namespace Wonder\App\Import;

use Throwable;
use Wonder\App\Logger;
use Wonder\App\Module\ConfigRepository;

/**
 * I tracciati di caricamento, per chiave. Il core non ne ha: i moduli li
 * aggiungono, sostituiscono o tolgono (`false`) con `import.layouts` e il
 * sito ha l'ultima parola con `Layouts::set()`. Un tracciato che non si
 * costruisce va nel log e si salta.
 */
final class Layouts
{
    /** @var array<string, class-string<Layout>> */
    public const CORE = [];

    /** @var array<string, class-string<Layout>|Layout|false> */
    private static array $overrides = [];

    public static function set(string $key, string|Layout|false $layout): void
    {
        self::$overrides[$key] = $layout;
    }

    public static function reset(): void
    {
        self::$overrides = [];
    }

    /** @return array<string, Layout> */
    public static function all(?array $configs = null): array
    {
        $layouts = self::CORE;

        foreach ($configs ?? ConfigRepository::all() as $config) {
            foreach ((array) ($config['import']['layouts'] ?? []) as $key => $layout) {
                if (is_string($key)) {
                    $layouts[$key] = $layout;
                }
            }
        }

        $items = [];

        foreach (array_merge($layouts, self::$overrides) as $key => $layout) {
            if (!$layout instanceof Layout && !(is_string($layout) && is_subclass_of($layout, Layout::class))) {
                continue;
            }

            try {
                $items[$key] = $layout instanceof Layout ? $layout : new $layout();
            } catch (Throwable $exception) {
                Logger::log($exception, 'backend', 'import', 'ERROR', 'error', [
                    'key' => $key,
                    'class' => is_string($layout) ? $layout : $layout::class,
                ], false);
            }
        }

        return $items;
    }

    public static function find(string $key, ?array $configs = null): ?Layout
    {
        return self::all($configs)[$key] ?? null;
    }

    public static function get(string $key): Layout
    {
        return self::find($key) ?? throw ImportError::make('layout');
    }
}
