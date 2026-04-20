<?php

namespace Wonder\App\Resources\Css;

use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\Resources\Support\CssSingleton;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

final class CssDropdownResource extends CssSingleton
{
    public static string $model = \Wonder\App\Models\Css\CssDropdown::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'dropdown',
            'plural_label' => 'dropdown',
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
            'tx' => 'Testo',
            'bg' => 'Sfondo',
            'bg_hover' => 'Sfondo hover',
            'border_color' => 'Bordi',
            'border_width' => 'Spessore',
            'border_radius' => 'Raggio',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('border_width')->text()->required(),
            FormInput::key('border_radius')->text()->required(),
            FormInput::key('tx')->color()->required(),
            FormInput::key('bg')->color()->required(),
            FormInput::key('bg_hover')->color()->required(),
            FormInput::key('border_color')->color()->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('border_width')->columnSpan(6),
                static::getInput('border_radius')->columnSpan(6),
            ])->columns(12)->columnSpan(9),
            (new Card)->components([
                static::getInput('tx'),
                static::getInput('bg'),
                static::getInput('bg_hover'),
                static::getInput('border_color'),
            ])->columns(1)->columnSpan(3),
        ])->columns(12);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('id')->text()->link('edit'),
        ];
    }

    public static function apiSchema(): ApiSchema
    {
        return parent::apiSchema()
            ->fields('show', array_keys(static::labelSchema()))
            ->fields('update', array_keys(static::labelSchema()));
    }

    public static function navigationSchema(): NavigationSchema
    {
        return parent::navigationSchema()
            ->title('Dropdown')
            ->order(60);
    }
}
