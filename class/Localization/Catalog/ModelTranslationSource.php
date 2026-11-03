<?php

namespace Wonder\Localization\Catalog;

use Throwable;
use Wonder\App\Model;
use Wonder\App\ModelRegistry;
use Wonder\App\Models\Localization\Translation;
use Wonder\Localization\Content\TranslationStore;
use Wonder\Localization\Content\Translations;
use Wonder\Localization\Contracts\Translatable;
use Wonder\Localization\LanguageContext;

/**
 * I campi traducibili di un Model: l'id è `<riga>.<campo>`, il testo nella
 * lingua base è la colonna, le altre lingue stanno in `translations`.
 * L'import scrive solo le lingue diverse dalla base e segna le righe
 * `reviewed`; la colonna si cambia dal contenuto, non da qui.
 */
class ModelTranslationSource implements TranslationSource
{
    public const BASE_LANGUAGE = 'La lingua base si modifica dal contenuto.';

    /** @param class-string<Model&Translatable> $model */
    public function __construct(private readonly string $model, private readonly ?TranslationStore $store = null)
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

    /** Il Model rileva `deleted` dalle colonne; nel dubbio si filtra. */
    public static function softDelete(string $model): bool
    {
        try {
            return array_key_exists('deleted', $model::getColumns());
        } catch (Throwable) {
            return true;
        }
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
        $table = (string) $this->model::$table;
        $fields = array_keys($this->model::translatableFields());
        $rows = $this->store()->sourceRows($table, $fields, self::softDelete($this->model));
        $ids = array_keys($rows);
        $sourceTexts = $this->texts($table, $rows, $ids, $source);
        $targetTexts = $this->texts($table, $rows, $ids, $target);
        $entries = [];

        foreach ($rows as $rowId => $row) {
            foreach ($fields as $field) {
                $text = $sourceTexts[$rowId][$field] ?? '';

                if (trim($text) === '') {
                    continue;
                }

                $id = "{$rowId}.{$field}";
                $entries[$id] = new Entry($id, "{$table} #{$rowId} {$field}", $text, $targetTexts[$rowId][$field] ?? '');
            }
        }

        return $entries;
    }

    public function apply(string $source, string $target, array $changes): array
    {
        if ($target === LanguageContext::getDefaultLang()) {
            return array_fill_keys(array_keys($changes), self::BASE_LANGUAGE);
        }

        $table = (string) $this->model::$table;
        $fields = array_keys($this->model::translatableFields());
        $rows = $this->store()->sourceRows($table, $fields, self::softDelete($this->model));
        $errors = [];

        foreach ($changes as $id => $value) {
            [$rowId, $field] = array_pad(explode('.', (string) $id, 2), 2, '');

            if (!ctype_digit($rowId) || !in_array($field, $fields, true) || !isset($rows[(int) $rowId])) {
                $errors[$id] = 'Chiave inesistente.';
                continue;
            }

            $this->store()->save($table, (int) $rowId, $field, $target, [
                'value' => (string) $value,
                'status' => Translation::REVIEWED,
                'source_hash' => Translations::hash($rows[(int) $rowId][$field]),
                'engine' => 'import',
            ]);
        }

        return $errors;
    }

    /** @return array<int, array<string, string>> */
    private function texts(string $table, array $rows, array $ids, string $lang): array
    {
        if ($lang === LanguageContext::getDefaultLang()) {
            return $rows;
        }

        $texts = [];

        foreach ($this->store()->translations($table, $ids, $lang) as $rowId => $fields) {
            foreach ($fields as $field => $row) {
                $texts[$rowId][$field] = (string) ($row['value'] ?? '');
            }
        }

        return $texts;
    }

    private function store(): TranslationStore
    {
        return $this->store ?? Translations::store();
    }
}
