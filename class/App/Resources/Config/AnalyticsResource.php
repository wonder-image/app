<?php

namespace Wonder\App\Resources\Config;

use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\Resources\Support\SingletonResource;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

final class AnalyticsResource extends SingletonResource
{
    public static string $model = \Wonder\App\Models\Config\Analytics::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'analitica',
            'plural_label' => 'analitica',
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
            'tag_manager' => 'Tag manager',
            'active_tag_manager' => 'Attivo',
            'pixel_facebook' => 'ID Pixel',
            'active_pixel_facebook' => 'Attivo',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('tag_manager')->text(),
            FormInput::key('active_tag_manager')
                ->select()
                ->options(['true' => 'Si', 'false' => 'No'])
                ->value('false')
                ->required(),
            FormInput::key('pixel_facebook')->text(),
            FormInput::key('active_pixel_facebook')
                ->select()
                ->options(['true' => 'Si', 'false' => 'No'])
                ->value('false')
                ->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                '<div class="col-12"><h6>Google</h6></div>',
                static::getInput('tag_manager')->columnSpan(8),
                static::getInput('active_tag_manager')->columnSpan(4),
            ])->columns(12)->columnSpan(6),
            (new Card)->components([
                '<div class="col-12"><h6>Facebook</h6></div>',
                static::getInput('pixel_facebook')->columnSpan(8),
                static::getInput('active_pixel_facebook')->columnSpan(4),
            ])->columns(12)->columnSpan(6),
        ])->columns(12);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)
            ->fields('show', ['id', 'tag_manager', 'active_tag_manager', 'pixel_facebook', 'active_pixel_facebook'])
            ->fields('update', ['tag_manager', 'active_tag_manager', 'pixel_facebook', 'active_pixel_facebook']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('Set Up', 'set-up', 'bi-gear')
            ->title('Analitica')
            ->order(60)
            ->authority(['admin']);
    }
}
