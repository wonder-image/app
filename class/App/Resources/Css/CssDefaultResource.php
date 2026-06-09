<?php

namespace Wonder\App\Resources\Css;

use SensitiveParameter;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\Resources\Support\CssSingleton;
use Wonder\Elements\Components\{Card, Container, SectionTitle};
use Wonder\Elements\Form\Form;

final class CssDefaultResource extends CssSingleton
{
    public static string $model = \Wonder\App\Models\Css\CssDefault::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'css',
            'plural_label' => 'css',
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
            'font_id' => 'Font family',
            'font_weight' => 'Font weight',
            'font_size' => 'Font size',
            'line_height' => 'Line height',
            'title_big_font_id' => 'Font family',
            'title_big_font_weight' => 'Font weight',
            'title_big_font_size' => 'Font size',
            'title_big_line_height' => 'Line height',
            'title_font_id' => 'Font family',
            'title_font_weight' => 'Font weight',
            'title_font_size' => 'Font size',
            'title_line_height' => 'Line height',
            'subtitle_font_id' => 'Font family',
            'subtitle_font_weight' => 'Font weight',
            'subtitle_font_size' => 'Font size',
            'subtitle_line_height' => 'Line height',
            'text_font_id' => 'Font family',
            'text_font_weight' => 'Font weight',
            'text_font_size' => 'Font size',
            'text_line_height' => 'Line height',
            'text_small_font_id' => 'Font family',
            'text_small_font_weight' => 'Font weight',
            'text_small_font_size' => 'Font size',
            'text_small_line_height' => 'Line height',
            'button_font_size' => 'Font size',
            'button_line_height' => 'Line height',
            'button_font_weight' => 'Font weight',
            'button_border_radius' => 'Raggio bordi',
            'button_border_width' => 'Spessore bordi',
            'badge_font_size' => 'Font size',
            'badge_line_height' => 'Line height',
            'badge_font_weight' => 'Font weight',
            'badge_border_radius' => 'Raggio bordi',
            'badge_border_width' => 'Spessore bordi',
            'tx_color' => 'Testo',
            'bg_color' => 'Sfondo',
            'spacer' => 'Spaziatore',
            'header_height' => 'Altezza header',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('font_id')->select(static::fontOptions())->required(),
            FormField::key('font_weight')->text()->required(),
            FormField::key('font_size')->text()->required(),
            FormField::key('line_height')->text()->required(),
            FormField::key('title_big_font_id')->select(static::fontOptions())->required(),
            FormField::key('title_big_font_weight')->text()->required(),
            FormField::key('title_big_font_size')->text()->required(),
            FormField::key('title_big_line_height')->text()->required(),
            FormField::key('title_font_id')->select(static::fontOptions())->required(),
            FormField::key('title_font_weight')->text()->required(),
            FormField::key('title_font_size')->text()->required(),
            FormField::key('title_line_height')->text()->required(),
            FormField::key('subtitle_font_id')->select(static::fontOptions())->required(),
            FormField::key('subtitle_font_weight')->text()->required(),
            FormField::key('subtitle_font_size')->text()->required(),
            FormField::key('subtitle_line_height')->text()->required(),
            FormField::key('text_font_id')->select(static::fontOptions())->required(),
            FormField::key('text_font_weight')->text()->required(),
            FormField::key('text_font_size')->text()->required(),
            FormField::key('text_line_height')->text()->required(),
            FormField::key('text_small_font_id')->select(static::fontOptions())->required(),
            FormField::key('text_small_font_weight')->text()->required(),
            FormField::key('text_small_font_size')->text()->required(),
            FormField::key('text_small_line_height')->text()->required(),
            FormField::key('tx_color')->color()->required(),
            FormField::key('bg_color')->color()->required(),
            FormField::key('button_font_size')->text()->required(),
            FormField::key('button_line_height')->text()->required(),
            FormField::key('button_font_weight')->text()->required(),
            FormField::key('button_border_radius')->text()->required(),
            FormField::key('button_border_width')->text()->required(),
            FormField::key('badge_font_size')->text()->required(),
            FormField::key('badge_line_height')->text()->required(),
            FormField::key('badge_font_weight')->text()->required(),
            FormField::key('badge_border_radius')->text()->required(),
            FormField::key('badge_border_width')->text()->required(),
            FormField::key('spacer')->text()->required(),
            FormField::key('header_height')->text()->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([

            (new Container)->components([
            
                (new Card)->components([
                    (new SectionTitle)->text('Default')->columnSpan(12),
                    static::getInput('font_id')->columnSpan(5),
                    static::getInput('font_weight')->columnSpan(3),
                    static::getInput('font_size')->columnSpan(2),
                    static::getInput('line_height')->columnSpan(2),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    (new SectionTitle)->text('Titolo grande')->columnSpan(12),
                    static::getInput('title_big_font_id')->columnSpan(5),
                    static::getInput('title_big_font_weight')->columnSpan(3),
                    static::getInput('title_big_font_size')->columnSpan(2),
                    static::getInput('title_big_line_height')->columnSpan(2),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    (new SectionTitle)->text('Titolo')->columnSpan(12),
                    static::getInput('title_font_id')->columnSpan(5),
                    static::getInput('title_font_weight')->columnSpan(3),
                    static::getInput('title_font_size')->columnSpan(2),
                    static::getInput('title_line_height')->columnSpan(2),
                ])->columns(12)->columnSpan(1),
                    
                (new Card)->components([
                    (new SectionTitle)->text('Sottotitolo')->columnSpan(12),
                    static::getInput('subtitle_font_id')->columnSpan(5),
                    static::getInput('subtitle_font_weight')->columnSpan(3),
                    static::getInput('subtitle_font_size')->columnSpan(2),
                    static::getInput('subtitle_line_height')->columnSpan(2),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    (new SectionTitle)->text('Testo')->columnSpan(12),
                    static::getInput('text_font_id')->columnSpan(5),
                    static::getInput('text_font_weight')->columnSpan(3),
                    static::getInput('text_font_size')->columnSpan(2),
                    static::getInput('text_line_height')->columnSpan(2),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    (new SectionTitle)->text('Testo piccolo')->columnSpan(12),
                    static::getInput('text_small_font_id')->columnSpan(5),
                    static::getInput('text_small_font_weight')->columnSpan(3),
                    static::getInput('text_small_font_size')->columnSpan(2),
                    static::getInput('text_small_line_height')->columnSpan(2),
                ])->columns(12)->columnSpan(1),
                
            ])->columns(1)->columnSpan(9),
            

            (new Container)->components([

                (new Card)->components([
                    (new SectionTitle)->text('Colori')->columnSpan(12),
                    static::getInput('tx_color'),
                    static::getInput('bg_color'),
                ])->columns(1)->columnSpan(1),

                (new Card)->components([
                    (new SectionTitle)->text('Bottoni')->columnSpan(12),
                    static::getInput('button_font_size')->columnSpan(6),
                    static::getInput('button_line_height')->columnSpan(6),
                    static::getInput('button_font_weight')->columnSpan(12),
                    static::getInput('button_border_radius')->columnSpan(6),
                    static::getInput('button_border_width')->columnSpan(6),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    (new SectionTitle)->text('Badge')->columnSpan(12),
                    static::getInput('badge_font_size')->columnSpan(6),
                    static::getInput('badge_line_height')->columnSpan(6),
                    static::getInput('badge_font_weight')->columnSpan(12),
                    static::getInput('badge_border_radius')->columnSpan(6),
                    static::getInput('badge_border_width')->columnSpan(6),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    (new SectionTitle)->text('Spacer')->columnSpan(12),
                    static::getInput('spacer')->columnSpan(12),
                    static::getInput('header_height')->columnSpan(12),
                ])->columns(12)->columnSpan(1),

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

    protected static function formDefaults(): array
    {
        return \Wonder\App\RuntimeDefaults::cssDefaultRow();
    }

    public static function navigationSchema(): NavigationSchema
    {
        return parent::navigationSchema()
            ->title('Default')
            ->order(10);
    }

    private static function fontOptions(): array
    {
        if (!function_exists('sqlSelect')) {
            return [];
        }

        $options = [];

        try {
            foreach ((array) (sqlSelect('css_font', ['visible' => 'true'])->row ?? []) as $row) {
                if (!is_array($row) || !isset($row['id'])) {
                    continue;
                }

                $options[(string) $row['id']] = \Wonder\App\Support\CssFontFamily::normalize($row['font_family'] ?? '');
            }
        } catch (\Throwable) {
            return [];
        }

        return $options;
    }
}
