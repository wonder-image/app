<?php

namespace Wonder\App\Resources\Css;

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

final class CssColorResource extends Resource
{
    public static string $model = \Wonder\App\Models\Css\CssColor::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'colore',
            'plural_label' => 'colori',
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
            'var' => 'Var',
            'name' => 'Nome',
            'color' => 'Colore',
            'contrast' => 'Contrasto',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('var')->text()->required(),
            FormInput::key('name')->text()->required(),
            FormInput::key('color')->color()->required(),
            FormInput::key('contrast')->color()->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('var')->columnSpan(1),
                static::getInput('name')->columnSpan(1),
            ])->columns(2)->columnSpan(2),
            (new Card)->components([
                static::getInput('color'),
                static::getInput('contrast'),
            ])->columns(1)->columnSpan(1),
        ])->columns(3);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')->text()->link('edit'),
            TableColumn::key('var')->text(),
            TableColumn::key('actions')->button()->actions(['edit']),
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
        return PageSchema::for(static::class)
            ->disable(['view', 'delete']);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)
            ->only(['index', 'store', 'show', 'update'])
            ->fields('index', ['id', 'var', 'name', 'color', 'contrast'])
            ->fields('show', ['id', 'var', 'name', 'color', 'contrast'])
            ->fields('store', ['var', 'name', 'color', 'contrast'])
            ->fields('update', ['var', 'name', 'color', 'contrast']);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'create', 'store', 'edit', 'update'], ['admin'])
            ->api(['index', 'store', 'show', 'update'], ['admin']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('Stile', 'css', 'bi-award')
            ->authority(['admin'])
            ->order(10);
    }

    public static function afterStore(object $result, array $values = []): void
    {
        static::refreshCss();
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
        static::refreshCss();
    }

    private static function refreshCss(): void
    {
        if (function_exists('cssRoot')) {
            cssRoot();
        }

        if (function_exists('cssColor')) {
            cssColor();
        }
    }
}
