<?php

namespace Wonder\Localization\Catalog;

use Throwable;
use Wonder\App\Logger;
use Wonder\App\Module\ConfigRepository;

/**
 * Le sorgenti della pagina Traduzioni, indicizzate per chiave. Il core
 * registra i JSON del sito e i documenti legali, e una sorgente per ogni
 * Model traducibile; i moduli aggiungono, sostituiscono o tolgono (`false`)
 * voci con `localization.sources` e il sito ha l'ultima parola con
 * `Catalog::set()`. Una sorgente che non si costruisce va nel log e si salta.
 */
final class Catalog
{
    public const CORE = [
        'json' => JsonTranslationSource::class,
        'legal' => LegalTranslationSource::class,
    ];

    /** @var array<string, class-string<TranslationSource>|TranslationSource|false> */
    private static array $overrides = [];

    public static function set(string $key, string|TranslationSource|false $source): void
    {
        self::$overrides[$key] = $source;
    }

    public static function reset(): void
    {
        self::$overrides = [];
    }

    /** @return array<string, class-string<TranslationSource>|TranslationSource> */
    public static function sources(?array $configs = null): array
    {
        $sources = self::CORE;

        foreach (ModelTranslationSource::translatableModels() as $model) {
            $sources['model:'.$model::$table] = new ModelTranslationSource($model);
        }

        foreach ($configs ?? ConfigRepository::all() as $config) {
            foreach ((array) ($config['localization']['sources'] ?? []) as $key => $source) {
                if (is_string($key)) {
                    $sources[$key] = $source;
                }
            }
        }

        $sources = array_merge($sources, self::$overrides);

        return array_filter(
            $sources,
            static fn (mixed $source): bool => $source instanceof TranslationSource
                || (is_string($source) && is_subclass_of($source, TranslationSource::class))
        );
    }

    /** @return array<string, TranslationSource> */
    public static function all(?array $configs = null): array
    {
        $items = [];

        foreach (self::sources($configs) as $key => $source) {
            try {
                $items[$key] = $source instanceof TranslationSource ? $source : new $source();
            } catch (Throwable $exception) {
                Logger::log($exception, 'backend', 'translations', 'ERROR', 'error', [
                    'key' => $key,
                    'class' => is_string($source) ? $source : $source::class,
                ], false);
            }
        }

        return $items;
    }

    public static function find(string $key, ?array $configs = null): ?TranslationSource
    {
        return self::all($configs)[$key] ?? null;
    }
}
