<?php

namespace Wonder\App\Resources\Config;

use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\Resources\Support\SingletonResource;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

final class SeoResource extends SingletonResource
{
    public static string $model = \Wonder\App\Models\Config\Seo::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'seo',
            'plural_label' => 'seo',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'i',
            'full' => 'usato',
            'empty' => 'non usato',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'title' => 'Titolo',
            'description' => 'Descrizione',
            'author' => 'Autore',
            'copyright' => 'Copyright',
            'creator' => 'Nome',
            'reply' => 'Email',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('title')->text()->required(),
            FormField::key('description')->textarea()->required(),
            FormField::key('author')->text()->required(),
            FormField::key('copyright')->text()->required(),
            FormField::key('creator')->text()->required(),
            FormField::key('reply')->email()->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                '<div class="col-12"><h6>Default SEO</h6></div>',
                static::getInput('title')->columnSpan(8),
                static::getInput('description')->columnSpan(12),
                static::getInput('author')->columnSpan(6),
                static::getInput('copyright')->columnSpan(6),
            ])->columns(12)->columnSpan(9),
            (new Card)->components([
                '<div class="col-12"><h6>Creatore</h6></div>',
                static::getInput('creator'),
                static::getInput('reply'),
            ])->columns(1)->columnSpan(3),
        ])->columns(12);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)
            ->fields('show', ['id', 'title', 'description', 'author', 'copyright', 'creator', 'reply'])
            ->fields('update', ['title', 'description', 'author', 'copyright', 'creator', 'reply']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('set-up')
            ->title('Seo')
            ->order(20)
            ->authority(['admin']);
    }
}
