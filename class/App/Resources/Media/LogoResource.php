<?php

namespace Wonder\App\Resources\Media;

use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\Resources\Support\SingletonResource;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

final class LogoResource extends SingletonResource
{
    public static string $model = \Wonder\App\Models\Media\Logo::class;

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
            'main' => 'Logo',
            'black' => 'Logo nero',
            'white' => 'Logo bianco',
            'icon' => 'Icona',
            'icon_black' => 'Icona nera',
            'icon_white' => 'Icona bianca',
            'favicon' => 'Favicon',
            'app_icon' => 'Icona app',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('main')->inputFileDragDrop('png'),
            FormInput::key('black')->inputFileDragDrop('png'),
            FormInput::key('white')->inputFileDragDrop('png'),
            FormInput::key('icon')->inputFileDragDrop('png'),
            FormInput::key('icon_black')->inputFileDragDrop('png'),
            FormInput::key('icon_white')->inputFileDragDrop('png'),
            FormInput::key('app_icon')->inputFileDragDrop('png'),
            FormInput::key('favicon')->inputFileDragDrop('ico'),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('main')->columnSpan(4),
                static::getInput('black')->columnSpan(4),
                static::getInput('white')->columnSpan(4),
                static::getInput('icon')->columnSpan(4),
                static::getInput('icon_black')->columnSpan(4),
                static::getInput('icon_white')->columnSpan(4),
            ])->columns(12)->columnSpan(8),
            (new Card)->components([
                static::getInput('app_icon'),
                static::getInput('favicon'),
            ])->columns(1)->columnSpan(4),
        ])->columns(12);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)
            ->fields('show', ['id', 'main', 'black', 'white', 'icon', 'icon_black', 'icon_white', 'favicon', 'app_icon'])
            ->fields('update', ['main', 'black', 'white', 'icon', 'icon_black', 'icon_white', 'favicon', 'app_icon']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('Media', 'media', 'bi-image')
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
