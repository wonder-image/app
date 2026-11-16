<?php

namespace Wonder\App\Resources\Media;

use Wonder\App\Models\Media\Logo;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\Resources\Support\SingletonResource;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

final class LogoResource extends SingletonResource
{
    public static string $model = Logo::class;

    public static function legacyFolder(): string
    {
        return 'logos';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'logo',
            'plural_label' => 'loghi',
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
            'main_id' => 'Logo',
            'black_id' => 'Logo nero',
            'white_id' => 'Logo bianco',
            'icon_id' => 'Icona',
            'icon_black_id' => 'Icona nera',
            'icon_white_id' => 'Icona bianca',
            'app_icon' => 'Icona app',
        ];
    }

    public static function formSchema(): array
    {
        return [
            // Loghi e icone: media della libreria, caricati o scelti. Solo png:
            // le email usano il logo e Gmail/Outlook non mostrano gli svg.
            Logo::mediaInput('main')->extensions('png'),
            Logo::mediaInput('black')->extensions('png'),
            Logo::mediaInput('white')->extensions('png'),
            Logo::mediaInput('icon')->extensions('png'),
            Logo::mediaInput('icon_black')->extensions('png'),
            Logo::mediaInput('icon_white')->extensions('png'),
            // L'icona app resta un file: si converte in png e ha misure fisse.
            FormField::key('app_icon')->fileDragDrop('image'),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('main_id')->columnSpan(4),
                static::getInput('black_id')->columnSpan(4),
                static::getInput('white_id')->columnSpan(4),
                static::getInput('icon_id')->columnSpan(4),
                static::getInput('icon_black_id')->columnSpan(4),
                static::getInput('icon_white_id')->columnSpan(4),
            ])->columns(12)->columnSpan(8),
            (new Card)->components([
                static::getInput('app_icon'),
            ])->columns(1)->columnSpan(4),
        ])->columns(12);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)
            ->only(['show', 'update'])
            ->fields('show', ['id', 'main_id', 'black_id', 'white_id', 'icon_id', 'icon_black_id', 'icon_white_id', 'app_icon'])
            ->fields('update', ['main_id', 'black_id', 'white_id', 'icon_id', 'icon_black_id', 'icon_white_id', 'app_icon']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('media')
            ->title('Logo')
            ->order(10)
            ->authority(['admin']);
    }

    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        $slug = '';

        if (function_exists('infoSociety')) {
            $society = infoSociety();
            $slug = trim((string) ($society->name ?? ''));
        }

        if ($slug === '' && is_array($oldValues)) {
            $slug = trim((string) ($oldValues['slug'] ?? ''));
        }

        $values['slug'] = $slug !== '' ? $slug : 'logo';

        return $values;
    }
}
