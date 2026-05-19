<?php

namespace Wonder\App\Models\Communications;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Popup extends Model
{
    public static string $table = 'popup';
    public static string $folder = 'popups';
    public static string $icon = 'bi bi-window-stack';

    public static function tableSchema(): array
    {
        return [
            Column::key('position')->int(),
            ...static::sqlColumnsFromDataSchema([
                'code',
                'slug',
                'name',
                'title',
                'bg_color',
                'tx_color',
                'url',
                'url_label',
                'view',
                'visible',
            ]),
            Column::key('pages')->json(),
            Column::key('images')->json(),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('code')->text()->uniqueCode('pop_'),
            Field::key('slug')->text()->slug(),
            Field::key('name')->text()->sanitizeFirst(),
            Field::key('title')->text(),
            Field::key('bg_color')->text(),
            Field::key('tx_color')->text(),
            Field::key('url')->text(),
            Field::key('url_label')->text(),
            Field::key('view')->text(),
            Field::key('visible')->text(),
            Field::key('pages')->json(),
            Field::key('images')
                ->image()
                ->reset()
                ->extensions(['png', 'jpg', 'jpeg'])
                ->maxSize(2)
                ->maxFile(1)
                ->dir('/images/')
                ->responsive(),
        ];
    }

    public static function currentForPageKey(string $pageKey): ?array
    {
        $pageKey = trim($pageKey);

        if ($pageKey === '') {
            return null;
        }

        $escapedPageKey = addslashes($pageKey);
        $condition = 'pages LIKE '."'%\\\"{$escapedPageKey}\\\"%'".' AND visible = \'true\' AND deleted = \'false\'';
        $popup = static::find($condition, 1, 'position', 'ASC');

        return is_array($popup) && $popup !== [] ? $popup : null;
    }

    public static function modalPayloadForPageKey(string $pageKey): ?array
    {
        $popup = static::currentForPageKey($pageKey);

        if ($popup === null) {
            return null;
        }

        $images = json_decode((string) ($popup['images'] ?? ''), true);
        $image = is_array($images) ? ($images[0] ?? null) : null;
        $bgColor = static::normalizeThemeColorVar($popup['bg_color'] ?? 'white');
        $txColor = static::normalizeThemeColorVar($popup['tx_color'] ?? 'black');

        return [
            'id' => (int) ($popup['id'] ?? 0),
            'code' => trim((string) ($popup['code'] ?? '')),
            'title' => trim((string) ($popup['title'] ?? '')),
            'url' => trim((string) ($popup['url'] ?? '')),
            'url_label' => trim((string) ($popup['url_label'] ?? '')),
            'view' => trim((string) ($popup['view'] ?? '')),
            'image' => is_string($image) ? trim($image) : null,
            'bg_color' => $bgColor,
            'tx_color' => $txColor,
            'content_class' => 'bg-'.$bgColor.' tx-'.$bgColor.'-o',
            'footer_class' => 'b-0 bg-'.$bgColor.' tx-'.$bgColor.'-o',
        ];
    }

    private static function normalizeThemeColorVar(mixed $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9_-]+/', '', $value) ?? '';

        return $value !== '' ? $value : 'white';
    }
}
