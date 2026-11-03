<?php

namespace Wonder\App\Resources\Config;

use Throwable;
use Wonder\App\Models\Localization\{Translation, TranslationTerm};
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;
use Wonder\Http\Route;
use Wonder\Localization\Catalog\ModelTranslationSource;
use Wonder\Localization\Content\Translations;
use Wonder\Localization\LanguageContext;

/**
 * Vocabolario delle traduzioni automatiche. Le traduzioni di un termine
 * (`glossary`) stanno in `translations` con `field = term`, sempre
 * `reviewed`: le scrive il cliente. Un termine salvato mostra in quanti
 * contenuti tradotti compare; "Ritraduci" rimette in coda solo gli `automatic`.
 */
final class TranslationTermResource extends Resource
{
    public static string $model = TranslationTerm::class;
    public static array|string $condition = [];
    public static string $orderColumn = 'term';
    public static string $orderDirection = 'ASC';

    public static function path(): string { return 'app/config/translation-terms'; }

    public static function textSchema(): array
    {
        return [
            'label' => 'termine',
            'plural_label' => 'termini',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'i',
            'full' => 'pieno',
            'empty' => 'vuoto',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        $labels = [
            'term' => 'Termine',
            'mode' => 'Uso',
            'status' => 'Stato',
            'occurrences' => 'Ripetizioni',
            'actions' => 'Azioni',
        ];

        foreach (static::targetLanguages() as $code => $name) {
            $labels['translation_'.$code] = $name;
        }

        return $labels;
    }

    public static function formSchema(): array
    {
        $fields = [
            FormField::key('term')->text()->required(),
            FormField::key('mode')->select(static::modes())->value(TranslationTerm::GLOSSARY)->required(),
            FormField::key('status')->select(static::statuses())->value(TranslationTerm::CONFIRMED)->required(),
        ];

        foreach (array_keys(static::targetLanguages()) as $code) {
            $fields[] = FormField::key('translation_'.$code)->text();
        }

        return $fields;
    }

    public static function formLayoutSchema(): ?Form
    {
        $translations = array_map(
            static fn (string $code) => static::getInput('translation_'.$code)->columnSpan(6),
            array_keys(static::targetLanguages())
        );

        return (new Form)->components([
            (new Card)->components([
                static::getInput('term')->columnSpan(12),
                ...$translations,
            ])->columns(12)->columnSpan(9),
            (new Card)->components([
                static::getInput('mode')->columnSpan(12),
                static::getInput('status')->columnSpan(12),
            ])->columns(12)->columnSpan(3),
        ])->columns(12);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('term')->text()->link('view'),
            TableColumn::key('mode')->text()->size('little')->formatter(static fn ($row) => e(static::modes()[$row['mode'] ?? ''] ?? '')),
            TableColumn::key('status')->text()->size('little')->formatter(static fn ($row) => e(static::statuses()[$row['status'] ?? ''] ?? '')),
            TableColumn::key('occurrences')->text()->size('little'),
            TableColumn::key('actions')->button()->actions(['view', 'edit']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Vocabolario')
            ->results()
            ->buttonAdd('Aggiungi termine')
            ->filters()
            ->searchFields(['term'])
            ->filterRadio('Stato', 'status', ['' => 'Tutti'] + static::statuses())
            ->filterRadio('Uso', 'mode', ['' => 'Tutti'] + static::modes());
    }

    public static function pageSchema(): PageSchema
    {
        return static::page()
            ->enable('view')
            ->disable('delete')
            ->title('view', 'Termine')
            ->actions('view', static fn (array $item): array => static::requeueActions($item))
            ->actions('edit', static fn (array $item): array => static::requeueActions($item));
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'create', 'store', 'view', 'edit', 'update'], ['admin']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('set-up')
            ->title('Vocabolario')
            ->order(41)
            ->authority(['admin']);
    }

    public static function mutateFormValues(array $values, string $mode, string $context = 'backend'): array
    {
        $id = (int) ($values['id'] ?? 0);

        if ($id < 1) {
            return $values;
        }

        foreach (array_keys(static::targetLanguages()) as $code) {
            $key = 'translation_'.$code;

            if (!array_key_exists($key, $values)) {
                $stored = Translations::store()->translations(TranslationTerm::$table, [$id], $code);
                $values[$key] = (string) ($stored[$id]['term']['value'] ?? '');
            }
        }

        return $values;
    }

    public static function afterStore(object $result, array $values = []): void
    {
        static::saveTranslations((int) ($result->insert_id ?? 0));
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
        static::saveTranslations((int) $id);
    }

    /**
     * Dove compare il termine e cosa farà "Ritraduci": quante traduzioni
     * automatiche si rifaranno e quali corrette a mano restano da rivedere.
     */
    public static function formNotices(array $values, string $mode): array
    {
        $term = trim((string) ($values['term'] ?? ''));

        if ($mode !== 'edit' || $term === '') {
            return [];
        }

        $usages = static::usages($term);

        if ($usages === []) {
            return [['level' => 'info', 'message' => 'Il termine non compare in nessun contenuto tradotto.']];
        }

        $automatic = array_filter($usages, static fn (array $row): bool => $row['status'] === Translation::AUTOMATIC);
        $reviewed = array_filter($usages, static fn (array $row): bool => $row['status'] !== Translation::AUTOMATIC);
        $notices = [[
            'level' => 'info',
            'message' => 'Compare in '.count($usages).' contenuti tradotti: '.count($automatic).' automatici, '
                .count($reviewed).' corretti a mano. "Ritraduci automatiche" rifà solo i primi.',
        ]];

        if ($reviewed !== []) {
            $lines = array_map(
                static fn (array $row): string => "{$row['table']} #{$row['row_id']}, {$row['field']} ({$row['language_code']})",
                array_slice(array_values($reviewed), 0, 20)
            );
            $more = count($reviewed) > 20 ? "\n… e altri ".(count($reviewed) - 20).'.' : '';
            $notices[] = [
                'level' => 'warning',
                'message' => "Da rivedere a mano in Contenuti tradotti:\n".implode("\n", $lines).$more,
            ];
        }

        return $notices;
    }

    /**
     * Traduzioni dei Model traducibili il cui testo base contiene il termine.
     *
     * @return list<array{id: int, row_id: int, language_code: string, status: string, table: string, field: string}>
     */
    public static function usages(string $term): array
    {
        $store = Translations::store();
        $usages = [];

        foreach (ModelTranslationSource::translatableModels() as $model) {
            $table = (string) $model::$table;
            $softDelete = ModelTranslationSource::softDelete($model);

            foreach (array_keys($model::translatableFields()) as $field) {
                foreach ($store->usages($table, (string) $field, $term, $softDelete) as $row) {
                    $usages[] = $row + ['table' => $table, 'field' => (string) $field];
                }
            }
        }

        return $usages;
    }

    public static function registerBackendRoutes(string $rootApp, string $slug): void
    {
        Route::post('/{id}/requeue/', $rootApp.'/http/backend/config/translation-terms/requeue.php', [
            'resource' => $slug,
            'resource_action' => 'requeue',
        ])->name('requeue')
            ->permit(['admin'])
            ->where('id', '[0-9]+');
    }

    /** @return array<string, string> codice => nome, senza la lingua base */
    public static function targetLanguages(): array
    {
        $languages = TranslationsResource::languages();
        unset($languages[LanguageContext::getDefaultLang()]);

        return $languages;
    }

    /** @return array<string, string> */
    public static function modes(): array
    {
        return [TranslationTerm::GLOSSARY => 'Traduci sempre così', TranslationTerm::KEEP => 'Non tradurre'];
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            TranslationTerm::CONFIRMED => 'Confermato',
            TranslationTerm::CANDIDATE => 'Proposto',
            TranslationTerm::REJECTED => 'Scartato',
        ];
    }

