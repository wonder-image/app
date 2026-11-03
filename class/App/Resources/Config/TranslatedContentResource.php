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
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;
use Wonder\Localization\Catalog\ModelTranslationSource;
use Wonder\Localization\Content\Translations;

/**
 * Traduzioni dei contenuti salvate in `translations`. Si modifica solo il
 * testo: chi lo salva lo rende `reviewed` e il task non lo tocca più finché
 * non cambia il testo base (allora passa a `review`).
 */
final class TranslatedContentResource extends Resource
{
    public static string $model = Translation::class;
    public static array|string $condition = [];
    public static string $orderColumn = 'id';
    public static string $orderDirection = 'DESC';

    public static function path(): string { return 'app/config/translated-contents'; }

    public static function textSchema(): array
    {
        return [
            'label' => 'contenuto tradotto',
            'plural_label' => 'contenuti tradotti',
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
        return [
            'table_name' => 'Tabella',
            'row_id' => 'Riga',
            'field' => 'Campo',
            'language_code' => 'Lingua',
            'value' => 'Traduzione',
            'status' => 'Stato',
            'engine' => 'Motore',
            'translated_at' => 'Tradotto il',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('value')->textarea(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('value')->columnSpan(12),
            ])->columns(12)->columnSpan(12),
        ])->columns(12);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('value')->text()->link('edit')->formatter(
                static fn ($row) => e(mb_strimwidth(trim(strip_tags((string) ($row['value'] ?? ''))), 0, 80, '…'))
            ),
            TableColumn::key('table_name')->text()->size('little'),
            TableColumn::key('row_id')->text()->size('little'),
            TableColumn::key('field')->text()->size('little'),
            TableColumn::key('language_code')->text()->size('little'),
            TableColumn::key('status')->text()->size('little')->formatter(static fn ($row) => e(static::statuses()[$row['status'] ?? ''] ?? '')),
            TableColumn::key('actions')->button()->actions(['edit']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        $tables = [];

        foreach (static::sourceModels() as $table => $model) {
            $tables[$table] = $table;
        }

        return TableLayoutSchema::for(static::class)
            ->title('Contenuti tradotti')
            ->results()
            ->filters()
            ->searchFields(['value'])
            ->filterRadio('Stato', 'status', ['' => 'Tutti'] + static::statuses())
            ->filterRadio('Lingua', 'language_code', ['' => 'Tutte'] + TranslationTermResource::targetLanguages())
            ->filterRadio('Tabella', 'table_name', ['' => 'Tutte'] + $tables);
    }

    public static function pageSchema(): PageSchema
    {
        return static::page()
            ->disable('create')
            ->disable('delete')
            ->title('edit', 'Modifica traduzione');
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'edit', 'update'], ['admin']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('set-up')
            ->inGroup('translations')
            ->title('Contenuti tradotti')
            ->order(30)
            ->authority(['admin']);
    }

    /** Una correzione a mano è `reviewed` e vale per il testo base attuale. */
    public static function mutateRequestValues(array $values, string $action, string $context = 'backend', ?array $oldValues = null): array
    {
        if ($action !== 'update') {
            throw new \InvalidArgumentException('Le traduzioni si creano dal task, non a mano.');
        }

        $source = static::sourceText($oldValues ?? []);

        return [
            'value' => (string) ($values['value'] ?? ''),
            'status' => Translation::REVIEWED,
            'engine' => 'manual',
            'translated_at' => date('Y-m-d H:i:s'),
            'source_hash' => $source === null ? ($oldValues['source_hash'] ?? null) : Translations::hash($source),
        ];
    }

    public static function formNotices(array $values, string $mode): array
    {
        if ($mode !== 'edit') {
            return [];
        }

        $notices = [];

        if (($values['status'] ?? '') === Translation::REVIEW) {
            $notices[] = [
                'level' => 'warning',
                'message' => 'Il testo originale è cambiato dopo la correzione: verifica la traduzione e salvala.',
            ];
        }

        $source = static::sourceText($values);
        $where = ($values['table_name'] ?? '').' #'.($values['row_id'] ?? '').', '.($values['field'] ?? '')
            .' ('.($values['language_code'] ?? '').')';
        $notices[] = [
            'level' => 'info',
            'message' => $source === null
                ? "Testo originale non trovato: {$where}."
                : "Testo originale, {$where}:\n".$source,
        ];

        return $notices;
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            Translation::AUTOMATIC => 'Automatica',
            Translation::REVIEWED => 'Corretta a mano',
            Translation::REVIEW => 'Da rivedere',
        ];
    }

    /** @return array<string, class-string> tabella => Model, vocabolario compreso */
    public static function sourceModels(): array
    {
        $models = [];

        foreach (ModelTranslationSource::translatableModels() as $model) {
            $models[(string) $model::$table] = $model;
        }

        $models[TranslationTerm::$table] = TranslationTerm::class;

        return $models;
    }

    private static function sourceText(array $translation): ?string
    {
        $model = static::sourceModels()[(string) ($translation['table_name'] ?? '')] ?? null;
        $field = (string) ($translation['field'] ?? '');
        $rowId = (int) ($translation['row_id'] ?? 0);

        if ($model === null || $field === '' || $rowId < 1) {
            return null;
        }

        try {
            $row = (array) $model::findById($rowId);
        } catch (Throwable) {
            return null;
        }

        return isset($row[$field]) && is_scalar($row[$field]) ? (string) $row[$field] : null;
    }
}
