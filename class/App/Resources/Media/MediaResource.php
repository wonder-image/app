<?php

namespace Wonder\App\Resources\Media;

use Wonder\App\Resource;
use Wonder\App\Support\MediaFileManager;
use Wonder\Data\Formatters\String\SlugFormatter;

abstract class MediaResource extends Resource
{
    abstract protected static function mediaType(): string;

    public static function findStoreExistingValues(array $requestValues, string $context = 'backend'): ?array
    {
        $name = trim((string) ($requestValues['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $slug = function_exists('create_link')
            ? create_link($name)
            : (string) SlugFormatter::format($name);

        if ($slug === '') {
            return null;
        }

        $existing = static::modelClass()::find([
            'slug' => $slug,
            'type' => static::mediaType(),
            'deleted' => 'false',
        ], 1);

        return is_array($existing) && $existing !== [] ? $existing : null;
    }

    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        $hasNewUpload = MediaFileManager::hasUploadedFiles($values['file'] ?? null);

        if (!empty($values['name'])) {
            if ($action === 'update' && !$hasNewUpload && !empty($oldValues['slug'])) {
                $values['slug'] = (string) $oldValues['slug'];
            } else {
                $values['slug'] = (string) $values['name'];
            }
        } elseif ($action === 'update' && !empty($oldValues['slug'])) {
            $values['slug'] = (string) $oldValues['slug'];
        }

        $values['type'] = static::mediaType();

        return $values;
    }

    public static function afterDelete(int|string $id, object $result, array $values = []): void
    {
        if (empty($result->success) || empty($values['file'])) {
            return;
        }

        MediaFileManager::deleteFiles(
            ROOT.'/assets/upload/'.trim(static::legacyFolder(), '/'),
            static::fileCleanupFormat(),
            MediaFileManager::decodeStoredFiles($values['file'])
        );
    }

    protected static function fileCleanupFormat(): array
    {
        $input = static::getInput('file');
        $prepare = (array) ($input->get('prepare') ?? []);

        if (!isset($prepare['dir'])) {
            $prepare['dir'] = '/';
        }

        return $prepare;
    }
}
