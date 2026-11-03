<?php

namespace Wonder\Localization\Concerns;

use Wonder\Localization\Content\{TranslationStore, Translations};
use Wonder\Localization\LanguageContext;

/**
 * Lettura esplicita delle traduzioni per un Model `Translatable`. Il Model
 * resta com'è: `translated()` sovrappone ai campi traducibili i valori di
 * `translations` nella lingua chiesta, con una query per batch. Un valore
 * mancante o vuoto lascia il testo della colonna (lingua base).
 */
trait HasTranslations
{
    /**
     * @param array<string, mixed>|list<array<string, mixed>> $rows una riga (con `id`) o una lista
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public static function translated(array $rows, ?string $lang = null, ?TranslationStore $store = null): array
    {
        $lang ??= LanguageContext::getLang();

        if ($rows === [] || $lang === '' || $lang === LanguageContext::getDefaultLang()) {
            return $rows;
        }

        $single = array_key_exists('id', $rows) && !is_array($rows['id']);
        $list = $single ? [$rows] : $rows;
        $ids = [];

        foreach ($list as $row) {
            if (is_array($row) && isset($row['id'])) {
                $ids[] = (int) $row['id'];
            }
        }

        $translations = ($store ?? Translations::store())->translations(static::$table, $ids, $lang);
        $fields = array_keys(static::translatableFields());

        foreach ($list as $index => $row) {
            $byField = is_array($row) ? ($translations[(int) ($row['id'] ?? 0)] ?? []) : [];

            foreach ($fields as $field) {
                $value = (string) ($byField[$field]['value'] ?? '');

                if ($value !== '' && array_key_exists($field, $row)) {
                    $list[$index][$field] = $value;
                }
            }
        }

        return $single ? $list[0] : $list;
    }
}
