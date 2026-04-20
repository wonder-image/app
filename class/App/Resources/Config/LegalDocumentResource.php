<?php

namespace Wonder\App\Resources\Config;

use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;
use Wonder\Http\Route;

final class LegalDocumentResource extends Resource
{
    public static string $model = \Wonder\App\Models\Config\LegalDocument::class;
    public static array|string $condition = [];

    public static string $orderColumn = 'published_at';
    public static string $orderDirection = 'DESC';

    public static function textSchema(): array
    {
        return [
            'label' => 'documento legale',
            'plural_label' => 'documenti legali',
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
            'name' => 'Nome',
            'doc_type' => 'Tipologia documento',
            'language_code' => 'Lingua',
            'version' => 'Versione',
            'published_at' => 'Pubblicato il',
            'checkbox_label' => 'Testo checkbox',
            'content_snapshot' => 'Testo',
            'content_hash' => 'Hash contenuto',
            'active' => 'Stato',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('name')->text(),
            FormInput::key('doc_type')->select(static::documentTypes())->required(),
            FormInput::key('language_code')->select(static::languageOptions())->required(),
            FormInput::key('version')->text()->required(),
            FormInput::key('published_at')->textDatetime()->required()->value(date('Y-m-d\TH:i')),
            FormInput::key('checkbox_label')->textarea('plus')->required()->prepare('sanitize', false),
            FormInput::key('content_snapshot')->textarea('blog')->required()->prepare('sanitize', false),
            FormInput::key('active')
                ->select(['true' => 'Attivo', 'false' => 'Non attivo'])
                ->old()
                ->value('true')
                ->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('name')->columnSpan(8),
                static::getInput('doc_type')->columnSpan(4),
                static::getInput('language_code')->columnSpan(4),
                static::getInput('version')->columnSpan(4),
                static::getInput('published_at')->columnSpan(4),
                static::getInput('checkbox_label')->columnSpan(12),
            ])->columns(12)->columnSpan(9),
            (new Card)->components([
                static::getInput('active')->columnSpan(12),
            ])->columns(12)->columnSpan(3),
            (new Card)->components([
                static::getInput('content_snapshot')->columnSpan(12),
            ])->columns(12)->columnSpan(12),
        ])->columns(12);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')->text()->link('view'),
            TableColumn::key('language_code')->text()->size('little'),
            TableColumn::key('version')->text()->size('little'),
            TableColumn::key('active')->badge()->function('active', 'id', 'automaticResize')->size('little'),
            TableColumn::key('published_at')->datetime()->size('medium'),
            TableColumn::key('actions')->button()->actions(['view', 'download', 'edit']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Lista '.static::pluralLabel())
            ->results()
            ->buttonAdd('Aggiungi '.static::label())
            ->filters()
            ->searchFields(['name', 'doc_type', 'checkbox_label', 'version', 'language_code', 'content_hash'])
            ->filterRadio('Stato', 'active', [
                '' => 'Tutti',
                'true' => 'Attivi',
                'false' => 'Non attivi',
            ])
            ->filterRadio('Lingua', 'language_code', ['' => 'Tutte'] + static::languageOptions());
    }

    public static function pageSchema(): PageSchema
    {
        return static::page()
            ->enable('view')
            ->disable('delete')
            ->view('show', static::customShowViewPath())
            ->title('view', 'Documento legale');
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)
            ->only(['index', 'store', 'show', 'update'])
            ->fields('index', ['id', 'name', 'doc_type', 'version', 'language_code', 'content_hash', 'published_at', 'active'])
            ->fields('show', ['id', 'name', 'doc_type', 'version', 'language_code', 'checkbox_label', 'content_hash', 'content_snapshot', 'published_at', 'active'])
            ->fields('store', ['name', 'doc_type', 'version', 'language_code', 'checkbox_label', 'content_snapshot', 'published_at', 'active'])
            ->fields('update', ['name', 'doc_type', 'version', 'language_code', 'checkbox_label', 'content_snapshot', 'published_at', 'active']);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'create', 'store', 'view', 'edit', 'update'], ['admin'])
            ->api(['index', 'store', 'show', 'update'], ['admin']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('Set Up', 'set-up', 'bi-gear')
            ->title('Documenti legali')
            ->order(30)
            ->authority(['admin']);
    }

    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        if (!empty($values['content_snapshot'])) {
            $values['content_hash'] = hash('sha256', (string) $values['content_snapshot']);
        }

        if (!empty($values['doc_type']) && trim((string) ($values['name'] ?? '')) === '') {
            $values['name'] = ucwords(str_replace(['_', '-'], ' ', (string) $values['doc_type']));
        }

        return $values;
    }

    public static function mutateFormValues(
        array $values,
        string $mode,
        string $context = 'backend'
    ): array {
        if (!empty($values['published_at']) && strtotime((string) $values['published_at']) !== false) {
            $values['published_at'] = date('Y-m-d\TH:i', strtotime((string) $values['published_at']));
        }

        return $values;
    }

    public static function registerBackendRoutes(string $rootApp, string $slug): void
    {
        Route::get('/{id}/download/', $rootApp.'/http/backend/config/legal-documents/download.php', [
            'resource' => $slug,
            'resource_action' => 'download',
        ])->name('download')
            ->permit(['admin'])
            ->where('id', '[0-9]+');
    }

    private static function documentTypes(): array
    {
        return legalDocumentTypes();
    }

    private static function languageOptions(): array
    {
        return array_map(
            static fn ($lang) => $lang['name'] ?? '',
            __ls()
        );
    }

    private static function customShowViewPath(): ?string
    {
        $rootApp = (string) LegacyGlobals::get('ROOT_APP', '');

        return $rootApp !== ''
            ? $rootApp.'/view/pages/backend/config/legal-documents/show.php'
            : null;
    }
}