    /** @return list<Button> */
    private static function requeueActions(array $item): array
    {
        $id = (int) ($item['id'] ?? 0);

        if ($id < 1) {
            return [];
        }

        return [
            Button::post(__r('backend.resource.'.static::slug().'.requeue', ['id' => $id]), 'Ritraduci automatiche')
                ->variant('light')
                ->confirm(
                    'Le traduzioni automatiche dei contenuti che contengono il termine si rifaranno alla prossima esecuzione del task. Quelle corrette a mano non cambiano.',
                    title: 'Ritradurre i contenuti?',
                    ok: 'Ritraduci',
                    variant: 'primary'
                ),
        ];
    }

    /** Le traduzioni del termine arrivano dal form e sono scritte dal cliente: `reviewed`. */
    private static function saveTranslations(int $id): void
    {
        if ($id < 1) {
            return;
        }

        $store = Translations::store();

        foreach (array_keys(static::targetLanguages()) as $code) {
            $key = 'translation_'.$code;

            if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
                continue;
            }

            $value = trim($_POST[$key]);
            $existing = $store->translations(TranslationTerm::$table, [$id], $code)[$id]['term'] ?? null;

            if ($value === '' && $existing === null) {
                continue;
            }

            try {
                $store->save(TranslationTerm::$table, $id, 'term', $code, [
                    'value' => $value,
                    'status' => Translation::REVIEWED,
                    'engine' => 'manual',
                ]);
            } catch (Throwable $error) {
                \Wonder\App\Logger::log($error, 'translations', 'term.save');
            }
        }
    }
}
