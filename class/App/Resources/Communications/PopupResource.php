<?php

namespace Wonder\App\Resources\Communications;

use Throwable;
use Wonder\App\Resource;
use Wonder\App\RuntimeDefaults;
use Wonder\App\Models\Css\CssColor;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\App\Support\FrontendRouteCatalog;
use Wonder\Elements\Components\{Card, Container};
use Wonder\Elements\Form\Form;

final class PopupResource extends Resource
{
    public static string $model = \Wonder\App\Models\Communications\Popup::class;

    public static string $orderColumn = 'position';
    public static string $orderDirection = 'ASC';

    public static function path(): string
    {
        return 'popups';
    }

    public static function icon(): string
    {
        return 'bi bi-window-stack';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'popup',
            'plural_label' => 'popup',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'i',
            'full' => 'visibile',
            'empty' => 'nascosto',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'slug' => 'Slug',
            'name' => 'Nome interno',
            'title' => 'Titolo',
            'bg_color' => 'Sfondo',
            'tx_color' => 'Colore testo',
            'url' => 'URL azione',
            'url_label' => 'Etichetta CTA',
            'pages' => 'Pagine',
            'view' => 'N° Visualizzazioni',
            'images' => 'Immagine',
            'visible' => 'Stato',
            'position' => 'Ordine',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('name')
                ->text()
                ->required(),
            FormField::key('title')
                ->text(),
            FormField::key('bg_color')
                ->select(static::colorOptions(), 'old')
                ->value('white')
                ->required(),
            FormField::key('tx_color')
                ->select(static::colorOptions(), 'old')
                ->value('black')
                ->required(),
            FormField::key('url')
                ->text(),
            FormField::key('url_label')
                ->text(),
            FormField::key('pages')
                ->checkbox()
                ->options(static::frontendPageOptions())
                ->searchBar()
                ->required(),
            FormField::key('view')
                ->number()
                ->attribute('min="0" step="1"'),
            FormField::key('images')
                ->fileDragDrop('image'),
            FormField::key('visible')
                ->select([
                    'true' => 'Visibile',
                    'false' => 'Nascosto',
                ], 'old')
                ->value('true')
                ->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([

            (new Card)->components([
                (new Container)->components([
                    static::getInput('images')->columnSpan(2)
                ])->columns(1)->columnSpan(4),
                (new Container)->components([
                    static::getInput('name')->columnSpan(1),
                    static::getInput('title')->columnSpan(1),
                    static::getInput('url')->columnSpan(1),
                    static::getInput('url_label')->columnSpan(1),
                    static::getInput('pages')->columnSpan(2)
                ])->columns(2)->columnSpan(8),
            ])->columns(12)->columnSpan(2),

            (new Card)->components([
                static::getInput('view'),
                static::getInput('bg_color'),
                static::getInput('tx_color'),
                static::getInput('visible'),
            ])->columns(1)->columnSpan(1),

        ])->columns(3);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('images')->image()->link('edit')->size('little'),
            TableColumn::key('name')->text()->link('edit'),
            TableColumn::key('title')->text(),
            TableColumn::key('visible')
                ->badge()
                ->function('visible', 'id', 'automaticResize')
                ->size('little'),
            TableColumn::key('actions')
                ->button()
                ->actions(['edit', 'visible', 'delete']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Lista '.static::pluralLabel())
            ->results()
            ->buttonAdd('Aggiungi '.static::label())
            ->filters();
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)
            ->fields('index', ['id', 'slug', 'name', 'title', 'bg_color', 'url', 'url_label', 'view', 'images', 'pages', 'position', 'visible']);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin', 'administrator'])
            ->apiCrud(['admin', 'administrator']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('notices')
            ->title('Popup')
            ->order(30)
            ->authority(['admin', 'administrator']);
    }

    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        if (!empty($values['name']) && empty($values['slug'])) {
            $values['slug'] = $values['name'];
        }

        return $values;
    }

    public static function mutateFormValues(
        array $values,
        string $mode,
        string $context = 'backend'
    ): array {
        if (isset($values['pages']) && is_string($values['pages']) && trim($values['pages']) !== '') {
            $decoded = json_decode($values['pages'], true);

            if (is_array($decoded)) {
                $values['pages'] = $decoded;
            }
        }

        return $values;
    }

    private static function frontendPageOptions(): array
    {
        return [
            \Wonder\App\Models\Communications\Popup::ALL_PAGES_KEY => 'Tutte le pagine',
        ] + FrontendRouteCatalog::options();
    }

    private static function colorOptions(): array
    {
        try {
            $options = [];

            foreach (CssColor::all(['var', 'name']) as $row) {
                $var = trim((string) ($row['var'] ?? ''));

                if ($var === '') {
                    continue;
                }

                $options[$var] = trim((string) ($row['name'] ?? $var));
            }

            if ($options !== []) {
                return $options;
            }
        } catch (Throwable) {
        }

        $fallback = [];

        foreach (RuntimeDefaults::defaultColors() as $color) {
            $var = trim((string) ($color['var'] ?? ''));

            if ($var === '') {
                continue;
            }

            $fallback[$var] = trim((string) ($color['name'] ?? $var));
        }

        return $fallback;
    }
}
