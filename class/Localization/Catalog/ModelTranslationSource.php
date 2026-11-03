<?php

namespace Wonder\Localization\Catalog;

use Throwable;
use Wonder\App\Model;
use Wonder\App\ModelRegistry;
use Wonder\Localization\Contracts\Translatable;

/**
 * I campi traducibili di un Model: l'id è `<riga>.<campo>`, il testo sorgente
 * è la colonna, la traduzione sta in `translations`.
 */
class ModelTranslationSource implements TranslationSource
{
    /** @param class-string<Model&Translatable> $model */
    public function __construct(private readonly string $model)
    {
    }

    /** @return list<class-string<Model&Translatable>> */
    public static function translatableModels(): array
    {
        try {
            $models = ModelRegistry::classes();
        } catch (Throwable) {
            return [];
        }

        return array_values(array_filter(
            $models,
            static fn (string $model): bool => is_subclass_of($model, Translatable::class)
        ));
    }

    public function key(): string
    {
        return 'model:'.$this->model::$table;
    }

    public function label(): string
    {
        return $this->model::$table;
    }

    public function entries(string $source, string $target): array
    {
        return [];
    }

    public function apply(string $source, string $target, array $changes): array
    {
        return array_map(static fn (): string => 'Sorgente non ancora disponibile.', $changes);
    }
}
