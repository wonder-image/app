<?php

namespace Wonder\App\Resources\Css;

use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\Resources\Support\CssSingleton;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

final class CssAuthResource extends CssSingleton
{
    public static string $model = \Wonder\App\Models\Css\CssAuth::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'autenticazione',
            'plural_label' => 'autenticazione',
            'last' => 'ultime',
            'all' => 'tutte',
            'article' => 'le',
            'full' => 'piena',
            'empty' => 'vuota',
            'this' => 'questa',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'bg_color' => 'Sfondo autenticazione',
            'tx_color' => 'Testo autenticazione',
            'form_bg_color' => 'Sfondo form',
            'form_tx_color' => 'Testo form',
            'form_border_color' => 'Bordo form',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('bg_color')->color()->required(),
            FormField::key('tx_color')->color()->required(),
            FormField::key('form_bg_color')->color()->required(),
            FormField::key('form_tx_color')->color()->required(),
            FormField::key('form_border_color')->color()->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('bg_color')->columnSpan(6),
                static::getInput('tx_color')->columnSpan(6),
                static::getInput('form_bg_color')->columnSpan(4),
                static::getInput('form_tx_color')->columnSpan(4),
                static::getInput('form_border_color')->columnSpan(4),
            ])->columns(12)->columnSpan(12),
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

    protected static function formDefaults(): array
    {
        return \Wonder\App\SeedDefaults::cssAuthRow();
    }

    public static function navigationSchema(): NavigationSchema
    {
        return parent::navigationSchema()
            ->title('Autenticazione')
            ->order(45);
    }
}
