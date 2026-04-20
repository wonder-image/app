<?php

namespace Wonder\App\Resources\Css;

use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\Resources\Support\CssSingleton;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

final class CssInputResource extends CssSingleton
{
    public static string $model = \Wonder\App\Models\Css\CssInput::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'input',
            'plural_label' => 'input',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'gli',
            'full' => 'pieno',
            'empty' => 'vuoto',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'border_color' => 'Colore',
            'border_color_focus' => 'Colore focus',
            'border_radius' => 'Raggio',
            'border_top' => 'Alto',
            'border_right' => 'Destra',
            'border_bottom' => 'Basso',
            'border_left' => 'Sinistra',
            'date_default' => 'Data default',
            'date_active' => 'Data attiva',
            'date_bg' => 'Data',
            'date_bg_hover' => 'Data hover',
            'date_border_radius' => 'Raggio',
            'label_color' => 'Colore',
            'label_color_focus' => 'Colore focus',
            'label_weight' => 'Font weight',
            'label_weight_focus' => 'Font weight focus',
            'tx_color' => 'Testo',
            'bg_color' => 'Sfondo',
            'disabled_bg_color' => 'Sfondo disabilitato',
            'dropdown_tx_color' => 'Testo',
            'dropdown_bg_color' => 'Sfondo',
            'select_hover' => 'Hover',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('border_color')->color()->required(),
            FormInput::key('border_color_focus')->color()->required(),
            FormInput::key('border_radius')->text()->required(),
            FormInput::key('border_top')->text()->required(),
            FormInput::key('border_right')->text()->required(),
            FormInput::key('border_bottom')->text()->required(),
            FormInput::key('border_left')->text()->required(),
            FormInput::key('date_default')->color()->required(),
            FormInput::key('date_active')->color()->required(),
            FormInput::key('date_bg')->color()->required(),
            FormInput::key('date_bg_hover')->color()->required(),
            FormInput::key('date_border_radius')->text()->required(),
            FormInput::key('label_color')->color()->required(),
            FormInput::key('label_color_focus')->color()->required(),
            FormInput::key('label_weight')->text()->required(),
            FormInput::key('label_weight_focus')->text()->required(),
            FormInput::key('tx_color')->color()->required(),
            FormInput::key('bg_color')->color()->required(),
            FormInput::key('disabled_bg_color')->color()->required(),
            FormInput::key('dropdown_tx_color')->color()->required(),
            FormInput::key('dropdown_bg_color')->color()->required(),
            FormInput::key('select_hover')->color()->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('border_color')->columnSpan(6),
                static::getInput('border_color_focus')->columnSpan(6),
                static::getInput('border_radius')->columnSpan(12),
                static::getInput('border_top')->columnSpan(3),
                static::getInput('border_right')->columnSpan(3),
                static::getInput('border_bottom')->columnSpan(3),
                static::getInput('border_left')->columnSpan(3),
            ])->columns(12)->columnSpan(6),

            (new Card)->components([
                static::getInput('date_default')->columnSpan(6),
                static::getInput('date_active')->columnSpan(6),
                static::getInput('date_bg')->columnSpan(6),
                static::getInput('date_bg_hover')->columnSpan(6),
                static::getInput('date_border_radius')->columnSpan(12),
            ])->columns(12)->columnSpan(6),

            (new Card)->components([
                static::getInput('label_color')->columnSpan(6),
                static::getInput('label_color_focus')->columnSpan(6),
                static::getInput('label_weight')->columnSpan(6),
                static::getInput('label_weight_focus')->columnSpan(6),
            ])->columns(12)->columnSpan(6),

            (new Card)->components([
                static::getInput('tx_color')->columnSpan(6),
                static::getInput('bg_color')->columnSpan(6),
                static::getInput('disabled_bg_color')->columnSpan(12),
            ])->columns(12)->columnSpan(3),

            (new Card)->components([
                static::getInput('dropdown_tx_color')->columnSpan(6),
                static::getInput('dropdown_bg_color')->columnSpan(6),
            ])->columns(12)->columnSpan(3),

            (new Card)->components([
                static::getInput('select_hover'),
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
            ->title('Input')
            ->order(40);
    }
}
